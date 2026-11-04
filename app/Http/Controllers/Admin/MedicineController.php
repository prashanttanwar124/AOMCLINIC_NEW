<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\Size;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MedicineController extends Controller
{
    /**
     * Search medicine variations dynamically.
     */
    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('query', '');

        $stocksQuery = MedicineStock::query()
            ->with(['medicine', 'category', 'size']);

        if (filled($query)) {
            $cleanQuery = str_replace('|', ' ', $query);
            $words = array_filter(explode(' ', $cleanQuery), function ($word) {
                return trim($word) !== '';
            });

            foreach ($words as $word) {
                $stocksQuery->where(function ($q) use ($word) {
                    $q->whereHas('medicine', function ($qm) use ($word) {
                        $qm->where('name', 'like', "%{$word}%");
                    })
                        ->orWhereHas('category', function ($qc) use ($word) {
                            $qc->where('name', 'like', "%{$word}%");
                        })
                        ->orWhereHas('size', function ($qs) use ($word) {
                            $qs->where('name', 'like', "%{$word}%");
                        });
                });
            }
        }

        $stocks = $stocksQuery->limit(30)->get();

        $results = $stocks->map(function ($stock) {
            $medicineName = $stock->medicine?->name ?? '';
            $label = trim(strtoupper($medicineName).' | '.($stock->category?->name ?? '').' | '.($stock->size?->name ?? ''));

            return [
                'id' => "{$stock->medicine_id} | {$stock->category_id} | {$stock->size_id}",
                'label' => $label,
                'name' => $medicineName,
                'category' => $stock->category?->name ?? '',
                'sizeName' => $stock->size?->name ?? '',
                'stock' => $stock->quantity,
            ];
        });

        return response()->json($results);
    }

    /**
     * Show the medicines ledger and config lists.
     */
    public function index(Request $request): Response
    {
        $search = $request->query('search');
        $stockStatus = $request->query('stock_status');
        $categoryId = $request->query('category_id');
        $sizeId = $request->query('size_id');

        $namesQuery = Medicine::query()
            ->select('name')
            ->when(filled($search), function ($q) use ($search) {
                $trimmed = trim((string) $search);
                $words = array_filter(preg_split('/[\s|,]+/', $trimmed) ?: [], fn ($w) => trim($w) !== '');

                foreach ($words as $word) {
                    $q->where(function ($sub) use ($word) {
                        $sub->where('name', 'like', "%{$word}%")
                            ->orWhereHas('stocks.category', function ($qc) use ($word) {
                                $qc->where('name', 'like', "%{$word}%");
                            })
                            ->orWhereHas('stocks.size', function ($qs) use ($word) {
                                $qs->where('name', 'like', "%{$word}%");
                            });
                    });
                }
            })
            ->when(filled($categoryId), function ($q) use ($categoryId) {
                $q->whereHas('stocks', function ($qs) use ($categoryId) {
                    $qs->where('category_id', (int) $categoryId);
                });
            })
            ->when(filled($sizeId), function ($q) use ($sizeId) {
                $q->whereHas('stocks', function ($qs) use ($sizeId) {
                    $qs->where('size_id', (int) $sizeId);
                });
            })
            ->when($stockStatus === 'out_of_stock', function ($q) {
                $q->where(function ($sub) {
                    $sub->whereDoesntHave('stocks')
                        ->orWhereHas('stocks', function ($qs) {
                            $qs->where('quantity', '<=', 0);
                        });
                });
            })
            ->when($stockStatus === 'low_stock', function ($q) {
                $q->whereHas('stocks', function ($qs) {
                    $qs->whereBetween('quantity', [1, 9]);
                });
            })
            ->when($stockStatus === 'in_stock', function ($q) {
                $q->whereHas('stocks', function ($qs) {
                    $qs->where('quantity', '>=', 10);
                });
            });

        $paginatedNames = $namesQuery->groupBy('name')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $names = $paginatedNames->pluck('name')->toArray();

        $allVariations = MedicineStock::whereHas('medicine', function ($q) use ($names) {
            $q->whereIn('name', $names);
        })
            ->with(['medicine', 'category', 'size'])
            ->get();

        $paginatedNames->getCollection()->transform(function ($item) use ($allVariations) {
            $name = $item->name;
            $variations = $allVariations->filter(function ($v) use ($name) {
                return $v->medicine?->name === $name;
            })->values();
            $totalQuantity = $variations->sum('quantity');

            return [
                'id' => $variations->first()?->id,
                'name' => $name,
                'total_quantity' => $totalQuantity,
                'variations' => $variations->map(function ($v) use ($name) {
                    return [
                        'id' => $v->id,
                        'name' => $name,
                        'category_id' => $v->category_id,
                        'size_id' => $v->size_id,
                        'quantity' => $v->quantity,
                        'quantity_reduction' => $v->quantity_reduction,
                        'category' => $v->category,
                        'size' => $v->size,
                    ];
                }),
            ];
        });

        $medicines = $paginatedNames;

        $categories = Category::withCount('stocks')->orderBy('name')->get();
        $sizes = Size::withCount('stocks')->orderBy('name')->get();

        $summary = [
            'totalMedicines' => Medicine::query()->distinct('name')->count('name'),
            'totalVariations' => MedicineStock::query()->count(),
            'totalUnits' => (int) MedicineStock::query()->sum('quantity'),
            'inStockCount' => MedicineStock::query()->where('quantity', '>=', 10)->count(),
            'lowStockCount' => MedicineStock::query()->whereBetween('quantity', [1, 9])->count(),
            'outOfStockCount' => MedicineStock::query()->where('quantity', '<=', 0)->count(),
        ];

        return Inertia::render('admin/Medicines', [
            'medicines' => $medicines,
            'categories' => $categories,
            'sizes' => $sizes,
            'summary' => $summary,
            'filters' => [
                'search' => $search,
                'stock_status' => $stockStatus,
                'category_id' => $categoryId ? (int) $categoryId : null,
                'size_id' => $sizeId ? (int) $sizeId : null,
            ],
        ]);
    }

    /**
     * Store new medicine variations.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'variations' => ['required', 'array', 'min:1'],
            'variations.*.category_id' => ['required', 'exists:categories,id'],
            'variations.*.size_id' => ['required', 'exists:sizes,id'],
            'variations.*.quantity' => ['required', 'integer', 'min:0'],
        ]);

        $medicine = Medicine::firstOrCreate([
            'name' => $validated['name'],
        ]);

        foreach ($validated['variations'] as $variation) {
            MedicineStock::updateOrCreate(
                [
                    'medicine_id' => $medicine->id,
                    'category_id' => $variation['category_id'],
                    'size_id' => $variation['size_id'],
                ],
                [
                    'quantity' => $variation['quantity'],
                    'quantity_reduction' => 1,
                ]
            );
        }

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => 'Medicine variation(s) created successfully.',
        ]);
    }

    /**
     * Update quantity for a specific variation.
     */
    public function updateQuantity(Request $request, MedicineStock $medicine): RedirectResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        $medicine->update([
            'quantity' => $validated['quantity'],
        ]);

        $medicineName = $medicine->medicine?->name ?? '';

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => "Quantity for {$medicineName} updated successfully.",
        ]);
    }

    /**
     * Delete a medicine variation.
     */
    public function destroy(MedicineStock $medicine): RedirectResponse
    {
        $name = $medicine->medicine?->name ?? '';

        $medicine->delete();

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => "Medicine variation {$name} deleted successfully.",
        ]);
    }

    /**
     * Add a category configuration option.
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:categories,name'],
        ]);

        Category::create([
            'name' => $validated['name'],
        ]);

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => 'Category option added successfully.',
        ]);
    }

    /**
     * Update a category option.
     */
    public function updateCategory(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:categories,name,'.$category->id],
        ]);

        $category->update([
            'name' => $validated['name'],
        ]);

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => 'Category option updated successfully.',
        ]);
    }

    /**
     * Delete a category option.
     */
    public function destroyCategory(Category $category): RedirectResponse
    {
        $name = $category->name;
        $category->delete();

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => "Category option {$name} removed successfully.",
        ]);
    }

    /**
     * Add a size configuration option.
     */
    public function storeSize(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:sizes,name'],
        ]);

        Size::create([
            'name' => $validated['name'],
        ]);

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => 'Size option added successfully.',
        ]);
    }

    /**
     * Update a size option.
     */
    public function updateSize(Request $request, Size $size): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:sizes,name,'.$size->id],
        ]);

        $size->update([
            'name' => $validated['name'],
        ]);

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => 'Size option updated successfully.',
        ]);
    }

    /**
     * Delete a size option.
     */
    public function destroySize(Size $size): RedirectResponse
    {
        $name = $size->name;
        $size->delete();

        return redirect()->back()->with('toast', [
            'type' => 'success',
            'message' => "Size option {$name} removed successfully.",
        ]);
    }
}
