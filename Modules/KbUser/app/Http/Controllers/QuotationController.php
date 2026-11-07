<?php

namespace Modules\KbUser\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\KflexPrice;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\PublicQuoteUserSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuotationController extends Controller
{
    public const SESSION_KEY = 'kbuser_quotation_auth_user_id';

    public const PUBLIC_QUOTE_EMAIL = 'publicquote@gmail.com';

    public function showLogin(Request $request): Response|RedirectResponse
    {
        if ($this->resolveAuthenticatedQuoteUser($request) !== null) {
            return to_route('kbuser.quotations.index');
        }

        return Inertia::render('quotations/Login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ], [
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
        ]);

        $email = Str::lower(trim($credentials['email']));
        $throttleKey = 'kbuser-quotation-login|'.$email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Bạn đã thử đăng nhập quá nhiều lần. Vui lòng thử lại sau '.RateLimiter::availableIn($throttleKey).' giây.',
            ]);
        }

        if ($email === self::PUBLIC_QUOTE_EMAIL && User::query()->where('email', self::PUBLIC_QUOTE_EMAIL)->doesntExist()) {
            app(PublicQuoteUserSeeder::class)->run();
        }

        $user = User::query()->where('email', $email)->first();

        $isAllowedAccount = $user !== null && (
            Str::lower($user->email) === self::PUBLIC_QUOTE_EMAIL || $user->is_admin
        );

        if (! $isAllowedAccount || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Email hoặc mật khẩu không chính xác.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->put(self::SESSION_KEY, $user->id);

        return to_route('kbuser.quotations.index')
            ->with('toast', 'Đăng nhập trang tạo báo giá thành công.');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return to_route('kbuser.quotations.login')
            ->with('toast', 'Đã đăng xuất khỏi trang tạo báo giá.');
    }

    public function index(Request $request): Response|RedirectResponse
    {
        $quoteUser = $this->resolveAuthenticatedQuoteUser($request);

        if ($quoteUser === null) {
            return to_route('kbuser.quotations.login');
        }

        Product::ensureOriginColumn();

        $aristonCategory = Category::query()
            ->whereNull('parent_id')
            ->where('name', 'like', '%Ariston%')
            ->first();

        $aristonCategoryIds = [];

        if ($aristonCategory) {
            $childIds = Category::query()
                ->where('parent_id', $aristonCategory->id)
                ->pluck('id')
                ->all();
            $aristonCategoryIds = [$aristonCategory->id, ...$childIds];
        }

        $aristonProducts = Product::query()
            ->with(['category:id,name', 'subcategory:id,name', 'images'])
            ->when(
                count($aristonCategoryIds) > 0,
                fn ($query) => $query->whereIn('category_id', $aristonCategoryIds)
                    ->orWhereIn('subcategory_id', $aristonCategoryIds),
            )
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();

        $kflexPrices = KflexPrice::query()
            ->orderBy('product_line')
            ->orderBy('form_type')
            ->orderBy('variant_type')
            ->orderBy('id')
            ->get()
            ->sortBy([
                ['product_line', 'asc'],
                ['form_type', 'asc'],
                ['variant_type', 'asc'],
                fn (KflexPrice $a, KflexPrice $b): int => (int) preg_replace('/[^0-9]/', '', $a->thickness) <=> (int) preg_replace('/[^0-9]/', '', $b->thickness),
                fn (KflexPrice $a, KflexPrice $b): int => (int) preg_replace('/[^0-9]/', '', (string) $a->diameter) <=> (int) preg_replace('/[^0-9]/', '', (string) $b->diameter),
                ['id', 'asc'],
            ])
            ->values();

        return Inertia::render('quotations/Index', [
            'aristonProducts' => $aristonProducts,
            'kflexPrices' => $kflexPrices,
            'kflexProductLines' => KflexPrice::PRODUCT_LINES,
            'kflexProductLineMeta' => KflexPrice::PRODUCT_LINE_META,
            'kflexTubeVariants' => KflexPrice::TUBE_VARIANTS,
            'kflexSheetVariants' => KflexPrice::SHEET_VARIANTS,
            'quoteUser' => [
                'name' => $quoteUser->name,
                'email' => $quoteUser->email,
            ],
        ]);
    }

    private function resolveAuthenticatedQuoteUser(Request $request): ?User
    {
        $userId = $request->session()->get(self::SESSION_KEY);

        if (! $userId) {
            return null;
        }

        $user = User::query()->find($userId);

        if ($user === null || (Str::lower($user->email) !== self::PUBLIC_QUOTE_EMAIL && ! $user->is_admin)) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $user;
    }
}
