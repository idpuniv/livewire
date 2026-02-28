<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductService
{
    public function getAllProducts(): array
    {
        $dbProducts = Product::all()->toArray();
        
        if (empty($dbProducts)) {
            return $this->createTestProducts();
        }
        
        return array_map(function ($product) {
            return [
                'id' => $product['id'],
                'name' => $product['name'],
                'code' => $product['code'] ?? '',
                'price' => floatval($product['price']),
                'stock' => intval($product['stock'] ?? 0),
                'image' => $product['image'] ?? '',
                'selected' => false,
                'quantity' => 0
            ];
        }, $dbProducts);
    }

    public function createTestProducts(): array
    {
        $testProducts = [
            ['name' => 'Lait 1L UHT entier', 'code' => '001', 'price' => 800,  'stock' => 50, 'image' => ''],
            ['name' => 'Pain de campagne', 'code' => '002', 'price' => 700,  'stock' => 30, 'image' => 'https://images.unsplash.com/photo-1549931319-a545dcf3bc73?auto=format&fit=crop&w=400&q=80'],
            ['name' => "Jus d'orange pressé 1L", 'code' => '003', 'price' => 1500, 'stock' => 25, 'image' => 'https://images.unsplash.com/photo-1629626720165-c408e98b4e70?auto=format&fit=crop&w=400&q=80'],
            ['name' => 'Pâtes spaghetti 500g', 'code' => '004', 'price' => 1000, 'stock' => 40, 'image' => 'https://images.unsplash.com/photo-1621996346565-e3dbc353d2e5?auto=format&fit=crop&w=400&q=80'],
            ['name' => 'Steak haché 15% MG', 'code' => '005', 'price' => 3200, 'stock' => 20, 'image' => 'https://images.unsplash.com/photo-1546833999-b9f581a1996d?auto=format&fit=crop&w=400&q=80'],
            ['name' => 'Tomates bio 1kg', 'code' => '006', 'price' => 2100, 'stock' => 35, 'image' => 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=400&q=80'],
        ];

        $products = [];
        foreach ($testProducts as $productData) {
            $product = Product::create($productData);
            $products[] = [
                'id' => $product->id,
                'name' => $product->name,
                'code' => $product->code,
                'price' => floatval($product->price),
                'stock' => intval($product->stock),
                'image' => $product->image,
                'selected' => false,
                'quantity' => 0
            ];
        }
        
        return $products;
    }

    public function filterProducts(array $products, string $search): array
    {
        if (!$search) {
            return $products;
        }

        $search = strtolower($search);
        return array_filter(
            $products,
            fn ($p) =>
            str_contains(strtolower($p['name']), $search) ||
                str_contains(strtolower($p['code']), $search)
        );
    }

    public function sortSelected(array $products): array
    {
        $selected = [];
        $notSelected = [];

        foreach ($products as $product) {
            if ($product['selected']) {
                $selected[] = $product;
            } else {
                $notSelected[] = $product;
            }
        }

        usort($selected, function ($a, $b) {
            $totalA = $a['price'] * $a['quantity'];
            $totalB = $b['price'] * $b['quantity'];
            return $totalB <=> $totalA;
        });

        return array_merge($selected, $notSelected);
    }

    public function calculateCartTotal(array $products): float
    {
        return array_sum(array_map(
            fn ($p) => $p['selected'] ? $p['price'] * $p['quantity'] : 0, 
            $products
        ));
    }

    public function getSelectedCount(array $products): int
    {
        return count(array_filter($products, fn ($p) => $p['selected']));
    }
}