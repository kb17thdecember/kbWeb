<?php

namespace Modules\KbAdmin\Support;

use App\Models\Article;
use App\Models\Category;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Contact;
use App\Models\FeaturedProject;
use App\Models\Product;
use App\Models\ProjectReport;
use App\Models\SiteVisit;
use App\Models\User;
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

            usort(
                $items,
                fn (array $first, array $second): int => strcmp((string) ($second['created_at'] ?? ''), (string) ($first['created_at'] ?? '')),
            );

            return [
                'openRequestsCount' => $openRequestsCount,
                'pendingRequestsCount' => $pendingRequestsCount,
                'chatUnreadCount' => $chatUnreadCount,
                'supportProjectsCount' => $supportProjectsCount,
                'totalCount' => $openRequestsCount + $chatUnreadCount,
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

    /**
     * @return array{
     *     query: string,
     *     total: int,
     *     items: list<array{
     *         id: string,
     *         type: string,
     *         groupLabel: string,
     *         title: string,
     *         subtitle: string,
     *         badge: string,
     *         href: string,
     *         keyword: string
     *     }>
     * }
     */
    public function globalSearch(string $rawQuery): array
    {
        $query = trim($rawQuery);
        if ($query === '') {
            return [
                'query' => '',
                'total' => 0,
                'items' => [],
            ];
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $query).'%';
        $items = [];

        try {
            $products = Product::query()
                ->with(['category:id,name', 'images'])
                ->where(function ($builder) use ($like): void {
                    $builder->where('name', 'like', $like)
                        ->orWhere('product_title', 'like', $like)
                        ->orWhere('description', 'like', $like);
                })
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($products as $product) {
                $firstImage = $product->image[0] ?? null;
                $items[] = [
                    'id' => 'product-'.$product->id,
                    'type' => 'product',
                    'groupLabel' => 'Sản phẩm',
                    'title' => (string) ($product->product_title ?: $product->name),
                    'subtitle' => (string) ($product->category?->name ?: 'Sản phẩm'),
                    'badge' => $product->price ? number_format((float) $product->price, 0, ',', '.').'₫' : 'Liên hệ',
                    'image' => $firstImage,
                    'shortCode' => (string) ($product->category?->name ?: 'SP'),
                    'href' => '/admin/products?search='.rawurlencode((string) $product->name).'#workspace',
                    'keyword' => (string) $product->name,
                ];
            }

            $categories = Category::query()
                ->with('images')
                ->where(function ($builder) use ($like): void {
                    $builder->where('name', 'like', $like)
                        ->orWhere('description', 'like', $like);
                })
                ->orderByDesc('id')
                ->limit(4)
                ->get();

            foreach ($categories as $category) {
                $items[] = [
                    'id' => 'category-'.$category->id,
                    'type' => 'category',
                    'groupLabel' => 'Danh mục',
                    'title' => (string) $category->name,
                    'subtitle' => Str::limit((string) ($category->description ?: 'Danh mục sản phẩm'), 50),
                    'badge' => $category->parent_id === null ? 'Danh mục cha' : 'Danh mục con',
                    'image' => $category->image_url,
                    'shortCode' => 'DM',
                    'href' => '/admin/categories?search='.rawurlencode((string) $category->name).'#workspace',
                    'keyword' => (string) $category->name,
                ];
            }

            $reports = ProjectReport::query()
                ->where(function ($builder) use ($like): void {
                    $builder->where('project_name', 'like', $like)
                        ->orWhere('project_code', 'like', $like)
                        ->orWhere('project_area', 'like', $like)
                        ->orWhere('developer', 'like', $like)
                        ->orWhere('contact_person', 'like', $like)
                        ->orWhere('participating_products', 'like', $like);
                })
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($reports as $report) {
                $statusLabel = match ((string) $report->project_status) {
                    '2' => 'Dự án đang nóng',
                    '3' => 'Cần hỗ trợ',
                    default => 'Đang triển khai',
                };
                $items[] = [
                    'id' => 'report-'.$report->id,
                    'type' => 'project_report',
                    'groupLabel' => 'Báo cáo dự án',
                    'title' => (string) $report->project_name,
                    'subtitle' => trim(($report->project_code ? $report->project_code.' · ' : '').($report->project_area ?? 'Báo cáo dự án')),
                    'badge' => $statusLabel,
                    'image' => null,
                    'shortCode' => (string) ($report->project_code ?: 'BC'),
                    'href' => '/admin/project-reports?search='.rawurlencode((string) ($report->project_code ?: $report->project_name)).'#workspace',
                    'keyword' => (string) ($report->project_code ?: $report->project_name),
                ];
            }

            $projects = FeaturedProject::query()
                ->with('images')
                ->where(function ($builder) use ($like): void {
                    $builder->where('name', 'like', $like)
                        ->orWhere('location', 'like', $like)
                        ->orWhere('category', 'like', $like)
                        ->orWhere('subcategory', 'like', $like);
                })
                ->orderByDesc('id')
                ->limit(4)
                ->get();

            foreach ($projects as $project) {
                $items[] = [
                    'id' => 'case-study-'.$project->id,
                    'type' => 'case_study',
                    'groupLabel' => 'Dự án tiêu biểu',
                    'title' => (string) $project->name,
                    'subtitle' => (string) ($project->location ?: ($project->category ?: 'Dự án tiêu biểu')),
                    'badge' => (string) ($project->category ?: 'Dự án'),
                    'image' => $project->image_url,
                    'shortCode' => 'DA',
                    'href' => '/admin/case-studies?search='.rawurlencode((string) $project->name).'#workspace',
                    'keyword' => (string) $project->name,
                ];
            }

            $contacts = Contact::query()
                ->where(function ($builder) use ($like): void {
                    $builder->where('name', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('project_name', 'like', $like)
                        ->orWhere('product', 'like', $like)
                        ->orWhere('message', 'like', $like);
                })
                ->orderByDesc('id')
                ->limit(5)
                ->get();

            foreach ($contacts as $contact) {
                $statusBadge = match ($contact->status) {
                    'resolved' => 'Đã xử lý',
                    'processing' => 'Đang tư vấn',
                    default => 'Chờ xử lý',
                };
                $items[] = [
                    'id' => 'contact-'.$contact->id,
                    'type' => 'consultation',
                    'groupLabel' => 'Yêu cầu tư vấn',
                    'title' => (string) $contact->name,
                    'subtitle' => trim(($contact->phone ? $contact->phone.' · ' : '').($contact->project_name ?: ($contact->category_id ?: 'Yêu cầu tư vấn'))),
                    'badge' => $statusBadge,
                    'image' => null,
                    'shortCode' => 'TV',
                    'href' => '/admin/consultations?search='.rawurlencode((string) $contact->name).'&contact_id='.$contact->id.'#workspace',
                    'keyword' => (string) $contact->name,
                ];
            }

            $customers = User::query()
                ->where('is_admin', false)
                ->where(function ($builder) use ($like): void {
                    $builder->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                })
                ->orderByDesc('id')
                ->limit(4)
                ->get();

            foreach ($customers as $customer) {
                $items[] = [
                    'id' => 'customer-'.$customer->id,
                    'type' => 'customer',
                    'groupLabel' => 'Khách hàng',
                    'title' => (string) $customer->name,
                    'subtitle' => trim(($customer->phone ? $customer->phone.' · ' : '').$customer->email),
                    'badge' => 'Khách hàng',
                    'image' => $customer->avatar,
                    'shortCode' => 'KH',
                    'href' => '/admin/customers?search='.rawurlencode((string) ($customer->phone ?: $customer->name)).'#workspace',
                    'keyword' => (string) ($customer->phone ?: $customer->name),
                ];
            }

            $articles = Article::query()
                ->with('images')
                ->where(function ($builder) use ($like): void {
                    $builder->where('title', 'like', $like)
                        ->orWhere('excerpt', 'like', $like);
                })
                ->orderByDesc('id')
                ->limit(4)
                ->get();

            foreach ($articles as $article) {
                $items[] = [
                    'id' => 'article-'.$article->id,
                    'type' => 'article',
                    'groupLabel' => 'Bài viết',
                    'title' => (string) $article->title,
                    'subtitle' => Str::limit((string) ($article->excerpt ?: 'Tin tức & Bài viết kỹ thuật'), 50),
                    'badge' => $article->is_published ? 'Đã xuất bản' : 'Bản nháp',
                    'image' => $article->image_url,
                    'shortCode' => 'BV',
                    'href' => '/admin/articles?search='.rawurlencode((string) $article->title).'#workspace',
                    'keyword' => (string) $article->title,
                ];
            }
        } catch (Throwable) {
            // Ignore partial search failures
        }

        return [
            'query' => $query,
            'total' => count($items),
            'items' => $items,
        ];
    }
}
