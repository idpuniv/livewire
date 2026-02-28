<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class CartService
{
    public function getOrCreateCart(): Cart
    {
        return Cart::firstOrCreate([
            'user_id' => Auth::id() ?? 1,
            'status' => 'pending'
        ]);
    }

    public function syncCartItems(Cart $cart, array &$products): void
    {
        $items = $cart->items()->with('product')->get();

        foreach ($products as &$p) {
            $cartItem = $items->firstWhere('product_id', $p['id']);
            if ($cartItem) {
                $p['selected'] = true;
                $p['quantity'] = $cartItem->quantity;
            }
        }
    }

    public function addToCart(Cart $cart, array $product, int $productId): void
    {
        CartItem::updateOrCreate(
            [
                'cart_id' => $cart->id,
                'product_id' => $productId
            ],
            [
                'quantity' => $product['quantity'],
                'price' => $product['price']
            ]
        );
    }

    public function removeFromCart(Cart $cart, int $productId): void
    {
        CartItem::where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->delete();
    }

    public function clearCart(Cart $cart): void
    {
        CartItem::where('cart_id', $cart->id)->delete();
    }

    public function incrementQuantity(Cart $cart, int $productId, float $price): void
    {
        $item = CartItem::firstOrCreate(
            [
                'cart_id' => $cart->id,
                'product_id' => $productId
            ],
            [
                'quantity' => 0,
                'price' => $price
            ]
        );

        $item->quantity += 1;
        $item->save();
    }

    public function decrementQuantity(Cart $cart, int $productId): void
    {
        $item = CartItem::where('cart_id', $cart->id)
            ->where('product_id', $productId)
            ->first();
            
        if ($item) {
            $item->quantity -= 1;
            if ($item->quantity <= 0) {
                $item->delete();
            } else {
                $item->save();
            }
        }
    }
}