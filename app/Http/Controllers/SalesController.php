<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\InventoryService;
use App\Http\Requests\SaleRequest;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Product;
use Inertia\Inertia;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SalesController extends Controller implements HasMiddleware
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:view sales', only: ['index', 'show']),
            new Middleware('permission:create sales', only: ['create', 'store']),
            new Middleware('permission:edit sales', only: ['edit', 'update']),
            new Middleware('permission:delete sales', only: ['destroy']),
        ];
    }

    /**
     * Display a listing of sales.
     */
    public function index(Request $request)
    {
        $sales = Sale::with(['store', 'items.product', 'creator'])
            ->when($request->store_id, function ($query, $storeId) {
                return $query->where('store_id', $storeId);
            })
            ->when($request->from_date, function ($query, $date) {
                return $query->whereDate('created_at', '>=', $date);
            })
            ->when($request->to_date, function ($query, $date) {
                return $query->whereDate('created_at', '<=', $date);
            })
            ->when($request->search, function ($query, $search) {
                return $query->where('sale_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn($q) => $q->where('name', 'like', "%{$search}%"));
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20)
            ->withQueryString();

        // Get stores for filter dropdown
        $stores = Store::select('id', 'name', 'code')->get();
        
        // Get products for create form
        $products = Product::select('id', 'name', 'sku', 'selling_price', 'cost_price')
            ->where('is_active', true)
            ->get();

        return Inertia::render('Sales/Index', [
            'sales' => $sales,
            'stores' => $stores,
            'products' => $products,
            'filters' => $request->only(['store_id', 'from_date', 'to_date', 'search', 'per_page']),
        ]);
    }

    /**
     * Show form for creating a new sale.
     */
    public function create()
    {
        // Get stores user has access to
        $user = auth()->user();
        $stores = Store::select('id', 'name', 'code')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('id', $user->store_id);
                }
            })
            ->where('is_active', true)
            ->get();

        $products = Product::select('id', 'name', 'sku', 'selling_price', 'cost_price')
            ->where('is_active', true)
            ->get();

        return Inertia::render('Sales/Create', [
            'stores' => $stores,
            'products' => $products,
        ]);
    }

    /**
     * Store a newly created sale.
     */
    public function store(SaleRequest $request)
    {
        try {
            // Check if user has access to this store
            if (!auth()->user()->canAccessStore($request->store_id)) {
                return redirect()->back()
                    ->with('error', 'Unauthorized to sell from this store');
            }

            $sale = $this->inventoryService->processSale(
                $request->store_id,
                $request->items,
                auth()->id(),
                $request->customer_id
            );

            return redirect()->route('sales.show', $sale->id)
                ->with('success', 'Sale completed successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Sale failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified sale.
     */
    public function show(Sale $sale)
    {
        // Check access
        if (!auth()->user()->canAccessStore($sale->store_id)) {
            return redirect()->route('sales.index')
                ->with('error', 'Unauthorized to view this sale');
        }

        $sale->load(['items.product', 'store', 'creator', 'customer']);

        return Inertia::render('Sales/Show', [
            'sale' => $sale,
        ]);
    }

    /**
     * Show form for editing the specified sale.
     */
    public function edit(Sale $sale)
    {
        // Check access
        if (!auth()->user()->canAccessStore($sale->store_id)) {
            return redirect()->route('sales.index')
                ->with('error', 'Unauthorized to edit this sale');
        }

        // Only allow editing if sale is pending
        if ($sale->status !== 'PENDING') {
            return redirect()->route('sales.show', $sale->id)
                ->with('error', 'Only pending sales can be edited');
        }

        $sale->load(['items']);

        // Get stores user has access to
        $user = auth()->user();
        $stores = Store::select('id', 'name', 'code')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('id', $user->store_id);
                }
            })
            ->where('is_active', true)
            ->get();

        $products = Product::select('id', 'name', 'sku', 'selling_price', 'cost_price')
            ->where('is_active', true)
            ->get();

        return Inertia::render('Sales/Edit', [
            'sale' => $sale,
            'stores' => $stores,
            'products' => $products,
        ]);
    }

    /**
     * Update the specified sale.
     */
    public function update(SaleRequest $request, Sale $sale)
    {
        try {
            // Check access
            if (!auth()->user()->canAccessStore($sale->store_id)) {
                return redirect()->back()
                    ->with('error', 'Unauthorized to edit this sale');
            }

            // Only allow update if sale is pending
            if ($sale->status !== 'PENDING') {
                return redirect()->back()
                    ->with('error', 'Only pending sales can be updated');
            }

            // Process the sale update (this would need to be added to InventoryService)
            // $this->inventoryService->updateSale($sale, $request->validated());

            return redirect()->route('sales.show', $sale->id)
                ->with('success', 'Sale updated successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Update failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified sale.
     */
    public function destroy(Sale $sale)
    {
        if (!auth()->user()->hasRole('Administrator')) {
            return redirect()->route('sales.index')
                ->with('error', 'Only administrators can delete sales');
        }

        // Only allow deletion if sale is pending
        if ($sale->status !== 'PENDING') {
            return redirect()->route('sales.show', $sale->id)
                ->with('error', 'Only pending sales can be deleted');
        }

        $sale->delete();

        return redirect()->route('sales.index')
            ->with('success', 'Sale deleted successfully');
    }

    /**
     * Export sales report.
     */
    public function export(Request $request)
    {
        // Add export functionality if needed
        return redirect()->route('sales.index')
            ->with('info', 'Export feature coming soon');
    }
}