<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Contact;
use App\Models\FeaturedProject;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ConsultationController extends Controller
{
    public function index(): Response
    {
        $openRequestsCount = Contact::query()
            ->where('status', '!=', 'resolved')
            ->count();

        return Inertia::render('contacts/Index', [
            'contacts' => Contact::query()
                ->orderByDesc('created_at')
                ->get(),
            'openRequestsCount' => $openRequestsCount,
            'stats' => [
                ['label' => 'Sản phẩm', 'count' => Product::query()->count(), 'description' => 'Tổng số mặt hàng'],
                ['label' => 'Dự án tiêu biểu', 'count' => FeaturedProject::query()->count(), 'description' => 'Hồ sơ dự án'],
                ['label' => 'Danh mục', 'count' => Category::query()->whereNull('parent_id')->count(), 'description' => 'Nhóm chính'],
                ['label' => 'Yêu cầu cần xử lý', 'count' => $openRequestsCount, 'description' => 'Chưa hoàn tất'],
            ],
        ]);
    }

    public function updateStatus(Request $request, Contact $contact): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'processing', 'resolved'])],
        ]);

        $contact->update($validated);

        return to_route('admin.consultations.index');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return to_route('admin.consultations.index');
    }
}
