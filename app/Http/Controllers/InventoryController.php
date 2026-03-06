<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Store;
use App\Models\Branch;
use App\Models\Product;
use App\Http\Requests\InventoryAdjustmentRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InventoryController extends Controller implements HasMiddleware
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:view inventory', only: ['index', 'show']),
            new Middleware('permission:adjust inventory', only: ['adjust', 'createAdjustment']),
        ];
    }

    /**
     * Get inventory list with optional filters
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        
        $query = Inventory::with(['product', 'store.branch'])
            ->when($request->store_id, function ($query, $storeId) {
                return $query->where('store_id', $storeId);
            })
            ->when($request->branch_id, function ($query, $branchId) {
                return $query->whereHas('store', function ($q) use ($branchId) {
                    $q->where('branch_id', $branchId);
                });
            })
            ->when($request->product_id, function ($query, $productId) {
                return $query->where('product_id', $productId);
            })
            ->when($request->low_stock, function ($query) {
                return $query->whereRaw('quantity - reserved_quantity <= reorder_point');
            })
            ->when($request->search, function ($query, $search) {
                return $query->whereHas('product', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('sku', 'like', "%{$search}%");
                });
            });

        // Filter by user's accessible stores
        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager')) {
                $query->whereHas('store', function ($q) use ($user) {
                    $q->where('branch_id', $user->branch_id);
                });
            } elseif ($user->hasRole('Store Manager')) {
                $query->where('store_id', $user->store_id);
            }
        }

        // Pagination
        $perPage = $request->per_page ?? 20;
        $inventory = $query->paginate($perPage)->withQueryString();

        // Get filter data
        $stores = Store::select('id', 'name', 'code')
            ->when(!$user->hasRole('Administrator'), function ($query) use ($user) {
                if ($user->hasRole('Branch Manager')) {
                    $query->where('branch_id', $user->branch_id);
                } elseif ($user->hasRole('Store Manager')) {
                    $query->where('id', $user->store_id);
                }
            })
            ->get();

        $branches = Branch::select('id', 'name', 'code')->get();

        return Inertia::render('Inventory/Index', [
            'inventory' => $inventory,
            'stores' => $stores,
            'branches' => $branches,
            'filters' => $request->only(['store_id', 'branch_id', 'search', 'low_stock', 'per_page']),
        ]);
    }

    /**
     * Get inventory for a specific store
     */
    public function show(Store $store)
    {
        $user = auth()->user();

        // Check access
        if (!$user->canAccessStore($store->id)) {
            return redirect()->route('inventory.index')
                ->with('error', 'Unauthorized to view this store\'s inventory');
        }

        $inventory = $store->inventories()
            ->with('product')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'product_sku' => $item->product->sku,
                    'quantity' => $item->quantity,
                    'reserved_quantity' => $item->reserved_quantity,
                    'available_quantity' => $item->available_quantity,
                    'reorder_point' => $item->reorder_point,
                    'is_low_stock' => $item->isLowStock(),
                    'unit_price' => $item->product->selling_price,
                    'total_value' => $item->quantity * $item->product->selling_price
                ];
            });

        return Inertia::render('Inventory/Show', [
            'store' => $store->load('branch'),
            'inventory' => $inventory,
        ]);
    }

    /**
     * Show adjustment form
     */
    public function createAdjustment(Request $request)
    {
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

        $products = Product::select('id', 'name', 'sku', 'selling_price')
            ->where('is_active', true)
            ->get();

        return Inertia::render('Inventory/Adjustment', [
            'stores' => $stores,
            'products' => $products,
            'selected_store' => $request->store_id,
            'selected_product' => $request->product_id,
        ]);
    }

    /**
     * Perform stock adjustment
     */
    public function adjust(InventoryAdjustmentRequest $request)
    {
        try {
            // Check access
            if (!auth()->user()->canAccessStore($request->store_id)) {
                return redirect()->back()
                    ->with('error', 'Unauthorized to adjust inventory at this store');
            }

            $adjustment = $this->inventoryService->adjustStock(
                $request->store_id,
                $request->items,
                $request->type,
                $request->reason,
                $request->notes,
                auth()->id()
            );

            return redirect()->route('inventory.index')
                ->with('success', 'Stock adjustment completed successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Adjustment failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Get inventory movement history
     */
    public function history(Request $request)
    {
        $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'store_id' => 'nullable|exists:stores,id',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'movement_type' => 'nullable|in:SALE,TRANSFER,ADJUSTMENT,PROCUREMENT,RETURN,DAMAGE,LOST'
        ]);

        $history = $this->inventoryService->getMovementHistory(
            $request->product_id,
            $request->store_id,
            $request->from_date,
            $request->to_date,
            $request->movement_type
        );

        // Get filter data
        $products = Product::select('id', 'name', 'sku')->get();
        $stores = Store::select('id', 'name', 'code')->get();

        return Inertia::render('Inventory/History', [
            'movements' => $history,
            'products' => $products,
            'stores' => $stores,
            'filters' => $request->only(['product_id', 'store_id', 'from_date', 'to_date', 'movement_type']),
        ]);
    }

    /**
     * Get low stock alerts
     */
    public function lowStockAlerts()
    {
        $user = auth()->user();
        
        $query = Inventory::with(['product', 'store.branch'])
            ->whereRaw('quantity - reserved_quantity <= reorder_point');

        // Filter by user's accessible stores
        if (!$user->hasRole('Administrator')) {
            if ($user->hasRole('Branch Manager')) {
                $query->whereHas('store', function ($q) use ($user) {
                    $q->where('branch_id', $user->branch_id);
                });
            } elseif ($user->hasRole('Store Manager')) {
                $query->where('store_id', $user->store_id);
            }
        }

        $alerts = $query->paginate(20)->withQueryString();

        return Inertia::render('Inventory/LowStock', [
            'alerts' => $alerts,
        ]);
    }

    /**
     * Bulk update inventory (for procurement/receiving)
     */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        try {
            DB::transaction(function () use ($request) {
                foreach ($request->items as $item) {
                    $inventory = Inventory::firstOrCreate(
                        [
                            'store_id' => $request->store_id,
                            'product_id' => $item['product_id']
                        ],
                        ['quantity' => 0]
                    );

                    $oldQuantity = $inventory->quantity;
                    $inventory->increment('quantity', $item['quantity']);

                    InventoryMovement::create([
                        'movement_type' => 'PROCUREMENT',
                        'product_id' => $item['product_id'],
                        'to_store_id' => $request->store_id,
                        'quantity' => $item['quantity'],
                        'previous_quantity' => $oldQuantity,
                        'new_quantity' => $inventory->fresh()->quantity,
                        'reference' => $request->reference,
                        'notes' => $request->notes,
                        'created_by' => auth()->id()
                    ]);
                }
            });

            return redirect()->route('inventory.index')
                ->with('success', 'Inventory updated successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Bulk update failed: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Export inventory report
     */
    public function export(Request $request)
    {
        // Add export functionality if needed
        return redirect()->route('inventory.index')
            ->with('info', 'Export feature coming soon');
    }
}
