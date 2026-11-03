<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SiteVisit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;

class SiteVisitController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        SiteVisit::pruneOldVisits();

        $todayString = now()->toDateString();

        $visits = SiteVisit::query()
            ->with('user:id,name,email,phone')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(SiteVisit::MAX_RECENT_VISITS)
            ->get()
            ->map(function (SiteVisit $visit): array {
                $deviceInfo = $visit->deviceInfo();

                return [
                    'id' => $visit->id,
                    'session_id' => $visit->session_id,
                    'ip_address' => $visit->ip_address,
                    'user_agent' => $visit->user_agent,
                    'path' => $visit->path ?: '/',
                    'page_label' => SiteVisit::formatPageLabel($visit->path),
                    'visited_date' => $visit->visited_date,
                    'created_at' => $visit->created_at?->toIso8601String(),
                    'updated_at' => $visit->updated_at?->toIso8601String(),
                    'device_type' => $deviceInfo['device_type'],
                    'device_label' => $deviceInfo['device_label'],
                    'device_model' => $deviceInfo['device_model'],
                    'os' => $deviceInfo['os'],
                    'browser' => $deviceInfo['browser'],
                    'device_summary' => $deviceInfo['device_summary'],
                    'user' => $visit->user ? [
                        'id' => $visit->user->id,
                        'name' => $visit->user->name,
                        'email' => $visit->user->email,
                        'phone' => $visit->user->phone,
                    ] : null,
                ];
            })
            ->values();

        $visitSummary = [
            'total' => $visits->count(),
            'maxLimit' => SiteVisit::MAX_RECENT_VISITS,
            'today' => $visits->where('visited_date', $todayString)->count(),
            'desktop' => $visits->where('device_type', 'desktop')->count(),
            'mobile' => $visits->where('device_type', 'mobile')->count(),
            'tablet' => $visits->where('device_type', 'tablet')->count(),
            'bot' => $visits->where('device_type', 'bot')->count(),
            'authenticated' => $visits->whereNotNull('user')->count(),
        ];

        return Inertia::render('visits/Index', [
            'visits' => $visits->all(),
            'visitSummary' => $visitSummary,
            ...$dashboardStats->summary(),
        ]);
    }

    public function destroy(SiteVisit $siteVisit): RedirectResponse
    {
        $siteVisit->delete();

        return to_route('admin.visits.index')
            ->with('toast', 'Đã xóa lượt truy cập.');
    }

    public function clear(): RedirectResponse
    {
        SiteVisit::query()->delete();

        return to_route('admin.visits.index')
            ->with('toast', 'Đã xóa toàn bộ lịch sử lượt truy cập.');
    }
}
