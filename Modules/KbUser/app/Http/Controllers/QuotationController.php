<?php

namespace Modules\KbUser\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\KflexPrice;
use App\Models\Product;
use App\Models\QuotationDraft;
use App\Models\User;
use App\Support\QuotationFileImporter;
use Database\Seeders\PublicQuoteUserSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class QuotationController extends Controller
{
    public const SESSION_KEY = 'kbuser_quotation_auth_user_id';

    public const PUBLIC_QUOTE_EMAIL = 'publicquote@gmail.com';

    public function showLogin(Request $request): Response|\Symfony\Component\HttpFoundation\Response
    {
        if ($this->resolveAuthenticatedQuoteUser($request) !== null) {
            return to_route('kbuser.quotations.index')
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        return Inertia::render('quotations/Login', [
            'status' => $request->session()->get('status'),
            'recaptchaSiteKey' => (string) config('services.recaptcha.site_key'),
        ])->toResponse($request)->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'recaptcha_token' => ['required', 'string'],
        ], [
            'email.required' => 'Vui lòng nhập địa chỉ email.',
            'email.email' => 'Địa chỉ email không hợp lệ.',
            'password.required' => 'Vui lòng nhập mật khẩu.',
            'recaptcha_token.required' => 'Vui lòng tích xác nhận "Tôi không phải là người máy" (Google reCAPTCHA).',
        ]);

        if (! $this->verifyGoogleRecaptcha((string) $credentials['recaptcha_token'], $request->ip())) {
            throw ValidationException::withMessages([
                'recaptcha_token' => 'Xác thực Google reCAPTCHA không hợp lệ hoặc đã hết hạn. Vui lòng tích chọn lại.',
            ]);
        }

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
            return to_route('kbuser.quotations.login')
                ->header('X-Robots-Tag', 'noindex, nofollow');
        }

        Product::ensureOriginColumn();
        QuotationDraft::ensureTableExists();

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

        $quotationDrafts = QuotationDraft::query()
            ->latest('updated_at')
            ->limit(50)
            ->get();

        return Inertia::render('quotations/Index', [
            'aristonProducts' => $aristonProducts,
            'kflexPrices' => $kflexPrices,
            'kflexProductLines' => KflexPrice::PRODUCT_LINES,
            'kflexProductLineMeta' => KflexPrice::PRODUCT_LINE_META,
            'kflexTubeVariants' => KflexPrice::TUBE_VARIANTS,
            'kflexSheetVariants' => KflexPrice::SHEET_VARIANTS,
            'quotationDrafts' => $quotationDrafts,
            'quoteUser' => [
                'name' => $quoteUser->name,
                'email' => $quoteUser->email,
            ],
        ]);
    }

    public function listDrafts(Request $request): JsonResponse
    {
        $quoteUser = $this->resolveAuthenticatedQuoteUser($request);

        if ($quoteUser === null) {
            abort(403, 'Vui lòng đăng nhập tài khoản báo giá.');
        }

        QuotationDraft::ensureTableExists();

        return response()->json([
            'drafts' => QuotationDraft::query()->latest('updated_at')->limit(50)->get(),
        ]);
    }

    public function storeDraft(Request $request): RedirectResponse|JsonResponse
    {
        $quoteUser = $this->resolveAuthenticatedQuoteUser($request);

        if ($quoteUser === null) {
            abort(403, 'Vui lòng đăng nhập tài khoản báo giá.');
        }

        QuotationDraft::ensureTableExists();

        $rawPayload = $request->input('payload');
        if (is_string($rawPayload)) {
            $decodedPayload = json_decode($rawPayload, true);
            if (is_array($decodedPayload)) {
                $request->merge(['payload' => $decodedPayload]);
            }
        }

        $validated = $request->validate([
            'draft_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'quotation_type' => ['required', 'string', 'in:ariston,second'],
            'project_name' => ['nullable', 'string', 'max:255'],
            'recipient_company' => ['nullable', 'string', 'max:255'],
            'recipient_person' => ['nullable', 'string', 'max:255'],
            'items_count' => ['required', 'integer', 'min:0'],
            'grand_total' => ['required', 'numeric', 'min:0'],
            'payload' => ['required', 'array'],
        ]);

        $payload = $validated['payload'];
        $payload['quotationType'] = $validated['quotation_type'];
        $payload['activeQuotationType'] = $validated['quotation_type'];

        $draft = null;
        if (! empty($validated['draft_id'])) {
            $draft = QuotationDraft::query()->find((int) $validated['draft_id']);
        }

        $attributes = [
            'user_id' => $quoteUser->id,
            'title' => trim($validated['title']),
            'quotation_type' => $validated['quotation_type'],
            'project_name' => isset($validated['project_name']) ? trim((string) $validated['project_name']) : null,
            'recipient_company' => isset($validated['recipient_company']) ? trim((string) $validated['recipient_company']) : null,
            'recipient_person' => isset($validated['recipient_person']) ? trim((string) $validated['recipient_person']) : null,
            'items_count' => max(1, (int) $validated['items_count']),
            'grand_total' => max(0, (int) round((float) $validated['grand_total'])),
            'payload' => $payload,
        ];

        if ($draft !== null) {
            $draft->update($attributes);
            $message = 'Đã cập nhật bản nháp báo giá thành công.';
        } else {
            $draft = QuotationDraft::query()->create($attributes);
            $message = 'Đã lưu bản nháp báo giá mới thành công.';
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => $message,
                'draft' => $draft->fresh(),
                'drafts' => QuotationDraft::query()->latest('updated_at')->limit(50)->get(),
            ]);
        }

        return back()->with('toast', $message);
    }

    public function destroyDraft(Request $request, int|string $quotationDraft): RedirectResponse|JsonResponse
    {
        $quoteUser = $this->resolveAuthenticatedQuoteUser($request);

        if ($quoteUser === null) {
            abort(403, 'Vui lòng đăng nhập tài khoản báo giá.');
        }

        QuotationDraft::ensureTableExists();

        $draftId = (int) $quotationDraft;
        if ($draftId > 0) {
            QuotationDraft::query()->whereKey($draftId)->delete();
        }

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'deleted' => true,
                'id' => $draftId,
                'drafts' => QuotationDraft::query()->latest('updated_at')->limit(50)->get(),
                'message' => 'Đã xóa bản nháp báo giá.',
            ]);
        }

        return back()->with('toast', 'Đã xóa bản nháp báo giá.');
    }

    public function importFile(Request $request, QuotationFileImporter $importer): JsonResponse
    {
        $quoteUser = $this->resolveAuthenticatedQuoteUser($request);

        if ($quoteUser === null) {
            abort(403, 'Vui lòng đăng nhập tài khoản báo giá.');
        }

        $uploadedFile = $request->file('quotation_file') ?? $request->file('file');
        if ($uploadedFile === null) {
            return response()->json([
                'message' => 'Vui lòng chọn file báo giá cần nhập (.xlsx, .pdf, .json).',
            ], 422);
        }

        try {
            $payload = $importer->import($uploadedFile->getRealPath(), $uploadedFile->getClientOriginalName());

            return response()->json([
                'payload' => $payload,
                'message' => 'Đã đọc file báo giá thành công.',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage() ?: 'Không thể đọc file báo giá này.',
            ], 422);
        }
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

    private function verifyGoogleRecaptcha(string $token, ?string $ip = null): bool
    {
        if (trim($token) === '') {
            return false;
        }

        if (app()->environment('testing')) {
            return $token !== 'invalid-token';
        }

        $secretKey = trim((string) config('services.recaptcha.secret_key'));
        if ($secretKey === '') {
            return true;
        }

        try {
            $request = Http::asForm()->timeout(8);
            if (app()->environment('local')) {
                $request = $request->withoutVerifying();
            }

            $response = $request->post('https://www.google.com/recaptcha/api/siteverify', array_filter([
                'secret' => $secretKey,
                'response' => $token,
                'remoteip' => $ip,
            ]));

            if (! $response->successful()) {
                return false;
            }

            return (bool) $response->json('success', false);
        } catch (Throwable) {
            return false;
        }
    }
}
