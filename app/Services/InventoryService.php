<?php
namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Store;
use App\Models\Transfer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockAdjustment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\InsufficientStockException;


class InventoryService
{

    /**
     * Process a sale
     */
    public function processSale($storeId, $items, $userId, $customerId = null)
    {
        return DB::transaction(function () use ($storeId, $items, $userId, $customerId) {
            // Create sale record
            $sale = Sale::create([
                'store_id' => $storeId,
                'customer_id' => $customerId,
                'created_by' => $userId,
                'status' => 'COMPLETED'
            ]);

            $totalAmount = 0;

            foreach ($items as $item) {
                // Lock inventory for update
                $inventory = Inventory::where('store_id', $storeId)
                    ->where('product_id', $item['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($inventory->available_quantity < $item['quantity']) {
                    throw new InsufficientStockException(
                        "Insufficient stock for product: {$inventory->product->name}"
                    );
                }

                // Get product for price
                $product = Product::findOrFail($item['product_id']);
                
                // Calculate item total
                $itemTotal = $product->selling_price * $item['quantity'];
                $totalAmount += $itemTotal;

                // Create sale item
                $saleItem = $sale->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->selling_price,
                    'total' => $itemTotal
                ]);

                // Update inventory
                $oldQuantity = $inventory->quantity;
                $inventory->decrement('quantity', $item['quantity']);
                
                // Clear inventory cache
                $this->clearInventoryCache($storeId, $item['product_id']);

                // Create inventory movement
                InventoryMovement::create([
                    'movement_type' => 'SALE',
                    'product_id' => $item['product_id'],
                    'from_store_id' => $storeId,
                    'quantity' => -$item['quantity'],
                    'previous_quantity' => $oldQuantity,
                    'new_quantity' => $inventory->fresh()->quantity,
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'created_by' => $userId
                ]);
            }

            // Update sale totals
            $sale->update([
                'total_amount' => $totalAmount,
                'grand_total' => $totalAmount
            ]);

            return $sale;
        }, 5); // 5 retry attempts for deadlocks
    }

    /**
     * Transfer stock between stores
     */
    public function transferStock($fromStoreId, $toStoreId, $items, $userId, $expectedDeliveryDate = null)
    {
        return DB::transaction(function () use ($fromStoreId, $toStoreId, $items, $userId, $expectedDeliveryDate) {
            // Create transfer record
            $transfer = Transfer::create([
                'from_store_id' => $fromStoreId,
                'to_store_id' => $toStoreId,
                'expected_delivery_date' => $expectedDeliveryDate,
                'created_by' => $userId,
                'status' => 'PENDING'
            ]);

            foreach ($items as $item) {
                // Lock source inventory
                $fromInventory = Inventory::where('store_id', $fromStoreId)
                    ->where('product_id', $item['product_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($fromInventory->available_quantity < $item['quantity']) {
                    throw new InsufficientStockException(
                        "Insufficient stock for transfer"
                    );
                }

                // Create transfer item
                $transferItem = $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity_requested' => $item['quantity'],
                    'status' => 'PENDING'
                ]);

                // Reserve stock
                $fromInventory->increment('reserved_quantity', $item['quantity']);
            }

            return $transfer;
        });
    }

    /**
     * Receive transfer
     */
    public function receiveTransfer($transferId, $items, $userId)
    {
        return DB::transaction(function () use ($transferId, $items, $userId) {
            $transfer = Transfer::lockForUpdate()->findOrFail($transferId);

            if ($transfer->status !== 'SHIPPED') {
                throw new \Exception('Transfer must be shipped before receiving');
            }

            foreach ($items as $item) {
                $transferItem = $transfer->items()
                    ->where('product_id', $item['product_id'])
                    ->firstOrFail();

                // Update from store inventory (release reserved)
                $fromInventory = Inventory::where('store_id', $transfer->from_store_id)
                    ->where('product_id', $item['product_id'])
                    ->lockForUpdate()
                    ->first();

                if ($fromInventory) {
                    $fromInventory->decrement('reserved_quantity', $item['quantity']);
                    $fromInventory->decrement('quantity', $item['quantity']);
                }

                // Update to store inventory
                $toInventory = Inventory::firstOrCreate(
                    [
                        'store_id' => $transfer->to_store_id,
                        'product_id' => $item['product_id']
                    ],
                    ['quantity' => 0]
                );

                $oldQuantity = $toInventory->quantity;
                $toInventory->increment('quantity', $item['quantity']);

                // Create inventory movement
                InventoryMovement::create([
                    'movement_type' => 'TRANSFER',
                    'product_id' => $item['product_id'],
                    'from_store_id' => $transfer->from_store_id,
                    'to_store_id' => $transfer->to_store_id,
                    'quantity' => $item['quantity'],
                    'previous_quantity' => $oldQuantity,
                    'new_quantity' => $toInventory->fresh()->quantity,
                    'reference_type' => 'transfer',
                    'reference_id' => $transfer->id,
                    'created_by' => $userId
                ]);

                // Update transfer item
                $transferItem->update([
                    'quantity_shipped' => $item['quantity'],
                    'quantity_received' => $item['quantity'],
                    'status' => 'RECEIVED'
                ]);
            }

            $transfer->update([
                'status' => 'RECEIVED',
                'received_by' => $userId,
                'received_at' => now()
            ]);

            return $transfer;
        });
    }


    /**
     * Adjust stock levels
     */
    public function adjustStock($storeId, $items, $type, $reason, $notes, $userId)
    {
        return DB::transaction(function () use ($storeId, $items, $type, $reason, $notes, $userId) {
            $adjustment = \App\Models\StockAdjustment::create([
                'store_id' => $storeId,
                'type' => $type,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $userId,
                'status' => 'COMPLETED'
            ]);

            foreach ($items as $item) {
                $inventory = Inventory::firstOrCreate(
                    [
                        'store_id' => $storeId,
                        'product_id' => $item['product_id']
                    ],
                    ['quantity' => 0, 'reserved_quantity' => 0]
                );

                $oldQuantity = $inventory->quantity;
                $newQuantity = $item['new_quantity'];
                $quantityChange = $newQuantity - $oldQuantity;

                // Create adjustment item
                $adjustment->items()->create([
                    'product_id' => $item['product_id'],
                    'previous_quantity' => $oldQuantity,
                    'adjusted_quantity' => $quantityChange,
                    'new_quantity' => $newQuantity,
                    'reason' => $item['reason'] ?? null
                ]);

                // Update inventory
                $inventory->update(['quantity' => $newQuantity]);

                // Create inventory movement record
                InventoryMovement::create([
                    'movement_type' => 'ADJUSTMENT',
                    'product_id' => $item['product_id'],
                    'from_store_id' => $storeId,
                    'quantity' => $quantityChange,
                    'previous_quantity' => $oldQuantity,
                    'new_quantity' => $newQuantity,
                    'reason' => $reason,
                    'notes' => $notes . ($item['reason'] ? " - Item reason: {$item['reason']}" : ''),
                    'reference_type' => 'adjustment',
                    'reference_id' => $adjustment->id,
                    'created_by' => $userId
                ]);
            }

            return $adjustment;
        });
    }

    /**
     * Get inventory with caching
     */
    public function getInventory($storeId, $productId = null)
    {
        $cacheKey = "inventory.{$storeId}." . ($productId ?? 'all');

        return Cache::remember($cacheKey, 300, function () use ($storeId, $productId) {
            $query = Inventory::with(['product', 'store'])
                ->where('store_id', $storeId);

            if ($productId) {
                return $query->where('product_id', $productId)->first();
            }

            return $query->get();
        });
    }

    /**
     * Clear inventory cache
     */
    protected function clearInventoryCache($storeId, $productId = null)
    {
        Cache::forget("inventory.{$storeId}." . ($productId ?? 'all'));
        
        if ($productId) {
            Cache::forget("inventory.{$storeId}.all");
        }
    }

    /**
     * Get inventory movement history
     */
    public function getMovementHistory($productId = null, $storeId = null, $fromDate = null, $toDate = null)
    {
        $query = InventoryMovement::with(['product', 'fromStore', 'toStore', 'creator']);

        if ($productId) {
            $query->where('product_id', $productId);
        }

        if ($storeId) {
            $query->where(function ($q) use ($storeId) {
                $q->where('from_store_id', $storeId)
                  ->orWhere('to_store_id', $storeId);
            });
        }

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        return $query->orderBy('created_at', 'desc')->paginate(50);
    }

    /**
     * Get stock valuation report
     */
    public function getStockValuation($branchId = null)
    {
        $query = Inventory::with(['product', 'store.branch']);

        if ($branchId) {
            $query->whereHas('store', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        return $query->get()->map(function ($inventory) {
            return [
                'store' => $inventory->store->name,
                'branch' => $inventory->store->branch->name,
                'product' => $inventory->product->name,
                'sku' => $inventory->product->sku,
                'quantity' => $inventory->quantity,
                'unit_cost' => $inventory->product->cost_price,
                'total_cost' => $inventory->quantity * $inventory->product->cost_price,
                'unit_price' => $inventory->product->selling_price,
                'total_value' => $inventory->quantity * $inventory->product->selling_price
            ];
        });
    }
}