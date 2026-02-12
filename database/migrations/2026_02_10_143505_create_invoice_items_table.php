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
    Schema::create('invoice_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->string('product_name');
    $table->string('product_code');
    $table->decimal('unit_price', 10, 2);
    $table->integer('quantity');
    $table->decimal('total_price', 10, 2);
    $table->decimal('tax_rate', 5, 2)->default(20.00); // TVA 20%
    $table->decimal('tax_amount', 10, 2);
    $table->timestamps();
    
    $table->index('invoice_id');
    $table->index('product_id');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
