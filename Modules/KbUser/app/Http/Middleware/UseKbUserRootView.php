<?php

namespace Modules\KbUser\Http\Middleware;

use App\Models\SiteVisit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class UseKbUserRootView
{
    private const VISIT_WINDOW_MINUTES = 30;

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::setRootView('kbuser::kb-user');

        $response = $next($request);

        if ($this->shouldRecordVisit($request, $response)) {
            try {
                $this->recordVisit($request);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $response;
    }

    private function recordVisit(Request $request): void
    {
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;
        $ipAddress = $request->ip();
        $userAgent = Str::limit((string) $request->userAgent(), 500, '') ?: null;
        $cutoff = now()->subMinutes(self::VISIT_WINDOW_MINUTES);

        $recentVisit = SiteVisit::query()
            ->where('updated_at', '>=', $cutoff)
            ->where(function ($query) use ($sessionId, $ipAddress, $userAgent): void {
                if ($sessionId !== null && $sessionId !== '') {
                    $query->where('session_id', $sessionId);
                }

                if ($ipAddress !== null) {
                    $query->orWhere(function ($ipQuery) use ($ipAddress, $userAgent): void {
                        $ipQuery->where('ip_address', $ipAddress)
                            ->where('user_agent', $userAgent);
                    });
                }
            })
            ->latest('updated_at')
            ->first();

        if ($recentVisit !== null) {
            $recentVisit->forceFill([
                'session_id' => $sessionId ?? $recentVisit->session_id,
                'user_id' => $request->user()?->id ?? $recentVisit->user_id,
                'updated_at' => now(),
            ])->save();

            return;
        }

        SiteVisit::query()->create([
            'session_id' => $sessionId,
            'user_id' => $request->user()?->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'path' => '/'.ltrim($request->path(), '/'),
            'visited_date' => now()->toDateString(),
        ]);
    }

    private function shouldRecordVisit(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() >= 400) {
            return false;
        }

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return false;
        }

        return ! $request->is('auth/google/*');
    }
}
