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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number')->unique();
            $table->enum('movement_type', [
                'SALE',
                'TRANSFER', 
                'ADJUSTMENT',
                'PROCUREMENT',
                'RETURN',
                'DAMAGE',
                'LOST'
            ]);

            $table->foreignId('product_id')->constrained();
            $table->foreignId('from_store_id')->nullable()->constrained('stores');
            $table->foreignId('to_store_id')->nullable()->constrained('stores');
            
            $table->integer('quantity');
            $table->integer('previous_quantity')->nullable();
            $table->integer('new_quantity')->nullable();
            
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();

            // Polymorphic reference to source document
            $table->string('reference_type')->nullable(); // Sale, Transfer, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            
            $table->foreignId('created_by')->constrained('users');
            $table->string('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['movement_type', 'created_at']);
            $table->index(['product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('reference_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
