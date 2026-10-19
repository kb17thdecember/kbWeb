<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->with([
                'children' => fn ($query) => $query
                    ->withCount('products')
                    ->orderBy('name'),
            ])
            ->withCount('children')
            ->orderBy('name')
            ->get();

        return Inertia::render('categories/Index', [
            'categories' => $categories,
        ]);
    }
}
