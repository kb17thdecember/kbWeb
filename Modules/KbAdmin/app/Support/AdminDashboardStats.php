<?php

namespace Modules\KbAdmin\Support;

use App\Models\Category;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Contact;
use App\Models\FeaturedProject;
use App\Models\Product;
use App\Models\ProjectReport;
use App\Models\SiteVisit;
use Illuminate\Support\Str;
use Throwable;

class AdminDashboardStats
{
    /**
     * @return array{
     *     stats: list<array{label: string, count: int, description: string}>,
     *     openRequestsCount: int,
     *     chatUnreadCount: int,
     *     supportProjectsCount: int
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
            'supportProjectsCount' => ProjectReport::query()
                ->where('project_status', '3')
                ->count(),
        ];
    }

    /**
     * @return array{
     *     openRequestsCount: int,
     *     pendingRequestsCount: int,
     *     chatUnreadCount: int,
     *     supportProjectsCount: int,
     *     totalCount: int,
     *     items: list<array{
     *         id: string,
     *         type: string,
     *         title: string,
     *         subtitle: string,
     *         badge: string,
     *         href: string,
     *         created_at: string|null
     *     }>
     * }
     */
    public function notifications(): array
    {
        try {
            $openRequestsCount = Contact::query()
                ->where('status', '!=', 'resolved')
                ->count();
            $pendingRequestsCount = Contact::query()
                ->where('status', 'pending')
                ->count();
            $chatUnreadCount = ChatMessage::query()
                ->where('sender', ChatMessage::SENDER_VISITOR)
                ->whereNull('read_at')
                ->count();
            $supportProjectsCount = ProjectReport::query()
                ->where('project_status', '3')
                ->count();

            $items = [];

            $contacts = Contact::query()
                ->where('status', '!=', 'resolved')
                ->orderByDesc('created_at')
                ->limit(8)
                ->get();

            foreach ($contacts as $contact) {
                $items[] = [
                    'id' => 'contact-'.$contact->id,
                    'type' => 'consultation',
                    'title' => $contact->name,
                    'subtitle' => (string) ($contact->project_name ?: ($contact->category_id ?: Str::limit((string) $contact->message, 65))),
                    'badge' => $contact->status === 'pending' ? 'Chờ xử lý' : 'Đang tư vấn',
                    'href' => '/admin/consultations',
                    'created_at' => $contact->created_at?->toIso8601String(),
                ];
            }

            $conversations = ChatConversation::query()
                ->whereHas('messages', fn ($query) => $query
                    ->where('sender', ChatMessage::SENDER_VISITOR)
                    ->whereNull('read_at'))
                ->withCount([
                    'messages as unread_count' => fn ($query) => $query
                        ->where('sender', ChatMessage::SENDER_VISITOR)
                        ->whereNull('read_at'),
                ])
                ->with(['messages' => fn ($query) => $query->latest('id')->limit(1)])
                ->orderByDesc('last_message_at')
                ->limit(6)
                ->get();

            foreach ($conversations as $conversation) {
                $items[] = [
                    'id' => 'chat-'.$conversation->id,
                    'type' => 'chat',
                    'title' => (string) ($conversation->visitor_name ?: 'Khách truy cập #'.$conversation->id),
                    'subtitle' => Str::limit((string) ($conversation->messages->first()?->body ?? 'Tin nhắn mới từ khách hàng'), 65),
                    'badge' => $conversation->unread_count.' tin mới',
                    'href' => '/admin/chats',
                    'created_at' => ($conversation->last_message_at ?? $conversation->updated_at)?->toIso8601String(),
                ];
            }

            $supportProjects = ProjectReport::query()
                ->where('project_status', '3')
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get();

            foreach ($supportProjects as $report) {
                $items[] = [
                    'id' => 'project-'.$report->id,
                    'type' => 'project',
                    'title' => $report->project_name.' ('.$report->project_code.')',
                    'subtitle' => $report->project_area.' · '.Str::limit((string) $report->project_update, 55),
                    'badge' => 'Cần hỗ trợ',
                    'href' => '/admin/project-reports',
                    'created_at' => $report->updated_at?->toIso8601String(),
                ];
            }

            usort(
                $items,
                fn (array $first, array $second): int => strcmp((string) ($second['created_at'] ?? ''), (string) ($first['created_at'] ?? '')),
            );

            return [
                'openRequestsCount' => $openRequestsCount,
                'pendingRequestsCount' => $pendingRequestsCount,
                'chatUnreadCount' => $chatUnreadCount,
                'supportProjectsCount' => $supportProjectsCount,
                'totalCount' => $openRequestsCount + $chatUnreadCount + $supportProjectsCount,
                'items' => array_slice($items, 0, 12),
            ];
        } catch (Throwable) {
            return [
                'openRequestsCount' => 0,
                'pendingRequestsCount' => 0,
                'chatUnreadCount' => 0,
                'supportProjectsCount' => 0,
                'totalCount' => 0,
                'items' => [],
            ];
        }
    }
}
