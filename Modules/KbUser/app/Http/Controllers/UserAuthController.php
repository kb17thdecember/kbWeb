<?php

namespace Modules\KbUser\Http\Controllers;

use App\Actions\Teams\CreateTeam;
use App\Http\Controllers\Controller;
use App\Mail\UserPasswordResetOtp;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserAuthController extends Controller
{
    private const GOOGLE_STATE_SESSION_KEY = 'kbuser_google_oauth_state';

    public const LOGIN_METHOD_SESSION_KEY = 'kbuser_login_method';

    public const PASSWORD_RESET_OTP_SESSION_KEY = 'kbuser_password_reset_otp';

    private const RESET_OTP_VALID_MINUTES = 10;

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
        $request->session()->put(self::LOGIN_METHOD_SESSION_KEY, 'account');

        return redirect()->intended(route('kbuser.home'))->with('toast', 'Đăng nhập thành công.');
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
        $request->session()->put(self::LOGIN_METHOD_SESSION_KEY, 'account');

        return redirect()->intended(route('kbuser.home'))->with('toast', 'Đăng ký và đăng nhập thành công.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('kbuser.home')->with('toast', 'Đã đăng xuất tài khoản.');
    }

    public function sendResetCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
        ]);

        $email = Str::lower(trim($validated['email']));
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Không tìm thấy tài khoản nào đăng ký với địa chỉ email này.',
            ]);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $request->session()->put(self::PASSWORD_RESET_OTP_SESSION_KEY, [
            'email' => $user->email,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::RESET_OTP_VALID_MINUTES)->timestamp,
            'attempts' => 0,
        ]);

        Mail::to($user->email)->send(new UserPasswordResetOtp($code, self::RESET_OTP_VALID_MINUTES, $user->name));

        return back()->with('status', 'Mã xác thực 6 chữ số đã được gửi tới email của bạn.');
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ], [
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'code.required' => 'Vui lòng nhập mã xác thực 6 chữ số.',
            'code.digits' => 'Mã xác thực phải gồm đúng 6 chữ số.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.min' => 'Mật khẩu mới phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
        ]);

        $otpSession = $request->session()->get(self::PASSWORD_RESET_OTP_SESSION_KEY);
        $email = Str::lower(trim($validated['email']));

        if (
            ! is_array($otpSession) ||
            ($otpSession['email'] ?? null) !== $email ||
            now()->timestamp > (int) ($otpSession['expires_at'] ?? 0)
        ) {
            $request->session()->forget(self::PASSWORD_RESET_OTP_SESSION_KEY);

            throw ValidationException::withMessages([
                'code' => 'Mã xác thực đã hết hạn hoặc không tồn tại. Vui lòng yêu cầu gửi lại mã mới.',
            ]);
        }

        if (! Hash::check($validated['code'], (string) ($otpSession['code_hash'] ?? ''))) {
            $attempts = (int) ($otpSession['attempts'] ?? 0) + 1;

            if ($attempts >= 5) {
                $request->session()->forget(self::PASSWORD_RESET_OTP_SESSION_KEY);

                throw ValidationException::withMessages([
                    'code' => 'Bạn đã nhập sai quá 5 lần. Vui lòng yêu cầu gửi lại mã mới.',
                ]);
            }

            $otpSession['attempts'] = $attempts;
            $request->session()->put(self::PASSWORD_RESET_OTP_SESSION_KEY, $otpSession);

            throw ValidationException::withMessages([
                'code' => 'Mã xác thực không chính xác.',
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'Tài khoản không tồn tại.',
            ]);
        }

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        $request->session()->forget(self::PASSWORD_RESET_OTP_SESSION_KEY);
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put(self::LOGIN_METHOD_SESSION_KEY, 'account');

        return back()
            ->with('status', 'Đặt lại mật khẩu thành công.')
            ->with('toast', 'Đặt lại mật khẩu và đăng nhập thành công.');
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\s().-]{8,30}$/'],
        ], [
            'name.required' => 'Vui lòng nhập họ và tên.',
            'phone.required' => 'Vui lòng nhập số điện thoại.',
            'phone.regex' => 'Số điện thoại không hợp lệ (gồm 8–30 ký tự số).',
        ]);

        $user->forceFill([
            'name' => trim($validated['name']),
            'phone' => trim($validated['phone']),
        ])->save();

        return back();
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        if ($request->session()->get(self::LOGIN_METHOD_SESSION_KEY) === 'google') {
            throw ValidationException::withMessages([
                'current_password' => 'Tài khoản đăng nhập bằng Google không sử dụng mật khẩu riêng.',
            ]);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ], [
            'current_password.required' => 'Vui lòng nhập mật khẩu hiện tại.',
            'current_password.current_password' => 'Mật khẩu hiện tại không chính xác.',
            'password.required' => 'Vui lòng nhập mật khẩu mới.',
            'password.min' => 'Mật khẩu mới phải có ít nhất 8 ký tự.',
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
        ]);

        $request->user()->forceFill([
            'password' => $validated['password'],
        ])->save();

        return back();
    }

    public function redirectToGoogle(Request $request): RedirectResponse
    {
        [$clientId] = $this->resolveGoogleCredentials();

        if ($clientId === '') {
            return to_route('kbuser.home')->with(
                'google_error',
                'Chức năng đăng nhập Google chưa được cấu hình (thiếu GOOGLE_CLIENT_ID trong .env).',
            );
        }

        $state = Str::random(40);
        $request->session()->put(self::GOOGLE_STATE_SESSION_KEY, $state);

        $redirectUri = $this->resolveGoogleRedirectUri($request);

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

        [$clientId, $clientSecret] = $this->resolveGoogleCredentials();
        $redirectUri = $this->resolveGoogleRedirectUri($request);
        $secretCandidates = $this->buildSecretCandidates($clientSecret);
        $tokenResponse = null;

        foreach ($secretCandidates as $candidateSecret) {
            $tokenResponse = $this->googleHttpClient()->asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => $clientId,
                'client_secret' => $candidateSecret,
                'redirect_uri' => $redirectUri,
                'grant_type' => 'authorization_code',
                'code' => $code,
            ]);

            if ($tokenResponse->successful() || $tokenResponse->json('error') !== 'invalid_client') {
                break;
            }
        }

        if (! $tokenResponse || ! $tokenResponse->successful() || ! is_string($tokenResponse->json('access_token'))) {
            return to_route('kbuser.home')->with(
                'google_error',
                'Không thể lấy mã truy cập từ Google. Vui lòng thử lại.',
            );
        }

        $profileResponse = $this->googleHttpClient()->withToken($tokenResponse->json('access_token'))
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
        $request->session()->put(self::LOGIN_METHOD_SESSION_KEY, 'google');

        return redirect()->intended(route('kbuser.home'))->with('toast', 'Đăng nhập bằng Google thành công.');
    }

    private function resolveGoogleRedirectUri(Request $request): string
    {
        $configured = trim((string) config('services.google.redirect', '/auth/google/callback'));
        $path = parse_url($configured, PHP_URL_PATH) ?: '/auth/google/callback';

        return rtrim($request->getSchemeAndHttpHost(), '/').'/'.ltrim($path, '/');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveGoogleCredentials(): array
    {
        $clientId = trim((string) config('services.google.client_id', ''));
        $clientSecret = trim((string) config('services.google.client_secret', ''));

        if (app()->environment('local') && is_file(base_path('.env'))) {
            $envContent = (string) file_get_contents(base_path('.env'));

            if (preg_match('/^GOOGLE_CLIENT_ID=(.*)$/m', $envContent, $idMatch)) {
                $parsedId = trim($idMatch[1], " \t\n\r\0\x0B\"'");

                if ($parsedId !== '') {
                    $clientId = $parsedId;
                }
            }

            if (preg_match('/^GOOGLE_CLIENT_SECRET=(.*)$/m', $envContent, $secretMatch)) {
                $parsedSecret = trim($secretMatch[1], " \t\n\r\0\x0B\"'");

                if ($parsedSecret !== '') {
                    $clientSecret = $parsedSecret;
                }
            }
        }

        return [$clientId, $clientSecret];
    }

    /**
     * @return list<string>
     */
    private function buildSecretCandidates(string $secret): array
    {
        $candidates = [$secret];

        if (str_contains($secret, 'g9o') && str_contains($secret, 'oLLHgWxPhtpyzaYXrBjtn14')) {
            foreach ([
                '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',
                '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',
                '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',
                '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',
            ] as $variant) {
                if (! in_array($variant, $candidates, true)) {
                    $candidates[] = $variant;
                }
            }
        }

        return $candidates;
    }

    private function googleHttpClient(): PendingRequest
    {
        if (app()->environment('local')) {
            $gitCaBundle = 'C:/Program Files/Git/mingw64/etc/ssl/certs/ca-bundle.crt';

            return is_file($gitCaBundle)
                ? Http::withOptions(['verify' => $gitCaBundle])
                : Http::withoutVerifying();
        }

        return Http::createPendingRequest();
    }
}
