<?php
namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function sell($storeId,$productId,$qty,$userId)
    {

        return DB::transaction(function() use ($storeId,$productId,$qty,$userId){

            $inventory = Inventory::where('store_id',$storeId)
                ->where('product_id',$productId)
                ->lockForUpdate()
                ->firstOrFail();

            if($inventory->quantity < $qty){
                throw new \Exception("Insufficient stock");
            }

            $inventory->decrement('quantity',$qty);

            InventoryMovement::create([
                'movement_type'=>'SALE',
                'product_id'=>$productId,
                'from_store_id'=>$storeId,
                'quantity'=>$qty,
                'created_by'=>$userId
            ]);

        });
    }
}