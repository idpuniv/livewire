<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        // Générer un nom de produit
        $name = $this->faker->unique()->words(3, true);

        return [
            'name' => $name,
            'code' => strtoupper(Str::random(8)), // Code unique aléatoire
            'barcode' => $this->faker->unique()->ean13(), // Code-barres EAN-13
            'image' => null, // Ou 'products/' . $this->faker->image('public/storage/products', 400, 300, null, false)
            'description' => $this->faker->paragraph(3),
            'price' => $this->faker->randomFloat(2, 1000, 1000000), // Entre 1000 et 1M FCFA
            'tva_rate' => $this->faker->randomElement([0, 5.5, 10, 18, 20]), // Taux TVA courants
            'stock' => $this->faker->numberBetween(0, 1000),
            'published_at' => $this->faker->optional(0.8)->dateTimeBetween('-1 year', 'now'), // 80% publiés
        ];
    }

    /**
     * Indiquer que le produit est en stock (stock > 0)
     */
    public function inStock(): static
    {
        return $this->state(fn(array $attributes) => [
            'stock' => $this->faker->numberBetween(1, 1000),
        ]);
    }

    /**
     * Indiquer que le produit est en rupture de stock
     */
    public function outOfStock(): static
    {
        return $this->state(fn(array $attributes) => [
            'stock' => 0,
        ]);
    }

    /**
     * Indiquer que le produit a un stock faible (< 10)
     */
    public function lowStock(): static
    {
        return $this->state(fn(array $attributes) => [
            'stock' => $this->faker->numberBetween(1, 9),
        ]);
    }

    /**
     * Indiquer que le produit est publié
     */
    public function published(): static
    {
        return $this->state(fn(array $attributes) => [
            'published_at' => now(),
        ]);
    }

    /**
     * Indiquer que le produit est non publié
     */
    public function unpublished(): static
    {
        return $this->state(fn(array $attributes) => [
            'published_at' => null,
        ]);
    }

    /**
     * Indiquer que le produit a un code-barres
     */
    public function withBarcode(): static
    {
        return $this->state(fn(array $attributes) => [
            'barcode' => $this->faker->unique()->ean13(),
        ]);
    }

    /**
     * Indiquer que le produit a une image générée
     */
    public function withImage(): static
    {
        return $this->state(function (array $attributes) {
            // Créer le dossier s'il n'existe pas
            $imagePath = storage_path('app/public/products');
            if (!file_exists($imagePath)) {
                mkdir($imagePath, 0755, true);
            }

            // Générer l'image
            $imageFile = $this->faker->image($imagePath, 400, 300, null, false);

            return [
                'image' => 'products/' . $imageFile,
            ];
        });
    }

    /**
     * Indiquer que le produit a une image avec URL
     */
    public function withImageUrl(string $url): static
    {
        return $this->state(fn(array $attributes) => [
            'image' => $url,
        ]);
    }
}
