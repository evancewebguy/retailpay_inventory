<?php

namespace App\Http\Controllers;

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
            new Middleware('auth:sanctum'),
            new Middleware('permission:view products', only: ['index', 'show', 'list']),
            new Middleware('permission:create products', only: ['store']),
            new Middleware('permission:edit products', only: ['update']),
            new Middleware('permission:delete products', only: ['destroy']),
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

}
