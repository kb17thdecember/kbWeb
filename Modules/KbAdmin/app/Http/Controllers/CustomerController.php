<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Actions\Teams\CreateTeam;
use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;

class CustomerController extends Controller
{
    public function index(AdminDashboardStats $dashboardStats): Response
    {
        $customers = User::query()
            ->where('is_admin', false)
            ->withCount(['contacts', 'wishlists', 'productReviews'])
            ->with([
                'contacts' => fn ($query) => $query->latest()->limit(10),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'avatar' => $customer->avatar,
                'login_type' => $customer->google_id ? 'google' : 'account',
                'email_verified_at' => $customer->email_verified_at?->toISOString(),
                'created_at' => $customer->created_at?->toISOString(),
                'contacts_count' => (int) $customer->contacts_count,
                'wishlists_count' => (int) $customer->wishlists_count,
                'product_reviews_count' => (int) $customer->product_reviews_count,
                'recent_contacts' => $customer->contacts->map(fn (Contact $contact): array => [
                    'id' => $contact->id,
                    'project_name' => $contact->project_name,
                    'category_id' => $contact->category_id,
                    'product' => $contact->product,
                    'message' => $contact->message,
                    'status' => $contact->status,
                    'created_at' => $contact->created_at?->toISOString(),
                ])->values()->all(),
            ]);

        return Inertia::render('customers/Index', [
            'customers' => $customers,
            ...$dashboardStats->summary(),
        ]);
    }

    public function store(Request $request, CreateTeam $createTeam): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email:rfc',
                'max:255',
                Rule::unique(User::class, 'email'),
            ],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s().-]{8,30}$/'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ], [
            'name.required' => 'Vui lòng nhập họ và tên khách hàng.',
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'email.unique' => 'Email này đã được sử dụng cho một tài khoản khác.',
            'phone.regex' => 'Số điện thoại không hợp lệ (gồm 8–30 ký tự số).',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
        ]);

        DB::transaction(function () use ($validated, $createTeam): void {
            $user = User::query()->create([
                'name' => trim($validated['name']),
                'email' => trim($validated['email']),
                'phone' => isset($validated['phone']) && trim($validated['phone']) !== '' ? trim($validated['phone']) : null,
                'password' => $validated['password'],
                'email_verified_at' => now(),
            ]);

            $createTeam->handle($user, $user->name."'s Team", isPersonal: true);
        });

        return to_route('admin.customers.index')
            ->with('toast', 'Đã thêm khách hàng mới thành công.');
    }

    public function update(Request $request, User $customer): RedirectResponse
    {
        abort_if($customer->is_admin, 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email:rfc',
                'max:255',
                Rule::unique(User::class, 'email')->ignore($customer->id),
            ],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\s().-]{8,30}$/'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
        ], [
            'name.required' => 'Vui lòng nhập họ và tên khách hàng.',
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'email.unique' => 'Email này đã được sử dụng cho một tài khoản khác.',
            'phone.regex' => 'Số điện thoại không hợp lệ (gồm 8–30 ký tự số).',
            'password.min' => 'Mật khẩu mới phải có ít nhất 8 ký tự.',
        ]);

        $attributes = [
            'name' => trim($validated['name']),
            'email' => trim($validated['email']),
            'phone' => isset($validated['phone']) && trim($validated['phone']) !== '' ? trim($validated['phone']) : null,
        ];

        if (! empty($validated['password'])) {
            $attributes['password'] = $validated['password'];
        }

        $customer->forceFill($attributes)->save();

        return to_route('admin.customers.index')
            ->with('toast', 'Đã cập nhật thông tin khách hàng thành công.');
    }

    public function destroy(User $customer): RedirectResponse
    {
        abort_if($customer->is_admin, 404);

        $customer->delete();

        return to_route('admin.customers.index')
            ->with('toast', 'Đã xóa tài khoản khách hàng thành công.');
    }
}
