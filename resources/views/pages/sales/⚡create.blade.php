<?php

use Livewire\Component;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Services\ProductService;
use App\Services\CartService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;
use App\Enums\Status;
use Illuminate\Support\Facades\Log;
use App\Permissions\OrderPermissions;
use App\Permissions\PaymentPermissions;

new class extends Component {
    public string $search = '';
    public $subtotal = 0;
    public array $products = [];
    public ?Cart $cart = null;
    public ?Order $order = null;
    public bool $showQuantityModal = false;
    public $editingProductId = null;
    public $editingQuantity = 1;
    public ?string $mobilePaymentStatus = null;
    public $customer = null;

    public $hoveredProductId = null;

    protected ProductService $productService;
    protected CartService $cartService;
    protected OrderService $orderService;

    public function boot(ProductService $productService, CartService $cartService, OrderService $orderService)
    {
        $this->productService = $productService;
        $this->cartService = $cartService;
        $this->orderService = $orderService;
    }

    protected $listeners = [
        'clearCart2' => 'clearCart2',
        'refreshPay' => '$refresh',
        'orderPaid' => 'orderPaid',
        'echo:products,.product.updated' => 'handleProductUpdate',
        'echo:payments,.payment.completed' => 'handleProductUpdate',
        'showMobileReceipt' => 'setShowReceipt',
        'paymentStatus' => 'paymentStatusUpdated',
        'keyboardShortcut' => 'handleKeyboardShortcut',
        'customerSelected' => 'setCustomer',
    ];

    public function setCustomer($customerData)
    {
        $this->customer = $customerData;
        Log::info('Client sélectionné:', $customerData);
    }

    public function clearCustomerSelection()
    {
        $this->customer = null;
        $this->dispatch('clearCustomerSelection')->to('customer');
    }

    public function getCanCreateOrderProperty()
    {
        return auth()->user()?->can(OrderPermissions::CREATE) ?? false;
    }

    public function getCanPayOrderProperty()
    {
        return auth()->user()?->can(OrderPermissions::UPDATE) ?? false;
    }

    public function getCanManageCartProperty()
    {
        return auth()->user()?->can(OrderPermissions::UPDATE) ?? false;
    }

    public function setHoveredProduct($productId)
    {
        $this->hoveredProductId = $productId;
        $this->dispatch('product-hovered', productId: $productId);
    }

    public function handleKeyboardShortcut($key, $productId)
    {
        if (!$productId) {
            return;
        }

        $product = collect($this->products)->first(fn($p) => $p['id'] == $productId);
        if (!$product) {
            return;
        }

        switch ($key) {
            case '+':
            case 'Add':
                $this->addToCart($productId);
                break;

            case '-':
            case 'Subtract':
                if ($product['selected']) {
                    if ($product['quantity'] > 1) {
                        $this->removeFromCart($productId);
                    } else {
                        // Supprimer complètement
                        foreach ($this->products as &$p) {
                            if ($p['id'] == $productId) {
                                $p['quantity'] = 0;
                                $p['selected'] = false;
                                break;
                            }
                        }
                        if ($this->cart) {
                            $this->cartService->removeFromCart($this->cart, $productId);
                        }
                        $this->dispatch('refreshPay');
                    }
                }
                break;

            case 'Delete':
            case 'Del':
                if ($product['selected']) {
                    foreach ($this->products as &$p) {
                        if ($p['id'] == $productId) {
                            $p['quantity'] = 0;
                            $p['selected'] = false;
                            break;
                        }
                    }
                    if ($this->cart) {
                        $this->cartService->removeFromCart($this->cart, $productId);
                    }
                    $this->dispatch('refreshPay');
                }
                break;
        }
    }

    public function setShowReceipt()
    {
        $this->showReceiptMobile = true;
    }

    public function paymentStatusUpdated($status)
    {
        $this->mobilePaymentStatus = $status;

        // Si on reçoit un statut (success/error) et que la commande a été réinitialisée
        if (($status === 'success' || $status === 'error') && !$this->order && !$this->cart) {
            // L'offcanvas reste ouvert avec le récapitulatif
            $this->dispatch('show-payment-receipt');
        }
    }

    public function mount()
    {
        static $workerStarted = false;

        if (!$workerStarted) {
            try {
                Log::info('Tentative de lancement du worker');
                include base_path('startWorkerDynamic.php');
                $workerStarted = true;
                Log::info('Worker lancé avec succès');
            } catch (\Throwable $e) {
                Log::error('Impossible de démarrer le worker : ' . $e->getMessage());
            }
        }
        $this->loadProducts();
        $this->createCart();
        $this->syncCartItems();
        $this->dispatch('cartUpdated', cartId: $this->cart->id);
    }

    public function handleProductUpdate($payload)
    {
        Log::info('handleProductUpdate called', ['payload' => $payload]);
        $this->loadProducts();
        $this->dispatch('$refresh');
    }

    public function loadProducts()
    {
        $this->products = $this->productService->getAllProducts();
    }

    public function createCart()
    {
        $this->cart = $this->cartService->getOrCreateCart();
    }

    public function clearCart2()
    {
        foreach ($this->products as &$p) {
            $p['quantity'] = 0;
            $p['selected'] = false;
        }

        if ($this->cart) {
            $this->cartService->clearCart($this->cart);
        }
        if ($this->order) {
            $this->order = null;
        }
    }

    protected function syncCartItems()
    {
        if (!$this->cart) {
            return;
        }

        $this->cartService->syncCartItems($this->cart, $this->products);
        $this->dispatch('cartUpdated', cartId: $this->cart->id);
    }

    public function createCheckout()
    {
        if ($this->selectedCount === 0) {
            session()->flash('error', 'Le panier est vide.');
            return;
        }

        try {
            $this->order = $this->orderService->createOrderFromCart($this->cart, $this->customer);
            $orderId = $this->order->id;
            $this->clearCustomerSelection();

            $canPay = auth()->user()?->can(\App\Permissions\PaymentPermissions::CREATE) ?? false;
            if (!$canPay) {
                $this->clearCart2();
            }

            $this->createCart(); // Nouveau panier

            $this->dispatch('refreshPay');
            $this->dispatch('open-pay-offcanvas');
            $this->dispatch('showAlert', [
                'type' => 'success',
                'message' => 'Commande #' . $orderId . ' créée',
            ]);

            return $orderId;
        } catch (\Exception $e) {
            Log::error('Erreur création commande: ' . $e->getMessage());
            $this->dispatch('showAlert', [
                'type' => 'error',
                'message' => 'Erreur: ' . $e->getMessage(),
            ]);
            return null;
        }
    }

    public function getFilteredProductsProperty()
    {
        return $this->productService->filterProducts($this->products, $this->search);
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
            'editingQuantity' => 'required|integer|min:0',
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
                            $this->cartService->addToCart($this->cart, $p, $productId);
                        }
                    } else {
                        $p['quantity'] = 0;
                        $p['selected'] = false;

                        if ($this->cart) {
                            $this->cartService->removeFromCart($this->cart, $productId);
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
        $this->mobilePaymentStatus = null;
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
                $this->orderService->updateOrderWithExistingCart($this->order, $product, $productId);
                $this->dispatch('refreshPay')->to('pay');
            } else {
                $this->cartService->incrementQuantity($this->cart, $productId, $product['price']);
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
            $this->cartService->decrementQuantity($this->cart, $productId);
        }
    }

    public function clearCart()
    {
        if ($this->order && $this->order->status === Status::PENDING) {
            $this->order->delete();
            $this->order = null;
        }

        foreach ($this->products as &$p) {
            $p['quantity'] = 0;
            $p['selected'] = false;
        }

        if ($this->cart) {
            $this->cartService->clearCart($this->cart);
            $this->order = null;
        }
        $this->dispatch('refreshPay');
    }

    public function orderPaid($orderId)
    {
        $this->order = null;
    }

    public function sortSelected()
    {
        $this->products = $this->productService->sortSelected($this->products);
    }

    public function getSelectedCountProperty()
    {
        return $this->productService->getSelectedCount($this->products);
    }

    public function getCartTotalProperty()
    {
        return $this->productService->calculateCartTotal($this->products);
    }
};

?>


<div class="app-container">

    <div class="main-layout mb-5 mb-md-0">
        <aside class="left-aside layout d-none d-lg-block">
            <livewire:customer />
        </aside>
        <main class="main-section layout mb-2 mb-md-0">
            <div class="section-card h-100">

                <!-- Header -->
                <div class="product-header-custom mb-3">
                    <div class="input-group input-group-lg">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0 ps-0"
                            placeholder="Rechercher produit..." wire:model.live.debounce.300ms="search" autofocus>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <div class="text-muted">
                            <i class="fas fa-boxes me-1"></i> {{ count($this->filteredProducts) }} produits
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-light btn-sm px-3 d-flex align-items-center" wire:click="clearCart"
                                @if (!$order && !$cart) disabled @endif>
                                <i class="fas fa-times-circle me-1"></i> Vider
                                <div class="position-relative ms-2">
                                    <i class="fas fa-shopping-cart fs-5"></i>
                                    @if ($this->selectedCount > 0)
                                        <span
                                            class="position-absolute top-0 start-100 translate-middle badge rounded-circle bg-danger p-1"
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
                        @foreach ($this->filteredProducts as $product)
                            <div class="col" wire:key="product-{{ $product['id'] }}"
                                wire:mouseenter="setHoveredProduct({{ $product['id'] }})"
                                wire:mouseleave="setHoveredProduct(null)">

                                <div class="card h-100 product-card {{ $product['selected'] ? 'selected' : '' }} {{ $product['stock'] === 0 ? 'opacity-50' : '' }}"
                                    wire:click="addToCart({{ $product['id'] }})"
                                    @if ($product['stock'] === 0) disabled @endif
                                    style="cursor: pointer; position: relative;">

                                    <!-- Indicateur de survol pour les raccourcis -->
                                    @if ($hoveredProductId === $product['id'])
                                        <div class="position-absolute top-0 end-0 m-2 bg-dark bg-opacity-75 text-white px-2 py-1 rounded small"
                                            style="font-size: 10px; z-index: 10;">
                                            + / - / Del
                                        </div>
                                    @endif

                                    <!-- Image container avec position relative pour le bouton -->
                                    <div class="position-relative">

                                        <!-- Condition : image ou SVG générique -->
                                        @if ($product['image'])
                                            <img src="{{ $product['image'] }}" class="card-img-top"
                                                alt="{{ $product['name'] }}"
                                                style="height: 160px; object-fit: cover; aspect-ratio: 1/1;">
                                        @else
                                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center"
                                                style="height: 160px; aspect-ratio: 1/1;">
                                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none"
                                                    stroke="#adb5bd" stroke-width="1.2">
                                                    <!-- Boîte / carton -->
                                                    <rect x="3" y="7" width="18" height="14" rx="2"
                                                        stroke="currentColor" />
                                                    <path d="M7 7V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v2"
                                                        stroke="currentColor" />
                                                    <!-- Rabats du carton -->
                                                    <path d="M3 7L8 12M21 7L16 12M8 12L3 17M16 12L21 17"
                                                        stroke="currentColor" stroke-width="1" />
                                                    <!-- Étiquette -->
                                                    <circle cx="12" cy="12" r="2" fill="#e9ecef"
                                                        stroke="currentColor" />
                                                    <path d="M12 10V14M10 12H14" stroke="currentColor"
                                                        stroke-width="1.2" />
                                                </svg>
                                            </div>
                                        @endif

                                        <!-- Badges promotionnels -->
                                        <div class="position-absolute top-0 start-0 p-2 d-flex gap-1">
                                            @if ($product['promo_percent'] ?? false)
                                                <span class="badge bg-danger">-{{ $product['promo_percent'] }}%</span>
                                            @endif
                                            @if ($product['choice'] ?? false)
                                                <span class="badge bg-warning text-dark">Choice</span>
                                            @endif
                                        </div>

                                        <!-- Bouton ajouter / quantité -->
                                        @if ($product['selected'])
                                            <button
                                                class="position-absolute bottom-0 end-0 m-2 btn btn-primary rounded-circle d-flex align-items-center justify-content-center p-0"
                                                style="width: 32px; height: 32px;"
                                                wire:click.stop="openQuantityModal({{ $product['id'] }})">
                                                {{ $product['quantity'] }}
                                            </button>
                                        @else
                                            <button
                                                class="position-absolute bottom-0 end-0 m-2 btn btn-light rounded-circle d-flex align-items-center justify-content-center p-0 border"
                                                style="width: 32px; height: 32px;"
                                                wire:click.stop="addToCart({{ $product['id'] }})"
                                                @if ($product['stock'] === 0) disabled @endif>
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                                                    stroke="currentColor" stroke-width="2">
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
                                            @if ($product['rating'] ?? false)
                                                <div class="d-flex align-items-center gap-1 small">
                                                    <span style="color: #ffb800;">★★★★★</span>
                                                    <span class="text-muted">{{ $product['rating'] }}</span>
                                                </div>
                                            @endif
                                            @if ($product['sold_count'] ?? false)
                                                <small class="text-muted">{{ number_format($product['sold_count']) }}
                                                    vendus</small>
                                            @endif
                                        </div>

                                        <div class="d-flex justify-content-between align-items-baseline mt-1">
                                            <div>
                                                @if (($product['original_price'] ?? 0) > $product['price'])
                                                    <small class="text-muted text-decoration-line-through me-1">
                                                        {{ number_format($product['original_price'], 0) }}
                                                    </small>
                                                @endif
                                                <span
                                                    class="fw-bold {{ $product['selected'] ? 'text-primary' : 'text-success' }}">
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
                            <!-- Desktop : bouton Créer (plus tard : condition avec permission) -->
                            @can(OrderPermissions::CREATE)
                                <button class="btn btn-primary btn-sm d-none d-md-block" wire:click="createCheckout"
                                    @if ($order && auth()->user()->can(\App\Permissions\PaymentPermissions::CREATE)) disabled @endif>
                                    Créer la commande
                                </button>
                            @endcan

                            <!-- Mobile : structure préparée pour les permissions -->
                            <div class="d-md-none d-flex gap-2">
                                <!-- Bouton Créer commande (toujours visible sur mobile pour l'instant) -->
                                @can(OrderPermissions::CREATE)
                                    <button class="btn btn-primary btn-sm" wire:click="createCheckout">
                                        <i class="fas fa-plus-circle me-1"></i>
                                        Créer
                                    </button>
                                @endcan

                                <!-- Bouton Payer - NE SERA AFFICHÉ QUE SI UNE COMMANDE EXISTE
                     (Plus tard on ajoutera && $canPay) -->

                                @if ($order)
                                    <button class="btn btn-success btn-sm" type="button" data-bs-toggle="offcanvas"
                                        data-bs-target="#offcanvasPay">
                                        <i class="fas fa-credit-card me-1"></i>
                                        Payer
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- MODAL POUR MODIFIER LA QUANTITÉ - COMPLET -->
                @if ($showQuantityModal)
                    <div class="modal fade show d-block" style="background-color: rgba(0,0,0,0.5); z-index: 1050;"
                        tabindex="-1" role="dialog" aria-modal="true"
                        wire:key="quantity-modal-{{ $editingProductId }}">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content border-0 shadow-lg">
                                <div class="modal-header bg-light border-bottom">
                                    <h5 class="modal-title fw-bold text-dark">
                                        <i class="fas fa-pencil-alt me-2"></i>
                                        Modifier la quantité
                                    </h5>
                                    <button type="button" class="btn-close"
                                        wire:click="closeQuantityModal"></button>
                                </div>

                                <form wire:submit.prevent="saveQuantity">
                                    <div class="modal-body">
                                        @php
                                            $product = collect($products)->first(
                                                fn($p) => $p['id'] == $editingProductId,
                                            );
                                        @endphp

                                        @if ($product)
                                            <div class="mb-4">
                                                <div class="d-flex align-items-center mb-3">
                                                    @if ($product['image'])
                                                        <img src="{{ $product['image'] }}"
                                                            alt="{{ $product['name'] }}" class="rounded me-3"
                                                            style="width: 60px; height: 60px; object-fit: cover;">
                                                    @else
                                                        <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center"
                                                            style="width: 60px; height: 60px;">
                                                            <i class="fas fa-box text-muted fa-2x"></i>
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <div class="fw-bold">{{ $product['name'] }}</div>
                                                        <div class="text-muted small">Code: {{ $product['code'] }}
                                                        </div>
                                                        <div class="text-success fw-bold">
                                                            {{ number_format($product['price'], 2) }}</div>
                                                    </div>
                                                </div>

                                                <label class="form-label fw-semibold">Quantité</label>
                                                <div class="d-flex align-items-center gap-3">
                                                    <button type="button" class="btn btn-outline-secondary"
                                                        wire:click="$set('editingQuantity', {{ max(0, $editingQuantity - 1) }})"
                                                        @if ($editingQuantity <= 0) disabled @endif>
                                                        <i class="fas fa-minus"></i>
                                                    </button>
                                                    <input type="number"
                                                        class="form-control text-center form-control-lg"
                                                        wire:model="editingQuantity" min="0"
                                                        max="{{ $product['stock'] }}" autofocus>
                                                    <button type="button" class="btn btn-outline-secondary"
                                                        wire:click="$set('editingQuantity', {{ min($product['stock'], $editingQuantity + 1) }})"
                                                        @if ($editingQuantity >= $product['stock']) disabled @endif>
                                                        <i class="fas fa-plus"></i>
                                                    </button>
                                                </div>

                                                <div class="d-flex justify-content-between mt-3">
                                                    <div class="text-muted">
                                                        <i class="fas fa-boxes me-1"></i> Stock:
                                                        {{ $product['stock'] }}
                                                    </div>
                                                    <div class="fw-bold">
                                                        Total:
                                                        {{ number_format($editingQuantity * $product['price'], 2) }}
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    <div class="modal-footer border-top">
                                        <button type="button" class="btn btn-outline-secondary"
                                            wire:click="closeQuantityModal">
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
            <livewire:pay :order="$order" :cart="$cart" :wire:key="'pay-'.($order->id ?? 'empty')" />
        </aside>
    </div>

    <!-- OFFCANVAS POUR MOBILES - Le composant Pay DANS l'offcanvas -->
    <div class="offcanvas offcanvas-sm offcanvas-bottom d-lg-none h-75" tabindex="-1" id="offcanvasPay"
        aria-labelledby="offcanvasPayLabel" wire:ignore.self>

        <!-- HEADER -->
        <div class="offcanvas-header bg-light">
            <h5 class="offcanvas-title" id="offcanvasPayLabel">

                @if ($mobilePaymentStatus === 'success' && !$order && !$cart)
                    <span class="text-success">
                        <i class="fas fa-check-circle me-2"></i>
                        Paiement réussi
                    </span>
                @else
                    <span>Paiement</span>
                @endif

            </h5>

            <button type="button" class="btn-close" data-bs-dismiss="offcanvas">
            </button>
        </div>

        <!-- BODY -->
        <div class="offcanvas-body p-0">

            {{-- ================= SUCCESS SCREEN (RÉCAPITULATIF) ================= --}}
            @if ($mobilePaymentStatus === 'success')
                <div class="d-flex flex-column align-items-center justify-content-center h-100 py-4">

                    {{-- Petit indicateur "Dernier paiement" --}}
                    <div class="text-center mb-2">
                        <span class="badge bg-light text-secondary px-3 py-2 rounded-pill">
                            <i class="fas fa-clock me-1" style="font-size: 10px;"></i>
                            Dernier paiement
                        </span>
                    </div>

                    {{-- Icône de succès --}}
                    <div class="bg-success bg-opacity-10 rounded-circle p-3 mb-3">
                        <i class="fas fa-check-circle text-success fa-3x"></i>
                    </div>

                    {{-- Détails du paiement --}}
                    <div class="bg-light rounded-3 p-3 mb-3 w-75">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Total</span>
                            <span class="fw-bold text-primary">1,800 XOF</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Reçu</span>
                            <span class="fw-bold">2,000 XOF</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">Monnaie</span>
                            <span class="fw-bold text-success">200 XOF</span>
                        </div>
                    </div>

                    {{-- Informations client --}}
                    <div class="px-3 small w-75">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Client</span>
                            <span class="fw-medium">Emilie Dupont</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-secondary">Tél</span>
                            <span class="fw-medium">65 12 04 30</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-secondary">{{ now()->format('d/m H:i') }}</span>
                            <span class="fw-medium">#ORD-123</span>
                        </div>
                    </div>

                    {{-- Bouton pour fermer ou nouveau paiement --}}
                    <button class="btn btn-outline-secondary btn-sm px-4 mt-3"
                        wire:click="$set('mobilePaymentStatus', null)">
                        Nouveau paiement
                    </button>

                </div>

                {{-- ================= NORMAL PAYMENT ================= --}}
            @else
                <livewire:pay :order="$order" :cart="$cart"
                    :wire:key="'pay-mobile-'.($order->id ?? 'empty-'.uniqid())" />
            @endif

        </div>
    </div>

</div>

@script
    <script>
        let offcanvasInstance = null;
        let hoveredProductId = null; // Variable locale pour suivre le survol

        // Initialiser l'offcanvas une seule fois
        document.addEventListener('livewire:init', () => {
            const offcanvasEl = document.getElementById('offcanvasPay');
            if (offcanvasEl) {
                offcanvasInstance = new bootstrap.Offcanvas(offcanvasEl);

                offcanvasEl.addEventListener('hidden.bs.offcanvas', function() {
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                    const backdrop = document.querySelector('.offcanvas-backdrop');
                    if (backdrop) backdrop.remove();
                });
            }

            // Écouter les mises à jour de Livewire pour hoveredProductId
            Livewire.hook('morphed', () => {
                // Rien à faire ici
            });
        });

        // Écouter l'événement de survol envoyé par Livewire
        $wire.on('product-hovered', (data) => {
            hoveredProductId = data.productId;
            console.log('Produit survolé:', hoveredProductId);
        });

        // Raccourcis clavier
        document.addEventListener('keydown', function(e) {
            // Ignorer si on est dans un champ de saisie
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }

            if (!hoveredProductId) {
                console.log('Aucun produit survolé');
                return;
            }

            let key = e.key;
            let code = e.code;

            console.log('Touche pressée:', key, code, 'Produit:', hoveredProductId);

            // Touche PLUS
            if (key === '+' || key === '=' || code === 'NumpadAdd' || code === 'Equal') {
                e.preventDefault();
                console.log('ACTION: Ajouter au panier', hoveredProductId);
                $wire.dispatch('keyboardShortcut', {
                    key: '+',
                    productId: hoveredProductId
                });
            }

            // Touche MOINS
            else if (key === '-' || key === '_' || code === 'NumpadSubtract' || code === 'Minus') {
                e.preventDefault();
                console.log('ACTION: Retirer du panier', hoveredProductId);
                $wire.dispatch('keyboardShortcut', {
                    key: '-',
                    productId: hoveredProductId
                });
            }

            // Touche SUPPR
            else if (key === 'Delete' || key === 'Del' || code === 'Delete') {
                e.preventDefault();
                console.log('ACTION: Supprimer du panier', hoveredProductId);
                $wire.dispatch('keyboardShortcut', {
                    key: 'Delete',
                    productId: hoveredProductId
                });
            }
        });

        $wire.on('open-pay-offcanvas', () => {
            if (offcanvasInstance) {
                offcanvasInstance.hide();
                setTimeout(() => {
                    offcanvasInstance.show();
                }, 150);
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 992 && offcanvasInstance) {
                offcanvasInstance.hide();
            }
        });

        $wire.$on('livewire:navigated', () => {
            if (offcanvasInstance) {
                offcanvasInstance.hide();
                offcanvasInstance.dispose();
                offcanvasInstance = null;
            }
        });
    </script>
@endscript
