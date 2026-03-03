<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            
            // Identification
            $table->string('slug')->unique();           // Identifiant unique du menu
            $table->string('label');                     // Libellé affiché
            $table->string('icon')->nullable();          // Icône FontAwesome/Bootstrap
            
            // Type et position
            $table->enum('type', ['sidebar', 'navbar'])->default('sidebar');
            $table->string('position')->nullable();      // left/right pour navbar
            $table->string('menu_type')->nullable();     // search/dropdown/link pour navbar
            
            // Routes et liens
            $table->string('route')->nullable();         // Nom de la route
            $table->string('url')->nullable();           // URL externe (optionnel)
            
            // Hiérarchie
            $table->foreignId('parent_id')->nullable()
                  ->constrained('menus')
                  ->onDelete('cascade');
            $table->integer('order')->default(0);        // Ordre d'affichage
            
            // Contrôle d'accès
            $table->string('permission')->nullable();    // Permission requise
            $table->boolean('is_active')->default(true); // Actif/désactivé
            $table->boolean('is_visible')->default(true);// Visible/caché (sans supprimer)
            
            // Métadonnées
            $table->json('options')->nullable();         // Options supplémentaires
            $table->json('badge')->nullable();           // Badge (texte, couleur)
            
            $table->timestamps();
            $table->softDeletes();                        // Pour archivage
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};