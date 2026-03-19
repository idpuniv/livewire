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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('payment_id')->constrained()->onDelete('cascade');
            $table->string('transaction_type');
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending');
            $table->string('gateway_reference')->nullable();
            $table->json('gateway_response')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('transaction_type');
            $table->index('status');
            $table->index('gateway_reference');
            $table->index('created_at');
            $table->index(['payment_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transanctions');
    }
};
