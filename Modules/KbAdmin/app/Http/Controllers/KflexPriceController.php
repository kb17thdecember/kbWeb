<?php

namespace Modules\KbAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\KflexPrice;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\KbAdmin\Support\AdminDashboardStats;

class KflexPriceController extends Controller
{
    /**
     * Display the K-Flex detailed pricing management page.
     */
    public function index(AdminDashboardStats $dashboardStats): Response
    {
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

        return Inertia::render('kflex-prices/Index', [
            'kflexPrices' => $kflexPrices,
            'productLines' => KflexPrice::PRODUCT_LINES,
            'productLineMeta' => KflexPrice::PRODUCT_LINE_META,
            'formTypes' => KflexPrice::FORM_TYPES,
            'tubeVariants' => KflexPrice::TUBE_VARIANTS,
            'sheetVariants' => KflexPrice::SHEET_VARIANTS,
            ...$dashboardStats->summary(),
        ]);
    }

    /**
     * Bulk create or update multiple K-Flex size pricing rows at once (unlimited rows).
     */
    public function bulkStoreOrUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer', 'exists:kflex_prices,id'],
            'items.*.product_line' => ['required', 'string', Rule::in(array_keys(KflexPrice::PRODUCT_LINES))],
            'items.*.form_type' => ['required', 'string', Rule::in(array_keys(KflexPrice::FORM_TYPES))],
            'items.*.variant_type' => ['required', 'string', 'max:50'],
            'items.*.thickness' => ['required', 'string', 'max:50'],
            'items.*.diameter' => ['nullable', 'string', 'max:50'],
            'items.*.brand' => ['nullable', 'string', 'max:100'],
            'items.*.origin' => ['nullable', 'string', 'max:100'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.price' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ]);

        foreach ($validated['items'] as $index => $item) {
            $formType = $item['form_type'];
            $variantType = $item['variant_type'];
            $allowedVariants = $formType === 'sheet'
                ? array_keys(KflexPrice::SHEET_VARIANTS)
                : array_keys(KflexPrice::TUBE_VARIANTS);

            if (! in_array($variantType, $allowedVariants, true)) {
                return back()->withErrors([
                    "items.{$index}.variant_type" => 'Phân loại bề mặt không hợp lệ với loại hình dạng đã chọn.',
                ]);
            }

            if ($formType === 'tube' && trim((string) ($item['diameter'] ?? '')) === '') {
                return back()->withErrors([
                    "items.{$index}.diameter" => 'Đường kính là bắt buộc đối với loại Ống.',
                ]);
            }
        }

        $savedCount = 0;

        DB::transaction(function () use ($validated, &$savedCount): void {
            foreach ($validated['items'] as $item) {
                $productLine = $item['product_line'];
                $formType = $item['form_type'];
                $thickness = trim((string) $item['thickness']);
                $diameter = $formType === 'tube'
                    ? trim((string) ($item['diameter'] ?? ''))
                    : (trim((string) ($item['diameter'] ?? '')) ?: null);

                $defaults = KflexPrice::defaultBrandAndOrigin($productLine);
                $brand = trim((string) ($item['brand'] ?? '')) ?: $defaults['brand'];
                $origin = trim((string) ($item['origin'] ?? '')) ?: $defaults['origin'];

                $productName = trim((string) ($item['product_name'] ?? ''));

                if ($productName === '') {
                    $productName = KflexPrice::buildDefaultProductName($productLine, $formType, $thickness, $diameter);
                }

                $unit = trim((string) ($item['unit'] ?? ''));
                if ($unit === '') {
                    $unit = $formType === 'sheet' ? 'm²' : 'm';
                }

                $payload = [
                    'product_line' => $productLine,
                    'form_type' => $formType,
                    'variant_type' => $item['variant_type'],
                    'thickness' => $thickness,
                    'diameter' => $diameter,
                    'brand' => $brand,
                    'origin' => $origin,
                    'product_name' => $productName,
                    'unit' => $unit,
                    'price' => (int) $item['price'],
                ];

                if (! empty($item['id'])) {
                    KflexPrice::query()->whereKey($item['id'])->update($payload);
                    $savedCount++;

                    continue;
                }

                $existingQuery = KflexPrice::query()
                    ->where('product_line', $payload['product_line'])
                    ->where('form_type', $payload['form_type'])
                    ->where('variant_type', $payload['variant_type'])
                    ->where('thickness', $payload['thickness']);

                if ($payload['diameter'] === null) {
                    $existingQuery->whereNull('diameter');
                } else {
                    $existingQuery->where('diameter', $payload['diameter']);
                }

                $existing = $existingQuery->first();

                if ($existing) {
                    $existing->update($payload);
                } else {
                    KflexPrice::query()->create($payload);
                }

                $savedCount++;
            }
        });

        return to_route('admin.kflex-prices.index')
            ->with('toast', "Đã lưu cập nhật giá cho {$savedCount} kích thước bảo ôn K-Flex thành công.");
    }

    /**
     * Update a single K-Flex pricing row.
     */
    public function update(Request $request, KflexPrice $kflexPrice): RedirectResponse
    {
        $validated = $request->validate([
            'product_line' => ['required', 'string', Rule::in(array_keys(KflexPrice::PRODUCT_LINES))],
            'form_type' => ['required', 'string', Rule::in(array_keys(KflexPrice::FORM_TYPES))],
            'variant_type' => [
                'required',
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $formType = (string) $request->input('form_type');
                    $allowed = $formType === 'sheet'
                        ? array_keys(KflexPrice::SHEET_VARIANTS)
                        : array_keys(KflexPrice::TUBE_VARIANTS);

                    if (! in_array($value, $allowed, true)) {
                        $fail('Phân loại bề mặt không hợp lệ.');
                    }
                },
            ],
            'thickness' => ['required', 'string', 'max:50'],
            'diameter' => [
                Rule::requiredIf(fn (): bool => $request->input('form_type') === 'tube'),
                'nullable',
                'string',
                'max:50',
            ],
            'brand' => ['nullable', 'string', 'max:100'],
            'origin' => ['nullable', 'string', 'max:100'],
            'product_name' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:30'],
            'price' => ['required', 'integer', 'min:0', 'max:999999999999'],
        ]);

        $productLine = $validated['product_line'];
        $formType = $validated['form_type'];
        $thickness = trim((string) $validated['thickness']);
        $diameter = $formType === 'tube'
            ? trim((string) ($validated['diameter'] ?? ''))
            : (trim((string) ($validated['diameter'] ?? '')) ?: null);

        $defaults = KflexPrice::defaultBrandAndOrigin($productLine);
        $brand = trim((string) ($validated['brand'] ?? '')) ?: $defaults['brand'];
        $origin = trim((string) ($validated['origin'] ?? '')) ?: $defaults['origin'];

        $productName = trim((string) ($validated['product_name'] ?? ''));

        if ($productName === '') {
            $productName = KflexPrice::buildDefaultProductName($productLine, $formType, $thickness, $diameter);
        }

        $kflexPrice->update([
            'product_line' => $productLine,
            'form_type' => $formType,
            'variant_type' => $validated['variant_type'],
            'thickness' => $thickness,
            'diameter' => $diameter,
            'brand' => $brand,
            'origin' => $origin,
            'product_name' => $productName,
            'unit' => trim((string) ($validated['unit'] ?? '')) ?: ($formType === 'sheet' ? 'm²' : 'm'),
            'price' => (int) $validated['price'],
        ]);

        return to_route('admin.kflex-prices.index')
            ->with('toast', 'Đã cập nhật giá kích thước bảo ôn K-Flex thành công.');
    }

    /**
     * Remove a single K-Flex pricing row.
     */
    public function destroy(KflexPrice $kflexPrice): RedirectResponse
    {
        $kflexPrice->delete();

        return to_route('admin.kflex-prices.index')
            ->with('toast', 'Đã xóa kích thước K-Flex thành công.');
    }

    /**
     * Remove multiple K-Flex pricing rows at once.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:kflex_prices,id'],
        ]);

        $deletedCount = KflexPrice::query()->whereIn('id', $validated['ids'])->delete();

        return to_route('admin.kflex-prices.index')
            ->with('toast', "Đã xóa {$deletedCount} kích thước K-Flex thành công.");
    }
}
