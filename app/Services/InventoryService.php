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

use App\Models\AdjustmentItem;



class InventoryService
{

   /**
     * Process a sale with enhanced error handling
     */
    public function processSale($storeId, $items, $userId, $customerId = null)
    {

        return DB::transaction(function () use ($storeId, $items, $userId, $customerId) {
            
            // Get store name for error messages
            $store = Store::find($storeId);
            if (!$store) {
                dump('❌ Store not found!', ['storeId' => $storeId]);
                throw new \Exception("Store not found");
            }

            // Validate all items first and collect errors
            $validationErrors = [];
            $validItems = [];

            foreach ($items as $index => $item) {
                $itemNumber = $index + 1;
                
                // Check if product exists
                $product = Product::find($item['product_id']);
                if (!$product) {
                    $error = "Item #{$itemNumber}: Product ID {$item['product_id']} not found in system";
                    $validationErrors[] = $error;
                    dump('❌ ' . $error);
                    continue;
                }

                // Check if product exists in store inventory
                $inventory = Inventory::where('store_id', $storeId)
                    ->where('product_id', $item['product_id'])
                    ->first();

                if (!$inventory) {
                    $error = "Item #{$itemNumber}: {$product->name} is not available in {$store->name}";
                    $validationErrors[] = $error;
                    dump('❌ ' . $error);
                    continue;
                }

                // Check available quantity
                if ($inventory->available_quantity < $item['quantity']) {
                    $error = "Item #{$itemNumber}: {$product->name} - Only {$inventory->available_quantity} available, but {$item['quantity']} requested";
                    $validationErrors[] = $error;
                    dump('❌ ' . $error);
                    continue;
                }

                // Store valid item with product and inventory for processing
                $validItems[] = [
                    'item' => $item,
                    'product' => $product,
                    'inventory' => $inventory,
                    'index' => $index
                ];
            }

            // If there are validation errors, throw exception with all messages
            if (!empty($validationErrors)) {
                $errorMessage = "Sale validation failed:\n" . implode("\n", $validationErrors);
                dump('❌ VALIDATION FAILED:', $validationErrors);
                throw new \Exception($errorMessage);
            }

            // Create sale record
            $saleNumber = $this->generateSaleNumber();
            
            $sale = Sale::create([
                'store_id' => $storeId,
                'customer_id' => $customerId,
                'created_by' => $userId,
                'status' => 'COMPLETED',
                'sale_number' => $saleNumber,
                'total_amount' => 0,
                'grand_total' => 0
            ]);

            if (!$sale || !$sale->id) {
                dump('❌ Failed to create sale record');
                throw new \Exception("Failed to create sale record");
            }

            $totalAmount = 0;
            $processedItems = [];

            try {
                // Process each valid item
                foreach ($validItems as $validItem) {
                    $item = $validItem['item'];
                    $product = $validItem['product'];
                    
                    // Lock inventory for update (fresh query with lock)
                    $inventory = Inventory::where('store_id', $storeId)
                        ->where('product_id', $item['product_id'])
                        ->lockForUpdate()
                        ->first();

                    if (!$inventory) {
                        dump('❌ Inventory record disappeared!');
                        throw new \Exception("Inventory record disappeared for {$product->name}");
                    }

                    // Double-check quantity after lock
                    if ($inventory->available_quantity < $item['quantity']) {
                        
                        throw new InsufficientStockException(
                            $product->name,
                            $inventory->available_quantity,
                            $item['quantity']
                        );
                    }

                    // Calculate item total
                    $itemTotal = $product->selling_price * $item['quantity'];
                    $totalAmount += $itemTotal;
                    

                    // Create sale item
                    $saleItem = $sale->items()->create([
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $product->selling_price,
                        'discount' => $item['discount'] ?? 0,
                        'total' => $itemTotal - ($item['discount'] ?? 0)
                    ]);

                    if (!$saleItem || !$saleItem->id) {
                        dump('❌ Failed to create sale item');
                        throw new \Exception("Failed to create sale item for {$product->name}");
                    }

                    // Store old quantity for movement record
                    $oldQuantity = $inventory->quantity;
                    
                    // Update inventory
                    $inventory->decrement('quantity', $item['quantity']);
                    $inventory->refresh();
                   
                    
                    // Clear inventory cache
                    $this->clearInventoryCache($storeId, $item['product_id']);

                    // Create inventory movement
                    $movement = InventoryMovement::create([
                        'movement_type' => 'SALE',
                        'product_id' => $item['product_id'],
                        'from_store_id' => $storeId,
                        'quantity' => -$item['quantity'],
                        'previous_quantity' => $oldQuantity,
                        'new_quantity' => $inventory->quantity,
                        'reference_type' => 'sale',
                        'reference_id' => $sale->id,
                        'created_by' => $userId
                    ]);

                    if (!$movement || !$movement->id) {
                        dump('❌ Failed to create inventory movement');
                        throw new \Exception("Failed to create inventory movement");
                    }
                    $processedItems[] = $product->name;
                }

                // Update sale totals
                $sale->update([
                    'total_amount' => $totalAmount,
                    'grand_total' => $totalAmount
                ]);
                return $sale;

            } catch (\Exception $e) {
                // If anything fails during processing, delete the sale record
                dump('❌ ERROR DURING PROCESSING!');
                dump('Error message:', $e->getMessage());
                dump('Deleting sale record...');
                $sale->delete();
                dump('✅ Sale record deleted');
                throw $e;
            }
        }, 5); // 5 retry attempts for deadlocks
    }

    /**
     * Generate a unique sale number
     */
    private function generateSaleNumber()
    {
        $prefix = 'SAL';
        $date = now()->format('Ymd');
        $lastSale = Sale::whereDate('created_at', today())->count();
        
        return $prefix . '-' . $date . '-' . str_pad($lastSale + 1, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Clear inventory cache
     */
    private function clearInventoryCache($storeId, $productId = null)
    {
        Cache::forget("inventory.{$storeId}." . ($productId ?? 'all'));
        
        if ($productId) {
            Cache::forget("inventory.{$storeId}.all");
        }
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
                        $item->name,
                        $item->available_quantity,
                        $item['quantity']
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
            // Generate adjustment number
            $adjustmentNumber = StockAdjustment::generateAdjustmentNumber();
            
            
            // Create adjustment record
            $adjustment = StockAdjustment::create([
                'adjustment_number' => $adjustmentNumber,
                'store_id' => $storeId,
                'type' => $type,
                'reason' => $reason,
                'notes' => $notes,
                'created_by' => $userId
            ]);

            foreach ($items as $item) {
                // Get or create inventory
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
                $adjustmentItem = AdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
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

            // dump('Adjustment completed successfully', [
            //     'adjustment_id' => $adjustment->id,
            //     'items_processed' => count($items)
            // ]);

            return $adjustment->load('items');
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