<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\AdminLoginOtp;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuthController extends Controller
{
    private const OTP_SESSION_KEY = 'admin_login_otp';

    private const OTP_VALID_MINUTES = 5;

    private const OTP_MAX_ATTEMPTS = 5;

    public function showLogin(): Response
    {
        return Inertia::render('auth/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Bạn thử quá nhiều lần. Vui lòng thử lại sau '.RateLimiter::availableIn($throttleKey).' giây.',
            ]);
        }

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! $user->is_admin || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không chính xác.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $this->sendOtp($request, $user);

        return to_route('admin.login.otp');
    }

    public function showOtp(Request $request): Response|RedirectResponse
    {
        $pending = $request->session()->get(self::OTP_SESSION_KEY);

        if ($pending === null) {
            return to_route('admin.login');
        }

        $user = User::query()->find($pending['user_id']);

        return Inertia::render('auth/Otp', [
            'email' => $user?->email,
            'validForMinutes' => self::OTP_VALID_MINUTES,
            'status' => $request->session()->get('status'),
        ]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'digits:6'],
        ]);

        $pending = $request->session()->get(self::OTP_SESSION_KEY);

        if ($pending === null) {
            return to_route('admin.login');
        }

        if (now()->timestamp > $pending['expires_at'] || $pending['attempts'] >= self::OTP_MAX_ATTEMPTS) {
            $request->session()->forget(self::OTP_SESSION_KEY);

            throw ValidationException::withMessages([
                'code' => 'Mã xác thực đã hết hạn hoặc nhập sai quá nhiều lần. Vui lòng đăng nhập lại.',
            ]);
        }

        if (! Hash::check($request->string('code')->toString(), $pending['code_hash'])) {
            $pending['attempts']++;
            $request->session()->put(self::OTP_SESSION_KEY, $pending);

            throw ValidationException::withMessages([
                'code' => 'Mã xác thực không chính xác.',
            ]);
        }

        $user = User::query()->find($pending['user_id']);

        if ($user === null || ! $user->is_admin) {
            $request->session()->forget(self::OTP_SESSION_KEY);

            return to_route('admin.login');
        }

        $request->session()->forget(self::OTP_SESSION_KEY);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard.index'));
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $pending = $request->session()->get(self::OTP_SESSION_KEY);
        $user = $pending === null ? null : User::query()->find($pending['user_id']);

        if ($user === null) {
            return to_route('admin.login');
        }

        $throttleKey = 'admin-otp-resend|'.$user->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            throw ValidationException::withMessages([
                'code' => 'Bạn gửi lại mã quá nhiều lần. Vui lòng thử lại sau '.RateLimiter::availableIn($throttleKey).' giây.',
            ]);
        }

        RateLimiter::hit($throttleKey, 600);
        $this->sendOtp($request, $user);

        return to_route('admin.login.otp')->with('status', 'Đã gửi lại mã xác thực.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('admin.login');
    }

    private function sendOtp(Request $request, User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $request->session()->put(self::OTP_SESSION_KEY, [
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::OTP_VALID_MINUTES)->timestamp,
            'attempts' => 0,
        ]);

        Mail::to($user->email)->send(new AdminLoginOtp($code, self::OTP_VALID_MINUTES));
    }
}
