<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Contact;
use App\Models\KflexPrice;
use App\Models\Product;
use App\Models\QuotationDraft;
use App\Support\QuotationFileImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;
use Throwable;

class QuotationController extends Controller
{
    /**
     * Display the quotation builder page.
     */
    public function index(Request $request, AdminDashboardStats $dashboardStats): Response
    {
        Product::ensureOriginColumn();
        Contact::ensureQuotationColumns();
        QuotationDraft::ensureTableExists();

        $contactId = $request->integer('contact_id');
        $initialContact = $contactId > 0
            ? Contact::query()->find($contactId)
            : null;
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
            'initialContact' => $initialContact,
            ...$dashboardStats->summary(),
        ]);
    }

    public function listDrafts(): JsonResponse
    {
        QuotationDraft::ensureTableExists();

        return response()->json([
            'drafts' => QuotationDraft::query()->latest('updated_at')->limit(50)->get(),
        ]);
    }

    public function storeDraft(Request $request): RedirectResponse|JsonResponse
    {
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
            'user_id' => $request->user()?->id,
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
}
