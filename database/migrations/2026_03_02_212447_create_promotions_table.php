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
        Schema::create('promotions', function (Blueprint $table) {
    $table->id();

    $table->string('name');
    $table->text('description')->nullable();

    // Type de remise
    $table->enum('discount_type', ['percent', 'fixed']);
    $table->decimal('discount_value', 10, 2);

    // Comment elle s’applique
    $table->enum('application_type', [
        'per_item',
        'bundle_quantity',
        'cart_total'
    ]);

    $table->integer('bundle_quantity')->nullable();

    $table->enum('scope', ['product', 'category', 'cart']);
    $table->integer('priority')->default(1);
    $table->boolean('is_stackable')->default(false);
    $table->decimal('min_cart_amount', 10, 2)->nullable();

    // Dates
    $table->timestamp('starts_at')->nullable();
    $table->timestamp('ends_at')->nullable();

    $table->boolean('is_active')->default(true);

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
