<?php

namespace Modules\KbAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAuthenticated
{
    public const LAST_ACTIVE_SESSION_KEY = 'admin_last_active_at';

    public const DEFAULT_TIMEOUT_MINUTES = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user === null || ! $user->is_admin) {
            return $this->unauthenticatedResponse($request);
        }

        $timeoutMinutes = max(1, (int) config('kbadmin.session_timeout_minutes', self::DEFAULT_TIMEOUT_MINUTES));
        $timeoutSeconds = $timeoutMinutes * 60;
        $nowTimestamp = now()->timestamp;
        $lastActiveAt = $request->session()->get(self::LAST_ACTIVE_SESSION_KEY);

        if ($lastActiveAt !== null && ($nowTimestamp - (int) $lastActiveAt) >= $timeoutSeconds) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->isMethod('GET') && (! $request->expectsJson() || $request->header('X-Inertia'))) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                abort(401);
            }

            $expiredMessage = "Phiên đăng nhập quản trị đã hết hạn sau {$timeoutMinutes} phút không hoạt động. Vui lòng đăng nhập lại.";

            return to_route('admin.login')
                ->with('status', $expiredMessage)
                ->with('toast', $expiredMessage);
        }

        if ($lastActiveAt === null || ! $this->isBackgroundPollingRequest($request)) {
            $request->session()->put(self::LAST_ACTIVE_SESSION_KEY, $nowTimestamp);
        }

        return $next($request);
    }

    private function unauthenticatedResponse(Request $request): Response
    {
        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            abort(401);
        }

        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return to_route('admin.login');
    }

    private function isBackgroundPollingRequest(Request $request): bool
    {
        return $request->header('X-Background-Poll') === '1';
    }
}
