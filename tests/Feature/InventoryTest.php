<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Store;
use App\Models\Product;
use App\Models\Inventory;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_process_sale_concurrently()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create();
        $product = Product::factory()->create(['selling_price' => 100]);
        
        Inventory::create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 100
        ]);

        // Simulate concurrent sales
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this->actingAs($user)->postJson('/api/sales', [
                'store_id' => $store->id,
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 20]
                ]
            ]);
        }

        // Check final inventory
        $inventory = Inventory::where('store_id', $store->id)
            ->where('product_id', $product->id)
            ->first();

        $this->assertEquals(0, $inventory->quantity);
    }

    public function test_cannot_sell_more_than_available()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        
        Inventory::create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 10
        ]);

        $response = $this->actingAs($user)->postJson('/api/sales', [
            'store_id' => $store->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 15]
            ]
        ]);

        $response->assertStatus(400);
        $response->assertJson(['message' => 'Sale failed']);
    }

    public function test_audit_trail_is_created()
    {
        $user = User::factory()->create();
        $store = Store::factory()->create();
        $product = Product::factory()->create();
        
        Inventory::create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 50
        ]);

        $this->actingAs($user)->postJson('/api/sales', [
            'store_id' => $store->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 10]
            ]
        ]);

        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'from_store_id' => $store->id,
            'movement_type' => 'SALE',
            'quantity' => -10
        ]);
    }
}