<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;

class DashboardController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        $now = CarbonImmutable::now();
        $firstDay = $now->startOfDay()->subDays(6);
        $requestsByDate = Contact::query()
            ->whereBetween('created_at', [$firstDay, $now])
            ->selectRaw('DATE(created_at) as request_date, COUNT(*) as request_count')
            ->groupByRaw('DATE(created_at)')
            ->pluck('request_count', 'request_date');

        $weekdayLabels = [
            1 => 'T2',
            2 => 'T3',
            3 => 'T4',
            4 => 'T5',
            5 => 'T6',
            6 => 'T7',
            7 => 'CN',
        ];
        $recentRequests = [];

        for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
            $date = $firstDay->addDays($dayOffset);
            $recentRequests[] = [
                'label' => $weekdayLabels[$date->dayOfWeekIso],
                'count' => (int) ($requestsByDate[$date->toDateString()] ?? 0),
            ];
        }

        $requestStatusCounts = Contact::query()
            ->select('status')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return Inertia::render('Dashboard', [
            ...$dashboardStats->summary(),
            'recentRequests' => $recentRequests,
            'requestStatuses' => [
                'pending' => (int) ($requestStatusCounts['pending'] ?? 0),
                'processing' => (int) ($requestStatusCounts['processing'] ?? 0),
                'resolved' => (int) ($requestStatusCounts['resolved'] ?? 0),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('kbadmin::create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request) {}

    /**
     * Show the specified resource.
     */
    public function show($id)
    {
        return view('kbadmin::show');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        return view('kbadmin::edit');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id) {}

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id) {}
}
