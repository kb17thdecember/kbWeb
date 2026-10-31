<?php

namespace Modules\KbAdmin\Support;

use App\Models\Category;
use App\Models\ChatMessage;
use App\Models\Contact;
use App\Models\FeaturedProject;
use App\Models\Product;
use App\Models\SiteVisit;

class AdminDashboardStats
{
    /**
     * @return array{
     *     stats: list<array{label: string, count: int, description: string}>,
     *     openRequestsCount: int,
     *     chatUnreadCount: int
     * }
     */
    public function summary(): array
    {
        $openRequestsCount = Contact::query()
            ->where('status', '!=', 'resolved')
            ->count();
        $totalVisitsCount = SiteVisit::query()->count();
        $todayVisitsCount = SiteVisit::query()
            ->whereDate('visited_date', now()->toDateString())
            ->count();

        return [
            'stats' => [
                ['label' => 'Sản phẩm', 'count' => Product::query()->count(), 'description' => 'Tổng số mặt hàng'],
                ['label' => 'Dự án tiêu biểu', 'count' => FeaturedProject::query()->count(), 'description' => 'Hồ sơ dự án'],
                ['label' => 'Danh mục', 'count' => Category::query()->whereNull('parent_id')->count(), 'description' => 'Nhóm chính'],
                ['label' => 'Yêu cầu cần xử lý', 'count' => $openRequestsCount, 'description' => 'Chưa hoàn tất'],
                ['label' => 'Lượt truy cập', 'count' => $totalVisitsCount, 'description' => "Hôm nay: {$todayVisitsCount} lượt"],
            ],
            'openRequestsCount' => $openRequestsCount,
            'chatUnreadCount' => ChatMessage::query()
                ->where('sender', ChatMessage::SENDER_VISITOR)
                ->whereNull('read_at')
                ->count(),
        ];
    }
}
