<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Contact;
use App\Models\KflexPrice;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;

class QuotationController extends Controller
{
    /**
     * Display the quotation builder page.
     */
    public function index(Request $request, AdminDashboardStats $dashboardStats): Response
    {
        Contact::ensureQuotationColumns();

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

        return Inertia::render('quotations/Index', [
            'aristonProducts' => $aristonProducts,
            'kflexPrices' => $kflexPrices,
            'kflexProductLines' => KflexPrice::PRODUCT_LINES,
            'kflexProductLineMeta' => KflexPrice::PRODUCT_LINE_META,
            'kflexTubeVariants' => KflexPrice::TUBE_VARIANTS,
            'kflexSheetVariants' => KflexPrice::SHEET_VARIANTS,
            'initialContact' => $initialContact,
            ...$dashboardStats->summary(),
        ]);
    }
}
