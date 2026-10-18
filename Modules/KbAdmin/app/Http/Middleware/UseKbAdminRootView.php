<?php

namespace Modules\KbAdmin\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class UseKbAdminRootView
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::setRootView('kbadmin::kb-admin');

        return $next($request);
    }
}
