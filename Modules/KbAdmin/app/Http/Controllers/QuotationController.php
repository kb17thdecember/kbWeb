<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;

class QuotationController extends Controller
{
    /**
     * Display the quotation builder page.
     */
    public function index(AdminDashboardStats $dashboardStats): Response
    {
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

        return Inertia::render('quotations/Index', [
            'aristonProducts' => $aristonProducts,
            ...$dashboardStats->summary(),
        ]);
    }
}
