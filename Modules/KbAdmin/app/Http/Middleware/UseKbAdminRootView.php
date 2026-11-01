<?php

namespace Modules\KbAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Modules\KbAdmin\Support\AdminDashboardStats;
use Symfony\Component\HttpFoundation\Response;

class UseKbAdminRootView
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::setRootView('kbadmin::kb-admin');
        Inertia::share('adminSessionTimeoutMinutes', fn () => max(
            1,
            (int) config('kbadmin.session_timeout_minutes', EnsureAdminAuthenticated::DEFAULT_TIMEOUT_MINUTES),
        ));
        Inertia::share('adminNotifications', fn () => app(AdminDashboardStats::class)->notifications());

        return $next($request);
    }
}
