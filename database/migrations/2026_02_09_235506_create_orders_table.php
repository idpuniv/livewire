<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('status', ['pending', 'confirmed'])->default('pending');
            $table->decimal('amount_paid', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('orders');
    }
};

