<?php

namespace Modules\KbUser\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class UseKbUserRootView
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::setRootView('kbuser::kb-user');

        return $next($request);
    }
}
