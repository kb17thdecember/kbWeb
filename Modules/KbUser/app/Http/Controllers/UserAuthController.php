<?php

namespace Modules\KbUser\Http\Controllers;

use App\Actions\Teams\CreateTeam;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserAuthController extends Controller
{
    private const GOOGLE_STATE_SESSION_KEY = 'kbuser_google_oauth_state';

    public function __construct(private CreateTeam $createTeam) {}

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
        ], [
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $throttleKey = 'kbuser-login|'.Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau '.RateLimiter::availableIn($throttleKey).' giây.',
            ]);
        }

        if (! Auth::attempt([
            'email' => Str::lower($credentials['email']),
            'password' => $credentials['password'],
        ], $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không chính xác.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(route('kbuser.home'));
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email:rfc',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s().-]{8,30}$/'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ], [
            'name.required' => 'Vui lòng nhập họ và tên.',
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'email.unique' => 'Email này đã được sử dụng cho một tài khoản khác.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ (gồm 8–30 ký tự số).',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
        ]);

        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'password' => $validated['password'],
            ]);

            $this->createTeam->handle($user, $user->name."'s Team", isPersonal: true);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('kbuser.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('kbuser.home');
    }

    public function redirectToGoogle(Request $request): RedirectResponse
    {
        $clientId = (string) config('services.google.client_id', '');

        if ($clientId === '') {
            return to_route('kbuser.home')->with(
                'google_error',
                'Chức năng đăng nhập Google chưa được cấu hình (thiếu GOOGLE_CLIENT_ID trong .env).',
            );
        }

        $state = Str::random(40);
        $request->session()->put(self::GOOGLE_STATE_SESSION_KEY, $state);

        $redirectUri = $this->resolveGoogleRedirectUri();

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull(self::GOOGLE_STATE_SESSION_KEY);
        $incomingState = (string) $request->query('state', '');
        $code = (string) $request->query('code', '');

        if (
            ! is_string($expectedState) ||
            $expectedState === '' ||
            ! hash_equals($expectedState, $incomingState) ||
            $code === ''
        ) {
            return to_route('kbuser.home')->with(
                'google_error',
                'Phiên xác thực Google không hợp lệ hoặc đã bị hủy. Vui lòng thử lại.',
            );
        }

        $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => (string) config('services.google.client_id', ''),
            'client_secret' => (string) config('services.google.client_secret', ''),
            'redirect_uri' => $this->resolveGoogleRedirectUri(),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ]);

        if (! $tokenResponse->successful() || ! is_string($tokenResponse->json('access_token'))) {
            return to_route('kbuser.home')->with(
                'google_error',
                'Không thể lấy mã truy cập từ Google. Vui lòng thử lại.',
            );
        }

        $profileResponse = Http::withToken($tokenResponse->json('access_token'))
            ->get('https://www.googleapis.com/oauth2/v3/userinfo');

        $googleId = $profileResponse->json('sub');
        $email = $profileResponse->json('email');
        $name = $profileResponse->json('name');
        $avatar = $profileResponse->json('picture');

        if (! $profileResponse->successful() || ! is_string($googleId) || ! is_string($email) || $email === '') {
            return to_route('kbuser.home')->with(
                'google_error',
                'Không thể đọc thông tin tài khoản Google của bạn.',
            );
        }

        $normalizedEmail = Str::lower($email);
        $displayName = is_string($name) && trim($name) !== '' ? trim($name) : Str::before($normalizedEmail, '@');

        $user = DB::transaction(function () use ($googleId, $normalizedEmail, $displayName, $avatar): User {
            $existingUser = User::query()
                ->where('google_id', $googleId)
                ->orWhere('email', $normalizedEmail)
                ->first();

            if ($existingUser !== null) {
                $existingUser->forceFill([
                    'google_id' => $googleId,
                    'avatar' => is_string($avatar) ? $avatar : $existingUser->avatar,
                    'email_verified_at' => $existingUser->email_verified_at ?? now(),
                ])->save();

                return $existingUser;
            }

            $newUser = User::query()->create([
                'name' => $displayName,
                'email' => $normalizedEmail,
                'google_id' => $googleId,
                'avatar' => is_string($avatar) ? $avatar : null,
                'email_verified_at' => now(),
                'password' => Str::password(32),
            ]);

            $this->createTeam->handle($newUser, $newUser->name."'s Team", isPersonal: true);

            return $newUser;
        });

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('kbuser.home'));
    }

    private function resolveGoogleRedirectUri(): string
    {
        $configured = (string) config('services.google.redirect', '/auth/google/callback');

        if (Str::startsWith($configured, ['http://', 'https://'])) {
            return $configured;
        }

        return url($configured);
    }
}
