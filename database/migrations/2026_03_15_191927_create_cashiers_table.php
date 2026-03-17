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
        Schema::create('cashiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('people_id')->constrained()->onDelete('cascade');
            $table->string('cashier_number')->unique();
            $table->foreignId('pos_id')->nullable()->constrained()->nullOnDelete(); // Point de vente assigné
            $table->decimal('opening_balance', 10, 2)->default(0); // Fonds de caisse initial
            $table->time('shift_start')->nullable(); // Début de service
            $table->time('shift_end')->nullable(); // Fin de service
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cashiers');
    }
};
