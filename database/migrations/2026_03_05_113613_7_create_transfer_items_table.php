<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->integer('quantity_requested');
            $table->integer('quantity_shipped')->nullable();
            $table->integer('quantity_received')->nullable();
            $table->enum('status', ['PENDING', 'SHIPPED', 'RECEIVED', 'PARTIAL'])->default('PENDING');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['transfer_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_items');
    }
};
