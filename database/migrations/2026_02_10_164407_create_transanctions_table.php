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
            $table->string('transaction_type'); // payment, refund, adjustment
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('pending'); // pending, success, failed
            $table->string('gateway_reference')->nullable(); // Référence du système externe (gateway)
            $table->json('gateway_response')->nullable(); // Réponse brute du gateway
            $table->json('metadata')->nullable(); // Données supplémentaires
            $table->timestamps();
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
