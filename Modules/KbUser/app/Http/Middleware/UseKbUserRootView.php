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
                self::recordConfirmedVisit($request);
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $response;
    }

    public static function recordConfirmedVisit(Request $request, ?string $customPath = null): void
    {
        if (SiteVisit::isBotUserAgent($request->userAgent())) {
            return;
        }

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

        $rawPath = $customPath !== null && trim($customPath) !== ''
            ? $customPath
            : $request->path();
        $currentPath = '/'.ltrim($rawPath, '/');

        if ($recentVisit !== null) {
            $recentVisit->forceFill([
                'session_id' => $sessionId ?? $recentVisit->session_id,
                'user_id' => $request->user()?->id ?? $recentVisit->user_id,
                'path' => $currentPath,
                'updated_at' => now(),
            ])->save();

            SiteVisit::pruneOldVisits();

            return;
        }

        SiteVisit::query()->create([
            'session_id' => $sessionId,
            'user_id' => $request->user()?->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'path' => $currentPath,
            'visited_date' => now()->toDateString(),
        ]);

        SiteVisit::pruneOldVisits();
    }

    private function shouldRecordVisit(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $response->getStatusCode() >= 400) {
            return false;
        }

        if (($request->expectsJson() || $request->ajax()) && ! $request->header('X-Inertia')) {
            return false;
        }

        if ($this->isPrefetchOrPreviewRequest($request)) {
            return false;
        }

        if (! $request->header('X-Inertia')) {
            $secFetchMode = strtolower(trim((string) $request->header('Sec-Fetch-Mode', '')));
            if ($secFetchMode !== '' && $secFetchMode !== 'navigate') {
                return false;
            }

            $secFetchDest = strtolower(trim((string) $request->header('Sec-Fetch-Dest', '')));
            if ($secFetchDest !== '' && ! in_array($secFetchDest, ['document', 'empty'], true)) {
                return false;
            }
        }

        if (SiteVisit::isBotUserAgent($request->userAgent())) {
            return false;
        }

        return ! $request->is(
            'auth/google/*',
            'products/search',
            'chat/*',
            'my-account/overview',
            'site-visits/*',
        );
    }

    private function isPrefetchOrPreviewRequest(Request $request): bool
    {
        $purposeHeaders = [
            $request->header('Purpose'),
            $request->header('Sec-Purpose'),
            $request->header('X-Purpose'),
            $request->header('X-Moz'),
            $request->header('X-Preview'),
        ];

        foreach ($purposeHeaders as $headerValue) {
            $normalized = strtolower(trim((string) $headerValue));
            if ($normalized === '') {
                continue;
            }

            if (
                str_contains($normalized, 'prefetch')
                || str_contains($normalized, 'prerender')
                || str_contains($normalized, 'preview')
                || $normalized === '1'
            ) {
                return true;
            }
        }

        return false;
    }
}
