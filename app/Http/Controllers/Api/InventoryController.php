<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InventoryService;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\InventoryMovement;
use App\Models\Store;
use App\Http\Requests\InventoryAdjustmentRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;


class InventoryController extends Controller
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
            new Middleware('permission:view inventory', only: ['index', 'show', 'checkAvailability']),
            new Middleware('permission:adjust inventory', only: ['adjust']),
        ];
    }

    /**
     * Get inventory list with optional filters
     */
    public function index(Request $request)
    {        
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

        // Pagination
        $perPage = $request->per_page ?? 20;
        $inventory = $query->paginate($perPage);

        return response()->json([
            'data' => $inventory->items(),
            'current_page' => $inventory->currentPage(),
            'per_page' => $inventory->perPage(),
            'total' => $inventory->total(),
            'last_page' => $inventory->lastPage()
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
            return response()->json([
                'message' => 'Unauthorized to view this store\'s inventory'
            ], 403);
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

        return response()->json([
            'store' => $store->load('branch'),
            'inventory' => $inventory
        ]);
    }


    /**
     * Check stock availability for multiple products in a store
     * Used by transfer creation to validate stock before transfer
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $user = auth()->user();

        // Check if user has access to this store
        if (!$user->canAccessStore($request->store_id)) {
            return response()->json([
                'message' => 'Unauthorized to check inventory at this store'
            ], 403);
        }

        $results = [];
        $insufficientItems = [];
        $totalValue = 0;

        foreach ($request->items as $item) {
            $inventory = Inventory::where('store_id', $request->store_id)
                ->where('product_id', $item['product_id'])
                ->first();

            $product = Product::find($item['product_id']);
            
            $available = $inventory ? $inventory->available_quantity : 0;
            $requestedQty = $item['quantity'];
            
            // Determine status
            if ($available < $requestedQty) {
                $status = 'insufficient';
                $insufficientItems[] = [
                    'product_id' => $item['product_id'],
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'requested' => $requestedQty,
                    'available' => $available,
                    'shortage' => $requestedQty - $available
                ];
            } elseif ($available <= ($inventory->reorder_point ?? 5)) {
                $status = 'low';
            } else {
                $status = 'ok';
            }

            $totalValue += $available * ($product->selling_price ?? 0);

            $results[$item['product_id']] = [
                'product_id' => $item['product_id'],
                'product_name' => $product->name,
                'sku' => $product->sku,
                'requested_quantity' => $requestedQty,
                'available_quantity' => $available,
                'reserved_quantity' => $inventory ? $inventory->reserved_quantity : 0,
                'on_hand_quantity' => $inventory ? $inventory->quantity : 0,
                'reorder_point' => $inventory ? $inventory->reorder_point : 0,
                'status' => $status,
                'unit_price' => $product->selling_price,
                'total_value' => $available * ($product->selling_price ?? 0),
                'can_fulfill' => $available >= $requestedQty,
            ];
        }

        // Calculate overall availability
        $allAvailable = count($insufficientItems) === 0;
        $partiallyAvailable = count($insufficientItems) > 0 && count($insufficientItems) < count($request->items);

        return response()->json([
            'success' => true,
            'data' => $results,
            'summary' => [
                'total_items_checked' => count($request->items),
                'items_with_sufficient_stock' => count($results) - count($insufficientItems),
                'items_with_insufficient_stock' => count($insufficientItems),
                'all_available' => $allAvailable,
                'partially_available' => $partiallyAvailable,
                'total_inventory_value' => $totalValue,
                'store_id' => $request->store_id,
                'checked_at' => now()->toDateTimeString(),
            ],
            'insufficient_items' => $insufficientItems,
        ]);
    }

    /**
     * Check availability for a single product (simpler endpoint)
     */
    public function checkSingleProductAvailability(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = auth()->user();

        if (!$user->canAccessStore($request->store_id)) {
            return response()->json([
                'message' => 'Unauthorized'
            ], 403);
        }

        $inventory = Inventory::where('store_id', $request->store_id)
            ->where('product_id', $request->product_id)
            ->first();

        $product = Product::find($request->product_id);
        $available = $inventory ? $inventory->available_quantity : 0;
        $requestedQty = $request->quantity;

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $request->product_id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'requested_quantity' => $requestedQty,
                'available_quantity' => $available,
                'reserved_quantity' => $inventory ? $inventory->reserved_quantity : 0,
                'on_hand_quantity' => $inventory ? $inventory->quantity : 0,
                'reorder_point' => $inventory ? $inventory->reorder_point : 0,
                'can_fulfill' => $available >= $requestedQty,
                'shortage' => $available < $requestedQty ? $requestedQty - $available : 0,
                'status' => $available >= $requestedQty 
                    ? ($available <= ($inventory->reorder_point ?? 5) ? 'low' : 'ok') 
                    : 'insufficient',
            ]
        ]);
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

        return response()->json($history);
    }

    /**
     * Perform stock adjustment
     */
    public function adjust(InventoryAdjustmentRequest $request)
    {
        try {
            // Check access
            if (!auth()->user()->canAccessStore($request->store_id)) {
                return response()->json([
                    'message' => 'Unauthorized to adjust inventory at this store'
                ], 403);
            }

            $adjustment = $this->inventoryService->adjustStock(
                $request->store_id,
                $request->items,
                $request->type,
                $request->reason,
                $request->notes,
                auth()->id()
            );

            return response()->json([
                'message' => 'Stock adjustment completed successfully',
                'data' => $adjustment->load(['items.product', 'store'])
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Adjustment failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get low stock alerts
     */
    public function lowStockAlerts()
    {
        $user = auth()->user();
        
        $query = Inventory::with(['product', 'store'])
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

        $alerts = $query->limit(50)->get()->map(function ($inventory) {
            return [
                'store' => $inventory->store->name,
                'branch' => $inventory->store->branch->name,
                'product' => $inventory->product->name,
                'sku' => $inventory->product->sku,
                'current_stock' => $inventory->quantity,
                'reserved' => $inventory->reserved_quantity,
                'available' => $inventory->available_quantity,
                'reorder_point' => $inventory->reorder_point,
                'status' => $inventory->available_quantity <= 0 ? 'OUT_OF_STOCK' : 'LOW_STOCK'
            ];
        });

        return response()->json([
            'total' => $alerts->count(),
            'alerts' => $alerts
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

            return response()->json([
                'message' => 'Inventory updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Bulk update failed',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
