<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use Illuminate\Http\JsonResponse;
use Modules\KbAdmin\Support\AdminDashboardStats;

class NotificationController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): JsonResponse
    {
        return response()->json($dashboardStats->notifications());
    }

    public function markAllChatsRead(AdminDashboardStats $dashboardStats): JsonResponse
    {
        ChatMessage::query()
            ->where('sender', ChatMessage::SENDER_VISITOR)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json($dashboardStats->notifications());
    }
}
