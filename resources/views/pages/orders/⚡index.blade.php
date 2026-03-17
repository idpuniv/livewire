<?php

use Livewire\Component;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use App\Services\ProductService;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use Illuminate\Support\Facades\Auth;
use App\Permissions\OrderPermissions;
use Illuminate\Support\Facades\Log;

new class extends Component {
    public string $search = '';
    public array $orders = [];
    public ?Order $selectedOrder = null;
    public array $orderProducts = [];
    public $subtotal = 0;
    
    // Propriétés pour le modal de modification
    public bool $showEditModal = false;
    public $editingProductId = null;
    public $editingQuantity = 1;
    
    // Propriétés pour le survol
    public $hoveredProductId = null;
    
    // Filtres
    public $statusFilter = '';
    public $dateFilter = '';

    protected OrderService $orderService;
    protected ProductService $productService;

    public function boot(OrderService $orderService, ProductService $productService)
    {
        $this->orderService = $orderService;
        $this->productService = $productService;
    }

    protected $listeners = [
        'refreshOrders' => 'loadOrders',
        'echo:orders,.order.updated' => 'handleOrderUpdate',
        'orderPaid' => 'handleOrderPaid',
        'keyboardShortcut' => 'handleKeyboardShortcut',
    ];

    public function getCanManageOrdersProperty()
    {
        return auth()->user()?->can(OrderPermissions::UPDATE) ?? false;
    }

    public function mount()
    {
        $this->loadOrders();
    }

    public function loadOrders()
    {
        $query = Order::with(['items.product', 'customer'])
            ->orderBy('created_at', 'desc');

        if ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->dateFilter) {
            $query->whereDate('created_at', $this->dateFilter);
        }

        $this->orders = $query->get()->map(function ($order) {
            return [
                'id' => $order->id,
                'reference' => $order->reference ?? '#' . $order->id,
                'customer_name' => $order->customer->name ?? 'Client sans compte',
                'customer_phone' => $order->customer->phone ?? '',
                'total' => $order->total,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'items_count' => $order->items->sum('quantity'),
                'created_at' => $order->created_at->format('d/m/Y H:i'),
                'is_modifiable' => $order->status === OrderStatus::PENDING && 
                                   $order->payment_status !== PaymentStatus::PAID,
            ];
        })->toArray();
    }

    public function handleOrderUpdate($payload)
    {
        Log::info('Order updated', ['payload' => $payload]);
        $this->loadOrders();
        $this->dispatch('$refresh');
    }

    public function handleOrderPaid($orderId)
    {
        if ($this->selectedOrder && $this->selectedOrder->id == $orderId) {
            $this->selectedOrder = null;
            $this->orderProducts = [];
        }
        $this->loadOrders();
    }

    public function selectOrder($orderId)
    {
        $this->selectedOrder = Order::with(['items.product', 'customer'])->find($orderId);
        
        if (!$this->selectedOrder) {
            return;
        }

        // Charger les produits de la commande
        $this->orderProducts = $this->selectedOrder->items->map(function ($item) {
            $product = $item->product;
            return [
                'id' => $product->id,
                'order_item_id' => $item->id,
                'name' => $product->name,
                'code' => $product->code,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
                'stock' => $product->stock,
                'image' => $product->image,
                'selected' => true,
            ];
        })->toArray();

        $this->calculateSubtotal();
        
        // Dispatch pour ouvrir le panneau des détails
        $this->dispatch('orderSelected', orderId: $orderId);
    }

    public function calculateSubtotal()
    {
        $this->subtotal = collect($this->orderProducts)->sum('subtotal');
    }

    public function setHoveredProduct($productId)
    {
        $this->hoveredProductId = $productId;
        $this->dispatch('product-hovered', productId: $productId);
    }

    public function handleKeyboardShortcut($key, $productId)
    {
        if (!$this->selectedOrder || !$this->selectedOrder->is_modifiable) {
            return;
        }

        if (!$productId) {
            return;
        }

        $product = collect($this->orderProducts)->first(fn($p) => $p['id'] == $productId);
        if (!$product) {
            return;
        }

        switch ($key) {
            case '+':
            case 'Add':
                $this->increaseQuantity($productId);
                break;

            case '-':
            case 'Subtract':
                $this->decreaseQuantity($productId);
                break;

            case 'Delete':
            case 'Del':
                $this->removeProduct($productId);
                break;
        }
    }

    public function increaseQuantity($productId)
    {
        if (!$this->selectedOrder || !$this->selectedOrder->is_modifiable) {
            return;
        }

        foreach ($this->orderProducts as &$product) {
            if ($product['id'] == $productId) {
                if ($product['quantity'] < $product['stock']) {
                    $product['quantity']++;
                    $product['subtotal'] = $product['quantity'] * $product['price'];
                    
                    $this->orderService->updateOrderItemQuantity(
                        $this->selectedOrder,
                        $productId,
                        $product['quantity']
                    );
                }
                break;
            }
        }

        $this->calculateSubtotal();
        $this->dispatch('orderUpdated');
    }

    public function decreaseQuantity($productId)
    {
        if (!$this->selectedOrder || !$this->selectedOrder->is_modifiable) {
            return;
        }

        foreach ($this->orderProducts as &$product) {
            if ($product['id'] == $productId) {
                if ($product['quantity'] > 1) {
                    $product['quantity']--;
                    $product['subtotal'] = $product['quantity'] * $product['price'];
                    
                    $this->orderService->updateOrderItemQuantity(
                        $this->selectedOrder,
                        $productId,
                        $product['quantity']
                    );
                }
                break;
            }
        }

        $this->calculateSubtotal();
        $this->dispatch('orderUpdated');
    }

    public function removeProduct($productId)
    {
        if (!$this->selectedOrder || !$this->selectedOrder->is_modifiable) {
            return;
        }

        $this->orderService->removeFromOrder($this->selectedOrder, $productId);
        
        $this->orderProducts = array_values(array_filter(
            $this->orderProducts,
            fn($p) => $p['id'] != $productId
        ));

        $this->calculateSubtotal();
        
        if (empty($this->orderProducts)) {
            $this->selectedOrder = null;
        }
        
        $this->dispatch('orderUpdated');
    }

    public function openEditModal($productId)
    {
        if (!$this->selectedOrder || !$this->selectedOrder->is_modifiable) {
            return;
        }

        $product = collect($this->orderProducts)->first(fn($p) => $p['id'] == $productId);
        if ($product) {
            $this->editingProductId = $productId;
            $this->editingQuantity = $product['quantity'];
            $this->showEditModal = true;
        }
    }

    public function saveQuantity()
    {
        $this->validate([
            'editingQuantity' => 'required|integer|min:1',
        ]);

        if (!$this->selectedOrder || !$this->selectedOrder->is_modifiable) {
            $this->closeEditModal();
            return;
        }

        $productId = $this->editingProductId;
        $newQuantity = $this->editingQuantity;

        foreach ($this->orderProducts as &$product) {
            if ($product['id'] == $productId) {
                $maxQuantity = $product['stock'];
                $newQuantity = min($newQuantity, $maxQuantity);
                
                $product['quantity'] = $newQuantity;
                $product['subtotal'] = $newQuantity * $product['price'];
                
                $this->orderService->updateOrderItemQuantity(
                    $this->selectedOrder,
                    $productId,
                    $newQuantity
                );
                break;
            }
        }

        $this->calculateSubtotal();
        $this->dispatch('orderUpdated');
        $this->closeEditModal();
    }

    public function closeEditModal()
    {
        $this->showEditModal = false;
        $this->editingProductId = null;
        $this->editingQuantity = 1;
    }

    public function cancelOrder()
    {
        if (!$this->selectedOrder || !$this->selectedOrder->is_modifiable) {
            return;
        }

        $this->orderService->cancelOrder($this->selectedOrder);
        $this->selectedOrder = null;
        $this->orderProducts = [];
        $this->loadOrders();
        $this->dispatch('showAlert', [
            'type' => 'success',
            'message' => 'Commande annulée'
        ]);
    }

    public function getFilteredOrdersProperty()
    {
        if (empty($this->search)) {
            return $this->orders;
        }

        return array_filter($this->orders, function ($order) {
            return stripos($order['reference'], $this->search) !== false ||
                   stripos($order['customer_name'], $this->search) !== false ||
                   stripos($order['customer_phone'], $this->search) !== false;
        });
    }

    public function getStatusBadgeClass($status)
    {
        return match($status) {
            OrderStatus::PENDING => 'bg-warning text-dark',
            OrderStatus::PROCESSING => 'bg-info text-white',
            OrderStatus::COMPLETED => 'bg-success text-white',
            OrderStatus::CANCELLED => 'bg-danger text-white',
            default => 'bg-secondary text-white'
        };
    }

    public function getPaymentBadgeClass($status)
    {
        return match($status) {
            PaymentStatus::PAID => 'bg-success text-white',
            PaymentStatus::PARTIAL => 'bg-info text-white',
            PaymentStatus::PENDING => 'bg-warning text-dark',
            PaymentStatus::FAILED => 'bg-danger text-white',
            default => 'bg-secondary text-white'
        };
    }
};

?>

<div class="app-container">
    <div class="main-layout mb-5 mb-md-0">
        <!-- Liste des commandes à gauche -->
        <aside class="left-aside layout d-none d-lg-block" style="width: 350px;">
            <div class="section-card h-100 p-3">
                <h5 class="mb-3">
                    <i class="fas fa-clipboard-list me-2"></i>
                    Commandes en cours
                </h5>
                
                <!-- Filtres -->
                <div class="mb-3">
                    <input type="text" 
                        class="form-control form-control-sm mb-2"
                        placeholder="Rechercher commande..."
                        wire:model.live.debounce.300ms="search">
                    
                    <div class="d-flex gap-2">
                        <select class="form-select form-select-sm" wire:model.live="statusFilter">
                            <option value="">Tous les statuts</option>
                            <option value="{{ OrderStatus::PENDING }}">En attente</option>
                            <option value="{{ OrderStatus::PROCESSING }}">En préparation</option>
                            <option value="{{ OrderStatus::COMPLETED }}">Terminées</option>
                            <option value="{{ OrderStatus::CANCELLED }}">Annulées</option>
                        </select>
                        
                        <input type="date" class="form-control form-control-sm" 
                            wire:model.live="dateFilter">
                    </div>
                </div>

                <!-- Liste des commandes -->
                <div class="orders-list" style="max-height: calc(100vh - 200px); overflow-y: auto;">
                    @forelse($this->filteredOrders as $order)
                        <div class="card mb-2 {{ $selectedOrder && $selectedOrder->id == $order['id'] ? 'border-primary' : '' }}"
                            wire:click="selectOrder({{ $order['id'] }})"
                            style="cursor: pointer;">
                            <div class="card-body p-2">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong>{{ $order['reference'] }}</strong>
                                        <div class="small text-muted">
                                            {{ $order['customer_name'] }}
                                        </div>
                                        @if($order['customer_phone'])
                                            <div class="small">
                                                <i class="fas fa-phone-alt me-1"></i>
                                                {{ $order['customer_phone'] }}
                                            </div>
                                        @endif
                                    </div>
                                    <span class="badge {{ $this->getStatusBadgeClass($order['status']) }}">
                                        {{ $order['status'] }}
                                    </span>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mt-2">
                                    <div>
                                        <span class="badge {{ $this->getPaymentBadgeClass($order['payment_status']) }} me-1">
                                            {{ $order['payment_status'] }}
                                        </span>
                                        <small class="text-muted">
                                            {{ $order['items_count'] }} art.
                                        </small>
                                    </div>
                                    <div>
                                        <strong class="text-success">
                                            {{ number_format($order['total'], 0) }} XOF
                                        </strong>
                                    </div>
                                </div>
                                
                                <div class="small text-muted mt-1">
                                    <i class="far fa-clock me-1"></i>
                                    {{ $order['created_at'] }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-box-open fa-3x mb-2"></i>
                            <p>Aucune commande trouvée</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </aside>

        <!-- Section principale : Détails de la commande sélectionnée -->
        <main class="main-section layout">
            <div class="section-card h-100">
                @if($selectedOrder)
                    <!-- En-tête de la commande -->
                    <div class="order-header-custom mb-3 p-3 bg-light rounded">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h4 class="mb-1">
                                    Commande {{ $selectedOrder->reference ?? '#' . $selectedOrder->id }}
                                </h4>
                                <div class="d-flex gap-3 mb-2">
                                    <span class="badge {{ $this->getStatusBadgeClass($selectedOrder->status) }} px-3 py-2">
                                        {{ $selectedOrder->status }}
                                    </span>
                                    <span class="badge {{ $this->getPaymentBadgeClass($selectedOrder->payment_status) }} px-3 py-2">
                                        {{ $selectedOrder->payment_status }}
                                    </span>
                                </div>
                                <p class="mb-1">
                                    <i class="fas fa-user me-2"></i>
                                    {{ $selectedOrder->customer->name ?? 'Client sans compte' }}
                                </p>
                                @if($selectedOrder->customer?->phone)
                                    <p class="mb-1">
                                        <i class="fas fa-phone me-2"></i>
                                        {{ $selectedOrder->customer->phone }}
                                    </p>
                                @endif
                                <p class="mb-0 text-muted small">
                                    <i class="far fa-calendar me-2"></i>
                                    {{ $selectedOrder->created_at->format('d/m/Y H:i') }}
                                </p>
                            </div>
                            
                            @if($selectedOrder->is_modifiable)
                                <div class="d-flex gap-2">
                                    <button class="btn btn-outline-danger btn-sm" 
                                        wire:click="cancelOrder"
                                        onclick="return confirm('Annuler cette commande ?')">
                                        <i class="fas fa-times-circle me-1"></i>
                                        Annuler
                                    </button>
                                    <button class="btn btn-success btn-sm" 
                                        wire:click="$dispatch('open-payment', { orderId: {{ $selectedOrder->id }} })">
                                        <i class="fas fa-credit-card me-1"></i>
                                        Payer
                                    </button>
                                </div>
                            @endif
                        </div>
                        
                        @if($selectedOrder->notes)
                            <div class="mt-2 p-2 bg-white rounded">
                                <small>
                                    <i class="fas fa-sticky-note me-2 text-muted"></i>
                                    {{ $selectedOrder->notes }}
                                </small>
                            </div>
                        @endif
                    </div>

                    <!-- Produits de la commande -->
                    <div class="products-container p-3">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0">
                                <i class="fas fa-boxes me-2"></i>
                                Articles ({{ count($orderProducts) }})
                            </h5>
                            @if($selectedOrder->is_modifiable)
                                <small class="text-muted">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Commandes modifiables
                                </small>
                            @endif
                        </div>

                        <div class="row g-3">
                            @foreach($orderProducts as $product)
                                <div class="col-12 col-sm-6 col-md-4 col-lg-3"
                                    wire:key="product-{{ $product['id'] }}"
                                    wire:mouseenter="setHoveredProduct({{ $product['id'] }})"
                                    wire:mouseleave="setHoveredProduct(null)">
                                    
                                    <div class="card h-100 product-card {{ $selectedOrder->is_modifiable ? 'cursor-pointer' : '' }}">
                                        <!-- Image -->
                                        <div class="position-relative">
                                            @if($product['image'])
                                                <img src="{{ $product['image'] }}" 
                                                    class="card-img-top"
                                                    alt="{{ $product['name'] }}"
                                                    style="height: 140px; object-fit: cover;">
                                            @else
                                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center"
                                                    style="height: 140px;">
                                                    <i class="fas fa-box fa-3x text-muted"></i>
                                                </div>
                                            @endif

                                            <!-- Badge quantité -->
                                            <div class="position-absolute top-0 end-0 m-2">
                                                <span class="badge bg-primary rounded-pill px-3 py-2">
                                                    {{ $product['quantity'] }}
                                                </span>
                                            </div>

                                            <!-- Indicateur de survol -->
                                            @if($hoveredProductId === $product['id'] && $selectedOrder->is_modifiable)
                                                <div class="position-absolute top-0 start-0 m-2 bg-dark bg-opacity-75 text-white px-2 py-1 rounded small">
                                                    + / - / Del
                                                </div>
                                            @endif

                                            <!-- Bouton modifier si modifiable -->
                                            @if($selectedOrder->is_modifiable)
                                                <button class="position-absolute bottom-0 end-0 m-2 btn btn-light btn-sm rounded-circle"
                                                    style="width: 32px; height: 32px;"
                                                    wire:click.stop="openEditModal({{ $product['id'] }})">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </button>
                                            @endif
                                        </div>

                                        <!-- Corps -->
                                        <div class="card-body p-2">
                                            <h6 class="card-title small fw-semibold mb-1"
                                                style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                {{ $product['name'] }}
                                            </h6>
                                            
                                            <div class="d-flex justify-content-between align-items-center mt-2">
                                                <div>
                                                    <small class="text-muted">Prix:</small>
                                                    <span class="fw-bold text-success d-block">
                                                        {{ number_format($product['price'], 0) }} XOF
                                                    </span>
                                                </div>
                                                <div class="text-end">
                                                    <small class="text-muted">Total:</small>
                                                    <span class="fw-bold text-primary d-block">
                                                        {{ number_format($product['subtotal'], 0) }} XOF
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            @if($selectedOrder->is_modifiable)
                                                <div class="small text-muted mt-1">
                                                    Stock: {{ $product['stock'] }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Pied de page avec total -->
                    <div class="order-footer-custom mt-3 p-3 bg-light rounded">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted">Total articles: {{ count($orderProducts) }}</span>
                                <span class="mx-2">•</span>
                                <span class="fw-bold text-primary">
                                    Sous-total: {{ number_format($subtotal, 0) }} XOF
                                </span>
                            </div>
                            
                            @if($selectedOrder->is_modifiable)
                                <div class="text-muted small">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    Modifications en temps réel
                                </div>
                            @endif
                        </div>
                    </div>

                @else
                    <!-- Message quand aucune commande n'est sélectionnée -->
                    <div class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                        <i class="fas fa-clipboard-list fa-4x mb-3"></i>
                        <h5>Sélectionnez une commande</h5>
                        <p class="small">Cliquez sur une commande dans la liste pour voir ses détails</p>
                    </div>
                @endif
            </div>
        </main>

        <!-- Sidebar droite pour le paiement (optionnel) -->
        <aside class="right-aside layout d-none d-lg-block" style="width: 400px;">
            @if($selectedOrder)
                <livewire:payment :order="$selectedOrder" 
                    :wire:key="'payment-'.$selectedOrder->id" />
            @else
                <div class="section-card h-100 p-3 d-flex flex-column align-items-center justify-content-center text-muted">
                    <i class="fas fa-credit-card fa-3x mb-3"></i>
                    <p>Sélectionnez une commande pour procéder au paiement</p>
                </div>
            @endif
        </aside>
    </div>

    <!-- MODAL POUR MODIFIER LA QUANTITÉ -->
    @if($showEditModal && $selectedOrder && $selectedOrder->is_modifiable)
        <div class="modal fade show d-block" style="background-color: rgba(0,0,0,0.5); z-index: 1050;">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-pencil-alt me-2"></i>
                            Modifier la quantité
                        </h5>
                        <button type="button" class="btn-close" wire:click="closeEditModal"></button>
                    </div>

                    <form wire:submit.prevent="saveQuantity">
                        <div class="modal-body">
                            @php
                                $product = collect($orderProducts)->first(
                                    fn($p) => $p['id'] == $editingProductId
                                );
                            @endphp

                            @if($product)
                                <div class="mb-3">
                                    <div class="d-flex align-items-center mb-3">
                                        @if($product['image'])
                                            <img src="{{ $product['image'] }}" 
                                                class="rounded me-3"
                                                style="width: 50px; height: 50px; object-fit: cover;">
                                        @else
                                            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center"
                                                style="width: 50px; height: 50px;">
                                                <i class="fas fa-box text-muted"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="fw-bold">{{ $product['name'] }}</div>
                                            <div class="small text-muted">
                                                Prix: {{ number_format($product['price'], 0) }} XOF
                                            </div>
                                        </div>
                                    </div>

                                    <label class="form-label">Quantité</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <button type="button" class="btn btn-outline-secondary"
                                            wire:click="$set('editingQuantity', {{ max(1, $editingQuantity - 1) }})">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" class="form-control text-center" 
                                            wire:model="editingQuantity"
                                            min="1" max="{{ $product['stock'] }}">
                                        <button type="button" class="btn btn-outline-secondary"
                                            wire:click="$set('editingQuantity', {{ min($product['stock'], $editingQuantity + 1) }})">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>

                                    <div class="d-flex justify-content-between mt-3">
                                        <span class="text-muted">
                                            Stock disponible: {{ $product['stock'] }}
                                        </span>
                                        <span class="fw-bold">
                                            Total: {{ number_format($editingQuantity * $product['price'], 0) }} XOF
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" 
                                wire:click="closeEditModal">
                                Annuler
                            </button>
                            <button type="submit" class="btn btn-primary">
                                Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @script
    <script>
        let hoveredProductId = null;

        document.addEventListener('livewire:init', () => {
            // Écouter l'événement de survol
            $wire.on('product-hovered', (data) => {
                hoveredProductId = data.productId;
            });
        });

        // Raccourcis clavier
        document.addEventListener('keydown', function(e) {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
                return;
            }

            if (!hoveredProductId) {
                return;
            }

            let key = e.key;
            let code = e.code;

            // Touche PLUS
            if (key === '+' || key === '=' || code === 'NumpadAdd' || code === 'Equal') {
                e.preventDefault();
                $wire.dispatch('keyboardShortcut', {
                    key: '+',
                    productId: hoveredProductId
                });
            }

            // Touche MOINS
            else if (key === '-' || key === '_' || code === 'NumpadSubtract' || code === 'Minus') {
                e.preventDefault();
                $wire.dispatch('keyboardShortcut', {
                    key: '-',
                    productId: hoveredProductId
                });
            }

            // Touche SUPPR
            else if (key === 'Delete' || key === 'Del' || code === 'Delete') {
                e.preventDefault();
                $wire.dispatch('keyboardShortcut', {
                    key: 'Delete',
                    productId: hoveredProductId
                });
            }
        });

        // Réinitialiser le survol quand on change de commande
        $wire.on('orderSelected', () => {
            hoveredProductId = null;
        });
    </script>
    @endscript
</div>