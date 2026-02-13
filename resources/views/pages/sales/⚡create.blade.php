<?php

use Livewire\Component;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\CartItem;
use App\Models\Checkout;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Enums\Status;
use Illuminate\Support\Facades\Log;

new class() extends Component {
    public string $search = '';
    public $subtotal = 0;
    public array $products = [];
    public ?Cart $cart = null;
    public ?Order $order = null;
    public bool $showQuantityModal = false;
    public $editingProductId = null;
    public $editingQuantity = 1;
    protected $listeners = [
        'refreshPay' => '$refresh',
        'clearCart2' => 'clearCart2',
        'orderPaid' => 'orderPaid',
        'echo:products,.product.updated' => 'handleProductUpdate'
    ];

    //     protected function getListeners()
    // {
    //     return [
    //         'echo:products,.product.updated' => 'handleProductUpdate'
    //     ];
    // }

    public function mount()
    {
        $this->loadProducts();
        $this->createCart();
        $this->syncCartItems();
        $this->dispatch('cartUpdated', cartId: $this->cart->id);
    }

    public function handleProductUpdate($payload)
    {
        \Log::info('Livewire: produit mis à jour', ['payload' => $payload]);

        $updatedProduct = $payload['product'] ?? null;

        if (!$updatedProduct) {
            return;
        }

        $found = false;

        // Met à jour le produit dans le tableau products
        foreach ($this->products as $index => $product) {
            if ($product['id'] == $updatedProduct['id']) {
                $this->products[$index]['name'] = $updatedProduct['name'];
                $this->products[$index]['price'] = floatval($updatedProduct['price']);
                $this->products[$index]['stock'] = intval($updatedProduct['stock']);
                $this->products[$index]['image'] = $updatedProduct['image'] ?? $product['image'];
                $found = true;
                break;
            }
        }

        // Si le produit n'existe pas dans le tableau (nouveau produit), on recharge tout
        if (!$found) {
            $this->loadProducts();
        }

        // Force la mise à jour de la vue
        $this->dispatch('$refresh');
    }


    public function loadProducts()
    {
        $dbProducts = Product::all()->toArray();

        if (empty($dbProducts)) {
            $this->createTestProducts();
        } else {
            $this->products = array_map(function ($product) {
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
    }

    protected function createTestProducts()
    {
        $testProducts = [
            ['name' => 'Lait 1L UHT entier', 'code' => '001', 'price' => 800,  'stock' => 50, 'image' => ''],
            ['name' => 'Pain de campagne', 'code' => '002', 'price' => 700,  'stock' => 30, 'image' => 'https://images.unsplash.com/photo-1549931319-a545dcf3bc73?auto=format&fit=crop&w=400&q=80'],
            ['name' => "Jus d'orange pressé 1L", 'code' => '003', 'price' => 1500, 'stock' => 25, 'image' => 'https://images.unsplash.com/photo-1629626720165-c408e98b4e70?auto=format&fit=crop&w=400&q=80'],
            ['name' => 'Pâtes spaghetti 500g', 'code' => '004', 'price' => 1000, 'stock' => 40, 'image' => 'https://images.unsplash.com/photo-1621996346565-e3dbc353d2e5?auto=format&fit=crop&w=400&q=80'],
            ['name' => 'Steak haché 15% MG', 'code' => '005', 'price' => 3200, 'stock' => 20, 'image' => 'https://images.unsplash.com/photo-1546833999-b9f581a1996d?auto=format&fit=crop&w=400&q=80'],
            ['name' => 'Tomates bio 1kg', 'code' => '006', 'price' => 2100, 'stock' => 35, 'image' => 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?auto=format&fit=crop&w=400&q=80'],
        ];

        foreach ($testProducts as $index => $productData) {
            $product = Product::create($productData);
            $this->products[] = [
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
    }

    public function createCart()
    {
        $this->cart = Cart::firstOrCreate([
            'user_id' => Auth::id() ?? 1,
            'status' => 'pending'
        ]);
    }

    protected function syncCartItems()
    {
        if (!$this->cart) {
            return;
        }

        $items = $this->cart->items()->with('product')->get();

        foreach ($this->products as &$p) {
            $cartItem = $items->firstWhere('product_id', $p['id']);
            if ($cartItem) {
                $p['selected'] = true;
                $p['quantity'] = $cartItem->quantity;
            }
        }

        $this->dispatch('cartUpdated', cartId: $this->cart->id);
    }

    public function createCheckout()
    {
        if ($this->selectedCount === 0) {
            session()->flash('error', 'Le panier est vide.');
            return;
        }

        try {
            $checkoutId = DB::transaction(function () {
                $subtotal = $this->cart->subtotal;
                $tax = $this->cart->tax;
                $total = $this->cart->total;

                $checkout = Checkout::create([
                    'cart_id' => $this->cart->id,
                    'user_id' => Auth::id() ?? 1,
                    'amount' => $total,
                    'status' => 'pending'
                ]);

                $invoice = Invoice::create([
                    'checkout_id' => $checkout->id,
                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'total' => $total,
                    'status' => 'pending'
                ]);

                $this->order = Order::create([
                    'checkout_id' => $checkout->id,
                    'status' => 'pending',
                    'amount_paid' => 0,
                    'invoice_id' => $invoice->id
                ]);

                $orderItemsData = [];
                $invoiceItemsData = [];
                $stockUpdates = [];

                foreach ($this->cart->items as $item) {
                    $product = $item->product;
                    $productTotal = $item->quantity * $item->price;
                    $productTax = $productTotal * 0.20;

                    $orderItemsData[] = [
                        'order_id' => $this->order->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_code' => $product->code,
                        'unit_price' => $item->price,
                        'quantity' => $item->quantity,
                        'total_price' => $productTotal,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    $invoiceItemsData[] = [
                        'invoice_id' => $invoice->id,
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'product_code' => $product->code,
                        'unit_price' => $item->price,
                        'quantity' => $item->quantity,
                        'total_price' => $productTotal,
                        'tax_rate' => 20.00,
                        'tax_amount' => $productTax,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    // $stockUpdates[$product->id] = $item->quantity;
                }

                if (!empty($orderItemsData)) {
                    OrderItem::insert($orderItemsData);
                }
                if (!empty($invoiceItemsData)) {
                    InvoiceItem::insert($invoiceItemsData);
                }

                foreach ($stockUpdates as $productId => $quantity) {
                    Product::where('id', $productId)->decrement('stock', $quantity);
                }

                $this->cart->delete();
                $this->createCart();

                $this->dispatch('refreshPay');
                $this->dispatch('open-pay-offcanvas');

                session()->flash('success', 'Commande #' . $this->order->id);

                return $this->order->id;
            });

            return $checkoutId;
        } catch (\Exception $e) {
            session()->flash('error', 'Erreur: ' . $e->getMessage());
            return null;
        }
    }

    public function getFilteredProductsProperty()
    {
        if (!$this->search) {
            return $this->products;
        }

        $search = strtolower($this->search);
        return array_filter(
            $this->products,
            fn($p) =>
            str_contains(strtolower($p['name']), $search) ||
                str_contains(strtolower($p['code']), $search)
        );
    }

    public function openQuantityModal($productId)
    {
        $product = collect($this->products)->first(fn($p) => $p['id'] == $productId);
        if ($product) {
            $this->editingProductId = $productId;
            $this->editingQuantity = $product['quantity'] ?: 1;
            $this->showQuantityModal = true;
        }
    }

    public function saveQuantity()
    {
        $this->validate([
            'editingQuantity' => 'required|integer|min:0'
        ]);

        $productId = $this->editingProductId;
        $newQuantity = $this->editingQuantity;

        $product = collect($this->products)->first(fn($p) => $p['id'] == $productId);

        if ($product) {
            $dbProduct = Product::find($productId);
            if (!$dbProduct) {
                session()->flash('error', 'Produit non trouvé');
                $this->closeQuantityModal();
                return;
            }

            if ($newQuantity > $product['stock']) {
                $newQuantity = $product['stock'];
            }

            foreach ($this->products as &$p) {
                if ($p['id'] == $productId) {
                    if ($newQuantity > 0) {
                        $p['quantity'] = $newQuantity;
                        $p['selected'] = true;

                        if ($this->cart) {
                            $item = CartItem::updateOrCreate(
                                [
                                    'cart_id' => $this->cart->id,
                                    'product_id' => $productId
                                ],
                                [
                                    'quantity' => $newQuantity,
                                    'price' => $p['price']
                                ]
                            );
                        }
                    } else {
                        $p['quantity'] = 0;
                        $p['selected'] = false;

                        if ($this->cart) {
                            CartItem::where('cart_id', $this->cart->id)
                                ->where('product_id', $productId)
                                ->delete();
                        }
                    }
                    break;
                }
            }
        }

        $this->dispatch('refreshPay');
        $this->closeQuantityModal();
    }

    public function closeQuantityModal()
    {
        $this->showQuantityModal = false;
        $this->editingProductId = null;
        $this->editingQuantity = 1;
    }

    public function addToCart($productId)
    {
        $product = collect($this->products)->first(fn($p) => $p['id'] == $productId);
        if (!$product || $product['stock'] <= $product['quantity']) {
            return;
        }

        $dbProduct = Product::find($productId);
        if (!$dbProduct) {
            session()->flash('error', 'Produit non trouvé');
            return;
        }

        foreach ($this->products as &$p) {
            if ($p['id'] == $productId) {
                $p['selected'] = true;
                $p['quantity'] += 1;
                break;
            }
        }

        try {
            if ($this->order) {
                $item = OrderItem::firstOrCreate(
                    [
                        'order_id' => $this->order->id,
                        'product_id' => $productId
                    ],
                    [
                        'product_name' => $product['name'],
                        'product_code' => $product['code'],
                        'unit_price' => $product['price'],
                        'quantity' => 0,
                        'total_price' => 0,
                    ]
                );

                $item->quantity += 1;
                $item->total_price = $item->quantity * $item->unit_price;
                $item->save();
                $this->dispatch('refreshPay')->to('pay');

                if ($this->order->invoice) {
                    $invoiceItem = InvoiceItem::firstOrCreate(
                        [
                            'invoice_id' => $this->order->invoice->id,
                            'product_id' => $productId
                        ],
                        [
                            'product_name' => $product['name'],
                            'product_code' => $product['code'],
                            'unit_price' => $product['price'],
                            'quantity' => 0,
                            'total_price' => 0,
                            'tax_rate' => 20.0,
                            'tax_amount' => 0,
                        ]
                    );

                    $invoiceItem->quantity += 1;
                    $invoiceItem->total_price = $invoiceItem->quantity * $invoiceItem->unit_price;
                    $invoiceItem->tax_amount = $invoiceItem->total_price * ($invoiceItem->tax_rate / 100);
                    $invoiceItem->save();
                }
            } else {
                $item = CartItem::firstOrCreate(
                    [
                        'cart_id' => $this->cart->id,
                        'product_id' => $productId
                    ],
                    [
                        'quantity' => 0,
                        'price' => $product['price']
                    ]
                );

                $item->quantity += 1;
                $item->save();
                $this->dispatch('cartUpdated', cartId: $this->cart->id);
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Erreur: ' . $e->getMessage());

            foreach ($this->products as &$p) {
                if ($p['id'] == $productId) {
                    $p['quantity'] = max(0, $p['quantity'] - 1);
                    if ($p['quantity'] == 0) {
                        $p['selected'] = false;
                    }
                    break;
                }
            }
        }
    }

    public function removeFromCart($productId)
    {
        foreach ($this->products as &$p) {
            if ($p['id'] == $productId) {
                $p['quantity'] = max(0, $p['quantity'] - 1);
                if ($p['quantity'] == 0) {
                    $p['selected'] = false;
                }
                break;
            }
        }

        if ($this->cart) {
            $item = CartItem::where('cart_id', $this->cart->id)
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

    public function clearCart()
    {
        if ($this->order && $this->order === Status::PENDING) {
            $this->order->delete();
            $this->order = null;
        }
        foreach ($this->products as &$p) {
            $p['quantity'] = 0;
            $p['selected'] = false;
        }

        if ($this->cart) {
            CartItem::where('cart_id', $this->cart->id)->delete();
            $this->order = null;
        }
        $this->dispatch('refreshPay');
    }

    public function clearCart2()
    {
        foreach ($this->products as &$p) {
            $p['quantity'] = 0;
            $p['selected'] = false;
        }

        if ($this->cart) {
            CartItem::where('cart_id', $this->cart->id)->delete();
        }
    }

    public function orderPaid($orderId)
    {
        $this->order = null;
    }

    public function sortSelected()
    {
        // Séparer les produits sélectionnés et non sélectionnés
        $selected = [];
        $notSelected = [];

        foreach ($this->products as $product) {
            if ($product['selected']) {
                $selected[] = $product;
            } else {
                $notSelected[] = $product;
            }
        }

        // Trier les sélectionnés par total (prix × quantité) décroissant
        usort($selected, function ($a, $b) {
            $totalA = $a['price'] * $a['quantity'];
            $totalB = $b['price'] * $b['quantity'];
            return $totalB <=> $totalA;
        });

        // Fusionner : sélectionnés en premier, puis non sélectionnés
        $this->products = array_merge($selected, $notSelected);
    }

    public function getSelectedCountProperty()
    {
        return count(array_filter($this->products, fn($p) => $p['selected']));
    }

    public function getCartTotalProperty()
    {
        return array_sum(array_map(fn($p) => $p['selected'] ? $p['price'] * $p['quantity'] : 0, $this->products));
    }
};

?>


<div class="app-container">

    <div class="main-layout">
        <main class="main-section layout">
            <div class="section-card h-100">

                <!-- Header -->
                <div class="product-header-custom mb-3">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0"
                            placeholder="Rechercher produit..."
                            wire:model.live.debounce.300ms="search" autofocus>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <div class="text-muted">
                            <i class="fas fa-boxes me-1"></i> {{ count($this->filteredProducts) }} produits
                        </div>
                        <div class="d-flex gap-2">
                            <button
                                class="btn btn-light btn-sm px-3 d-flex align-items-center"
                                wire:click="clearCart"
                                @if(!$order && !$cart) disabled @endif>
                                <i class="fas fa-times-circle me-1"></i> Vider
                                <div class="position-relative ms-2">
                                    <i class="fas fa-shopping-cart fs-5"></i>
                                    @if($this->selectedCount > 0)
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-circle bg-danger p-1"
                                        style="font-size: 0.6rem; min-width: 18px; height: 18px;">
                                        {{ $this->selectedCount }}
                                    </span>
                                    @endif
                                </div>
                            </button>
                            <button class="btn btn-light btn-sm px-3" wire:click="sortSelected">
                                <i class="fas fa-sort-amount-up me-1"></i> Trier
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Produits -->
                <div class="products-container">
                    <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-4 g-3">
                        @foreach($this->filteredProducts as $product)
                        <div class="col">
                            <div class="card h-100 product-card {{ $product['selected'] ? 'selected' : '' }} {{ $product['stock'] === 0 ? 'opacity-50' : '' }}"
                                wire:click="addToCart({{ $product['id'] }})"
                                @if($product['stock']===0) disabled @endif
                                style="cursor: pointer;">

                                <!-- Image container avec position relative pour le bouton -->
                                <div class="position-relative">

                                    <!-- Condition : image ou SVG générique -->
                                    @if($product['image'])
                                    <img src="{{ $product['image'] }}"
                                        class="card-img-top"
                                        alt="{{ $product['name'] }}"
                                        style="height: 160px; object-fit: cover; aspect-ratio: 1/1;">
                                    @else
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center"
                                        style="height: 160px; aspect-ratio: 1/1;">
                                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#adb5bd" stroke-width="1.2">
                                            <!-- Boîte / carton -->
                                            <rect x="3" y="7" width="18" height="14" rx="2" stroke="currentColor" />
                                            <path d="M7 7V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2" stroke="currentColor" />
                                            <!-- Rabats du carton -->
                                            <path d="M3 7L8 12M21 7L16 12M8 12L3 17M16 12L21 17" stroke="currentColor" stroke-width="1" />
                                            <!-- Étiquette -->
                                            <circle cx="12" cy="12" r="2" fill="#e9ecef" stroke="currentColor" />
                                            <path d="M12 10V14M10 12H14" stroke="currentColor" stroke-width="1.2" />
                                        </svg>
                                    </div>
                                    @endif

                                    <!-- Badges promotionnels -->
                                    <div class="position-absolute top-0 start-0 p-2 d-flex gap-1">
                                        @if($product['promo_percent'] ?? false)
                                        <span class="badge bg-danger">-{{ $product['promo_percent'] }}%</span>
                                        @endif
                                        @if($product['choice'] ?? false)
                                        <span class="badge bg-warning text-dark">Choice</span>
                                        @endif
                                    </div>

                                    <!-- Bouton ajouter / quantité -->
                                    @if($product['selected'])
                                    <button class="position-absolute bottom-0 end-0 m-2 btn btn-primary rounded-circle d-flex align-items-center justify-content-center p-0"
                                        style="width: 32px; height: 32px;"
                                        wire:click.stop="openQuantityModal({{ $product['id'] }})">
                                        {{ $product['quantity'] }}
                                    </button>
                                    @else
                                    <button class="position-absolute bottom-0 end-0 m-2 btn btn-light rounded-circle d-flex align-items-center justify-content-center p-0 border"
                                        style="width: 32px; height: 32px;"
                                        wire:click.stop="addToCart({{ $product['id'] }})"
                                        @if($product['stock']===0) disabled @endif>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M12 6V18M6 12H18" />
                                        </svg>
                                    </button>
                                    @endif
                                </div>

                                <!-- Corps de la carte -->
                                <div class="card-body p-2 d-flex flex-column">
                                    <h6 class="card-title mb-1 small fw-semibold"
                                        style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 2.4rem;">
                                        {{ $product['name'] }}
                                    </h6>

                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        @if($product['rating'] ?? false)
                                        <div class="d-flex align-items-center gap-1 small">
                                            <span style="color: #ffb800;">★★★★★</span>
                                            <span class="text-muted">{{ $product['rating'] }}</span>
                                        </div>
                                        @endif
                                        @if($product['sold_count'] ?? false)
                                        <small class="text-muted">{{ number_format($product['sold_count']) }} vendus</small>
                                        @endif
                                    </div>

                                    <div class="d-flex justify-content-between align-items-baseline mt-1">
                                        <div>
                                            @if(($product['original_price'] ?? 0) > $product['price'])
                                            <small class="text-muted text-decoration-line-through me-1">
                                                {{ number_format($product['original_price'], 0) }}
                                            </small>
                                            @endif
                                            <span class="fw-bold {{ $product['selected'] ? 'text-primary' : 'text-success' }}">
                                                {{ number_format($product['price'], 0) }} XOF
                                            </span>
                                        </div>
                                        <small class="text-{{ $product['stock'] < 5 ? 'warning' : 'muted' }}">
                                            {{ $product['stock'] }} stock
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Footer -->
                <div class="product-footer-custom mt-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <span class="text-muted">{{ count($this->filteredProducts) }} produits</span>
                            <span class="mx-2">•</span>
                            <span class="fw-bold text-primary">{{ $this->selectedCount }} sélectionnés</span>
                            <span class="mx-2">•</span>
                            <span class="fw-bold text-success">
                                Total: {{ number_format($this->cartTotal, 2) }}
                            </span>
                        </div>

                        <div class="d-flex gap-2">
                            <!-- Desktop : toujours afficher Créer (désactivé si order existe) -->
                            <button class="btn btn-primary btn-sm d-none d-md-block"
                                wire:click="createCheckout"
                                @if($order) disabled @endif>
                                Créer la commande
                            </button>

                            <!-- Mobile : afficher Créer OU Payer, jamais les deux -->
                            @if(!$order)
                            <button class="btn btn-primary btn-sm d-md-none"
                                wire:click="createCheckout">
                                Créer la commande
                            </button>
                            @else
                            <button class="btn btn-success d-md-none btn-sm" type="button"
                                data-bs-toggle="offcanvas"
                                data-bs-target="#offcanvasPay">
                                <i class="fas fa-credit-card me-1"></i>
                                Payer #{{ $order->id }}
                            </button>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- MODAL POUR MODIFIER LA QUANTITÉ - COMPLET -->
                @if($showQuantityModal)
                <div class="modal fade show d-block"
                    style="background-color: rgba(0,0,0,0.5); z-index: 1050;"
                    tabindex="-1"
                    role="dialog"
                    aria-modal="true"
                    wire:key="quantity-modal-{{ $editingProductId }}">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content border-0 shadow-lg">
                            <div class="modal-header bg-light border-bottom">
                                <h5 class="modal-title fw-bold text-dark">
                                    <i class="fas fa-pencil-alt me-2"></i>
                                    Modifier la quantité
                                </h5>
                                <button type="button" class="btn-close" wire:click="closeQuantityModal"></button>
                            </div>

                            <form wire:submit.prevent="saveQuantity">
                                <div class="modal-body">
                                    @php
                                    $product = collect($products)->first(fn($p) => $p['id'] == $editingProductId);
                                    @endphp

                                    @if($product)
                                    <div class="mb-4">
                                        <div class="d-flex align-items-center mb-3">
                                            @if($product['image'])
                                            <img src="{{ $product['image'] }}"
                                                alt="{{ $product['name'] }}"
                                                class="rounded me-3"
                                                style="width: 60px; height: 60px; object-fit: cover;">
                                            @else
                                            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center"
                                                style="width: 60px; height: 60px;">
                                                <i class="fas fa-box text-muted fa-2x"></i>
                                            </div>
                                            @endif
                                            <div>
                                                <div class="fw-bold">{{ $product['name'] }}</div>
                                                <div class="text-muted small">Code: {{ $product['code'] }}</div>
                                                <div class="text-success fw-bold">{{ number_format($product['price'], 2) }}</div>
                                            </div>
                                        </div>

                                        <label class="form-label fw-semibold">Quantité</label>
                                        <div class="d-flex align-items-center gap-3">
                                            <button type="button"
                                                class="btn btn-outline-secondary"
                                                wire:click="$set('editingQuantity', {{ max(0, $editingQuantity - 1) }})"
                                                @if($editingQuantity <=0) disabled @endif>
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="number"
                                                class="form-control text-center form-control-lg"
                                                wire:model="editingQuantity"
                                                min="0"
                                                max="{{ $product['stock'] }}"
                                                autofocus>
                                            <button type="button"
                                                class="btn btn-outline-secondary"
                                                wire:click="$set('editingQuantity', {{ min($product['stock'], $editingQuantity + 1) }})"
                                                @if($editingQuantity>= $product['stock']) disabled @endif>
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>

                                        <div class="d-flex justify-content-between mt-3">
                                            <div class="text-muted">
                                                <i class="fas fa-boxes me-1"></i> Stock: {{ $product['stock'] }}
                                            </div>
                                            <div class="fw-bold">
                                                Total: {{ number_format($editingQuantity * $product['price'], 2) }}
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                </div>

                                <div class="modal-footer border-top">
                                    <button type="button" class="btn btn-outline-secondary" wire:click="closeQuantityModal">
                                        Annuler
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Enregistrer
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endif

            </div>
        </main>

        <!-- Sidebar grands écrans - Pay toujours visible -->
        <aside class="right-aside layout d-none d-lg-block">
            <livewire:pay
                :order="$order"
                :cart="$cart"
                :wire:key="'pay-'.($order->id ?? 'empty')" />
        </aside>
    </div>

    <!-- OFFCANVAS POUR MOBILES - Le composant Pay DANS l'offcanvas -->
    <div class="offcanvas offcanvas-sm offcanvas-bottom d-lg-none h-75" tabindex="-1" id="offcanvasPay"
        aria-labelledby="offcanvasPayLabel" wire:ignore.self>
        <div class="offcanvas-header bg-light">
            <h5 class="offcanvas-title" id="offcanvasPayLabel">
                @if($order && $order->status === 'confirmed')
                <span class="text-success">
                    <i class="fas fa-check-circle me-2"></i>Commande #{{ $order->id }} payée
                </span>
                @else
                <span>Paiement</span>
                @endif
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body p-0">
            @if($order && $order->status === App\Enums\Status::CONFIRMED)
            <div class="d-flex flex-column align-items-center justify-content-center h-100 py-5">
                <div class="bg-success bg-opacity-10 rounded-circle p-4 mb-3">
                    <i class="fas fa-check text-success fa-3x"></i>
                </div>
                <h5 class="fw-bold text-success mb-1">Paiement réussi !</h5>
                <p class="text-muted mb-2">Commande #{{ $order->id }}</p>
                <p class="fw-bold text-dark fs-4 mb-3">{{ number_format($order->amount_paid ?? $order->invoice?->total ?? 0, 2) }}</p>
                <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="offcanvas">
                    Fermer
                </button>
            </div>
            @else
            <livewire:pay
                :order="$order"
                :cart="$cart"
                :wire:key="'pay-mobile-'.($order->id ?? 'empty-'.Str::random(4))" />
            @endif
        </div>
    </div>

</div>

@script
<script>
    $wire.on('open-pay-offcanvas', () => {
        let offcanvas = document.getElementById('offcanvasPay');
        if (offcanvas) {
            let bsOffcanvas = new bootstrap.Offcanvas(offcanvas);
            bsOffcanvas.show();
        }
    });

    // Sélectionner ton offcanvas
    const offcanvasEl = document.getElementById('offcanvasPay');
    const bsOffcanvas = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);

    // Écouter le redimensionnement
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 992) { // lg et plus
            bsOffcanvas.hide();
        }
    });
</script>


<script>
    $wire.on('console-log', (data) => {
        console.log('Livewire a reçu:', data);
    });

    // Pour vérifier que Livewire est bien prêt
    console.log('Livewire composant chargé');
</script>



@endscript