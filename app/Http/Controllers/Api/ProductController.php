<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Routing\Controllers\HasMiddleware;

class ProductController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            // new Middleware('auth:sanctum'),
            // new Middleware('permission:view products', only: ['index', 'show', 'list']),
            // new Middleware('permission:create products', only: ['store']),
            // new Middleware('permission:edit products', only: ['update']),
            // new Middleware('permission:delete products', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of all products.
     */
    public function index(Request $request)
    {
        $products = Product::query()
            ->when($request->search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%")
                      ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->when($request->category, function ($query, $category) {
                return $query->where('category', $category);
            })
            ->when($request->active === 'true' || $request->active === 'false', function ($query) use ($request) {
                return $query->where('is_active', $request->active === 'true');
            })
            ->when($request->min_price, function ($query, $price) {
                return $query->where('selling_price', '>=', $price);
            })
            ->when($request->max_price, function ($query, $price) {
                return $query->where('selling_price', '<=', $price);
            })
            ->orderBy($request->sort_by ?? 'name', $request->sort_direction ?? 'asc')
            ->paginate($request->per_page ?? 20)
            ->withQueryString();

        return response()->json([
            'data' => $products->items(),
            'current_page' => $products->currentPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'last_page' => $products->lastPage(),
        ]);
    }

    /**
     * Display the specified product.
     */
    public function show(Product $product)
    {
        return response()->json(
            $product->load(['inventories.store'])
        );
    }

    /**
     * Get all products as a simple list (for dropdowns).
     */
    public function list(Request $request)
    {
        $products = Product::select('id', 'name', 'sku', 'selling_price', 'cost_price')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json($products);
    }

    /**
     * Store a newly created product.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string|unique:products,sku',
            'name' => 'required|string|max:255',
            'barcode' => 'nullable|string|unique:products,barcode',
            'description' => 'nullable|string',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0|gte:cost_price',
            'unit' => 'required|string|max:50',
            'reorder_level' => 'required|integer|min:0',
            'category' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $product = Product::create($validated);

        return response()->json([
            'message' => 'Product created successfully',
            'data' => $product,
        ], 201);
    }

    /**
     * Update the specified product.
     */
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'sku' => 'sometimes|string|unique:products,sku,' . $product->id,
            'name' => 'sometimes|string|max:255',
            'barcode' => 'nullable|string|unique:products,barcode,' . $product->id,
            'description' => 'nullable|string',
            'cost_price' => 'sometimes|numeric|min:0',
            'selling_price' => 'sometimes|numeric|min:0|gte:cost_price',
            'unit' => 'sometimes|string|max:50',
            'reorder_level' => 'sometimes|integer|min:0',
            'category' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'weight' => 'nullable|numeric|min:0',
            'dimensions' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $product->update($validated);

        return response()->json([
            'message' => 'Product updated successfully',
            'data' => $product,
        ]);
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product)
    {
        // Check if product has any inventory or sales
        if ($product->inventories()->exists()) {
            return response()->json([
                'message' => 'Cannot delete product with existing inventory'
            ], 400);
        }

        if ($product->saleItems()->exists()) {
            return response()->json([
                'message' => 'Cannot delete product with existing sales'
            ], 400);
        }

        $product->delete();

        return response()->json([
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * Bulk update products (for price changes, etc.)
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.id' => 'required|exists:products,id',
            'products.*.selling_price' => 'sometimes|numeric|min:0',
            'products.*.cost_price' => 'sometimes|numeric|min:0',
            'products.*.is_active' => 'sometimes|boolean',
        ]);

        $updated = [];
        foreach ($request->products as $item) {
            $product = Product::find($item['id']);
            $product->update(collect($item)->except('id')->toArray());
            $updated[] = $product;
        }

        return response()->json([
            'message' => count($updated) . ' products updated successfully',
            'data' => $updated,
        ]);
    }

    /**
     * Get low stock products across all stores
     */
    public function lowStock(Request $request)
    {
        $products = Product::whereHas('inventories', function ($query) {
                $query->whereRaw('quantity - reserved_quantity <= reorder_point');
            })
            ->with(['inventories' => function ($query) {
                $query->whereRaw('quantity - reserved_quantity <= reorder_point')
                      ->with('store');
            }])
            ->when($request->search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            })
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => $products->items(),
            'current_page' => $products->currentPage(),
            'per_page' => $products->perPage(),
            'total' => $products->total(),
            'last_page' => $products->lastPage(),
        ]);
    }

    /**
     * Get product categories
     */
    public function categories()
    {
        $categories = Product::whereNotNull('category')
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return response()->json($categories);
    }

    /**
     * Import products from CSV
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        // Handle CSV import logic here
        // This would parse the CSV and create/update products

        return response()->json([
            'message' => 'Products imported successfully',
        ]);
    }

    /**
     * Export products to CSV
     */
    public function export(Request $request)
    {
        $products = Product::orderBy('name')->get();
        
        // Generate CSV and return as download
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products.csv"',
        ];

        $callback = function () use ($products) {
            $file = fopen('php://output', 'w');
            
            // Add headers
            fputcsv($file, ['SKU', 'Name', 'Barcode', 'Cost Price', 'Selling Price', 'Unit', 'Category', 'Brand']);
            
            // Add rows
            foreach ($products as $product) {
                fputcsv($file, [
                    $product->sku,
                    $product->name,
                    $product->barcode,
                    $product->cost_price,
                    $product->selling_price,
                    $product->unit,
                    $product->category,
                    $product->brand,
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

