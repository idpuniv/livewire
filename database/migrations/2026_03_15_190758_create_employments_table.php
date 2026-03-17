<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('people_id')->constrained()->onDelete('cascade');
            $table->foreignId('position_id')->constrained();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('salary', 10, 2);
            $table->string('department')->nullable();
            $table->text('reason_left')->nullable(); // Motif de départ
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            
            // Index pour les recherches
            $table->index(['people_id', 'is_current']);
            $table->index(['people_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employments');
    }
};