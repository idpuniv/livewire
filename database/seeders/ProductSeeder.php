<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    protected $testProducts = [
        [
            'name' => 'Lait 1L UHT entier',
            'code' => '001',
            'price' => 800,
            'stock' => 50,
            'tva_rate' => 5.5,
            'image_url' => '', // Pas d'image
        ],
        [
            'name' => 'Pain de campagne',
            'code' => '002',
            'price' => 700,
            'stock' => 30,
            'tva_rate' => 5.5,
            'image_url' => 'https://images.unsplash.com/photo-1549931319-a545dcf3bc73?auto=format&fit=crop&w=400&q=80',
        ],
        [
            'name' => "Jus d'orange pressé 1L",
            'code' => '003',
            'price' => 1500,
            'stock' => 25,
            'tva_rate' => 20,
            'image_url' => 'https://images.unsplash.com/photo-1629626720165-c408e98b4e70?auto=format&fit=crop&w=400&q=80',
        ],
        [
            'name' => 'Pâtes spaghetti 500g',
            'code' => '004',
            'price' => 1000,
            'stock' => 40,
            'tva_rate' => 5.5,
            'image_url' => 'https://images.unsplash.com/photo-1621996346565-e3dbc353d2e5?auto=format&fit=crop&w=400&q=80',
        ],
        [
            'name' => 'Steak haché 15% MG',
            'code' => '005',
            'price' => 3200,
            'stock' => 20,
            'tva_rate' => 5.5,
            'image_url' => 'https://images.unsplash.com/photo-1546833999-b9f581a1996d?auto=format&fit=crop&w=400&q=80',
        ],
        [
            'name' => 'Tomates bio 1kg',
            'code' => '006',
            'price' => 2100,
            'stock' => 35,
            'tva_rate' => 5.5,
            'image_url' => 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=400&q=80',
        ],
    ];

    /**
     * Génère un code-barres EAN-13 valide
     */
    private function generateEan13(): string
    {
        $code = '2' . Str::padLeft(rand(0, 999999999), 11, '0');
        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $sum += ($i % 2 === 0) ? (int)$code[$i] : (int)$code[$i] * 3;
        }

        $checksum = (10 - ($sum % 10)) % 10;

        return $code . $checksum;
    }

    public function run(): void
    {
        // Vider la table
        Product::truncate();

        foreach ($this->testProducts as $productData) {
            // Données de base - SANS image générée
            Product::create([
                'name' => $productData['name'],
                'code' => $productData['code'],
                'barcode' => $this->generateEan13(),
                'description' => "Description du " . strtolower($productData['name']),
                'price' => $productData['price'],
                'tva_rate' => $productData['tva_rate'],
                'stock' => $productData['stock'],
                'image' => $productData['image_url'] ?: null, // URL ou null
                'published_at' => now(),
            ]);
        }

        // Produits supplémentaires - SANS images
        Product::factory(10)
            ->published()
            ->create([
                'image' => null, // Pas d'image
            ]);
    }
}
