<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductService
{
    public function getAllProducts(): array
    {
        $products = Product::all()->toArray();
        
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
        }, $products);
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