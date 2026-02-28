<?php

use App\Enums\Status;
use Livewire\Component;
use App\Models\Order;
use App\Models\Cart;
use App\Services\PaymentService;
use App\Services\CheckoutService;
use Illuminate\Support\Facades\Log;

new class() extends Component {
    public ?Order $order = null;
    public ?Cart $cart = null;

    public $amountPaid = 0;
    public float $change = 0;
    public string $paymentMethod = 'cash';
    public float $total = 0;
    
    public ?string $paymentStatus = null;
    public bool $paymentCompleted = false; // Nouveau flag

    protected PaymentService $paymentService;
    protected CheckoutService $checkoutService;

    public function boot(
        PaymentService $paymentService,
        CheckoutService $checkoutService
    ) {
        $this->paymentService = $paymentService;
        $this->checkoutService = $checkoutService;
    }

    protected $listeners = [
        'refreshPay' => 'refreshPay',
        'loadOrder'  => 'loadOrder',
        'cartUpdated' => 'refreshCart',
        'resetPaymentStatus' => 'resetPaymentStatus',
        'orderCreated' => 'handleOrderCreated'
    ];

    public function mount(?Order $order = null, ?Cart $cart = null): void
    {
        $this->order = $order;
        $this->cart = $cart;
        $this->paymentCompleted = false;
        $this->syncAmounts();
    }

    public function refreshPay(): void
    {
        if ($this->paymentCompleted) {
            return; // Ne pas rafraîchir si paiement terminé
        }

        if ($this->order) {
            $this->order = Order::with(['items', 'invoice'])->find($this->order->id);
        }
        if ($this->cart) {
            $this->cart = Cart::with(['items'])->find($this->cart->id);
        }

        $this->syncAmounts();
    }

    public function refreshCart($cartId)
    {
        if ($this->paymentCompleted) {
            return;
        }

        $this->cart = Cart::with(['items'])->find($cartId);
        $this->order = null;
        $this->syncAmounts();
    }

    public function loadOrder(int $orderId): void
    {
        if ($this->paymentCompleted) {
            return;
        }

        $this->order = Order::with(['items', 'invoice'])->find($orderId);
        $this->cart = null;
        $this->syncAmounts();
    }

    public function handleOrderCreated($orderId, $amountPaid)
    {
        Log::info('handleOrderCreated appelé', ['orderId' => $orderId, 'amountPaid' => $amountPaid]);
        
        $this->order = Order::with(['items', 'invoice'])->find($orderId);
        $this->amountPaid = $amountPaid;
        $this->syncAmounts();
        
        $this->processPayment();
    }

    public function updatedAmountPaid(): void
    {
        if (!$this->paymentCompleted) {
            $this->calculateChange();
        }
    }

    private function syncAmounts(): void
    {
        if ($this->order) {
            $this->total = $this->order->invoice?->total ?? 0;
        } elseif ($this->cart) {
            $this->total = $this->cart->total ?? 0;
        } else {
            $this->total = 0;
        }
        
        $this->calculateChange();
    }

    private function calculateChange(): void
    {
        $this->change = $this->paymentService->calculateChange(
            floatval($this->amountPaid ?? 0),
            $this->total
        );
    }

    public function getCanPayProperty()
    {
        if ($this->paymentCompleted) {
            return false;
        }

        // Cas 1 : Commande existante
        if ($this->order) {
            if (!$this->order->invoice) {
                return false;
            }
            if ($this->paymentMethod === 'cash') {
                return $this->amountPaid >= $this->total;
            }
            return true;
        }
        
        // Cas 2 : Panier avec articles
        if ($this->cart && $this->cart->items()->count() > 0) {
            if ($this->paymentMethod === 'cash') {
                return $this->amountPaid >= $this->total;
            }
            return true;
        }

        return false;
    }

    public function pay(): void
    {
        if ($this->paymentCompleted) {
            return;
        }

        // Cas 1 : Paiement d'une commande existante
        if ($this->order) {
            $this->processPayment();
            return;
        }

        // Cas 2 : Création + Paiement d'un panier
        if ($this->cart) {
            if ($this->cart->items()->count() === 0) {
                session()->flash('error', 'Le panier est vide.');
                return;
            }

            if ($this->paymentMethod === 'cash' && $this->amountPaid < $this->total) {
                session()->flash('error', 'Montant insuffisant.');
                return;
            }

            // Utiliser CheckoutService pour créer ET payer en une transaction
            $result = $this->checkoutService->createOrderAndPay(
                $this->cart,
                floatval($this->amountPaid ?? 0),
                $this->paymentMethod
            );

            if ($result['success']) {
                $this->paymentStatus = 'success';
                $this->paymentCompleted = true;
                
                // Sauvegarder le message avant de nettoyer
                $successMessage = $result['message'];
                
                $this->dispatch('clearCart2');
                
                // NE PAS réinitialiser order et cart immédiatement
                // pour éviter les erreurs de rendu
                $this->order = null;
                $this->cart = null;
                $this->syncAmounts();

                session()->flash('success', $successMessage);
                $this->dispatch('startTimer');
                
            } else {
                $this->paymentStatus = 'error';
                session()->flash('error', $result['message']);
                $this->dispatch('startTimer');
            }
            
            return;
        }

        session()->flash('error', 'Aucune commande ou panier à traiter.');
    }

    public function processPayment(): void
    {
        if (!$this->order || !$this->order->invoice) {
            session()->flash('error', 'Aucune commande à payer.');
            return;
        }

        $result = $this->paymentService->processPayment(
            $this->order,
            floatval($this->amountPaid ?? 0),
            $this->paymentMethod
        );

        if ($result['success']) {
            $this->paymentStatus = 'success';
            $this->paymentCompleted = true;
            
            // Sauvegarder l'ID avant de nettoyer
            $orderId = $this->order->id;
            
            $this->dispatch('clearCart2');
            
            // NE PAS réinitialiser immédiatement
            $this->order = null;
            $this->syncAmounts();

            session()->flash('success', 'Commande #' . $orderId . ' payée avec succès.');
            $this->dispatch('startTimer');
            
        } else {
            $this->paymentStatus = 'error';
            session()->flash('error', $result['message']);
            $this->dispatch('startTimer');
        }
    }
    
    public function resetPaymentStatus()
    {
        $this->paymentStatus = null;
        $this->paymentCompleted = false;
    }
};

?>

<div class="card border-0 shadow-sm rounded-10 h-100">
    {{-- Header --}}
    <div class="card-header d-none bg-white border-0 pt-4 px-4">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold">
                <i class="fas fa-credit-card me-2 text-primary"></i>
                Paiement
            </h5>
            @if($order && !$paymentCompleted)
            <span class="badge bg-dark text-white px-3 py-2 rounded-pill">
                N° {{ $order->id }}
            </span>
            @endif
        </div>
    </div>

    {{-- Body --}}
    <div class="card-body px-4">
        @if($paymentStatus === 'success' && !$order && !$cart)
            <div class="text-center py-5">
                <div class="bg-success bg-opacity-10 rounded-circle p-4 d-inline-block mb-3">
                    <i class="fas fa-check-circle text-success fa-4x"></i>
                </div>
                <h5 class="text-success mb-3">Paiement réussi !</h5>
                <p class="text-muted mb-4">La transaction a été effectuée avec succès.</p>
                <button class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">
                    Fermer
                </button>
            </div>
        @elseif($order || $cart)
            {{-- Récapitulatif --}}
            <div class="mb-4">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Sous-total</span>
                    <span class="fw-medium">{{ number_format($order?->invoice?->subtotal ?? $cart?->subtotal ?? 0, 2) }} XOF</span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-secondary">TVA (20%)</span>
                    <span class="fw-medium text-danger">{{ number_format($order?->invoice?->tax ?? $cart?->tax ?? 0, 2) }} XOF</span>
                </div>
            </div>

            {{-- Total --}}
            <div class="bg-light rounded-3 p-3 mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">Total à payer</span>
                    <span class="h4 mb-0 text-primary fw-bold">{{ number_format($total, 2) }} XOF</span>
                </div>
                <small class="text-secondary d-block mt-1">
                    {{ $order?->items?->count() ?? $cart?->items?->count() ?? 0 }} articles •
                    {{ $order?->items?->sum('quantity') ?? $cart?->items?->sum('quantity') ?? 0 }} unités
                </small>
            </div>

            {{-- Montant remis avec message en hauteur fixe --}}
            <div class="mb-3">
                <label class="form-label small fw-medium text-secondary mb-1">Montant remis</label>
                <div class="input-group">
                    <input
                        type="number"
                        class="form-control form-control-lg bg-light border-0"
                        placeholder="0"
                        step="0.01"
                        wire:model.live="amountPaid"
                        @if($paymentCompleted) disabled @endif />
                    <span class="input-group-text bg-light border-0 text-secondary">XOF</span>
                </div>
                <div class="mt-2 small" style="height: 20px;">
                    @if($amountPaid > 0 && !$paymentCompleted)
                    <span class="{{ $amountPaid >= $total ? 'text-success' : 'text-danger' }}">
                        <i class="fas fa-{{ $amountPaid >= $total ? 'check-circle' : 'exclamation-circle' }} me-1"></i>
                        {{ $amountPaid >= $total ? 'Montant suffisant' : 'Montant insuffisant' }}
                    </span>
                    @endif
                </div>
            </div>

            {{-- Monnaie --}}
            <div class="bg-success bg-opacity-10 rounded-3 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-success small fw-medium">Monnaie à rendre</span>
                    <span class="h5 mb-0 text-success fw-bold">{{ number_format($change, 2) }} XOF</span>
                </div>
            </div>

            {{-- Zone à hauteur fixe pour l'icône de statut --}}
            <div class="d-none d-md-block" style="height: 120px; position: relative;">
                @if($paymentStatus && !$paymentCompleted)
                <div id="payment-status-icon" class="position-absolute start-50 translate-middle-x" style="top: 150px;">
                    <div class="position-relative">
                        <div class="position-absolute top-50 start-50 translate-middle 
                                    {{ $paymentStatus === 'success' ? 'bg-success' : 'bg-danger' }} bg-opacity-10 rounded-circle"
                             style="width: 70px; height: 70px;">
                        </div>

                        <div class="position-relative {{ $paymentStatus === 'success' ? 'bg-success' : 'bg-danger' }} rounded-circle d-flex align-items-center justify-content-center"
                             style="width: 50px; height: 50px;">
                            <i class="fas {{ $paymentStatus === 'success' ? 'fa-shopping-cart' : 'fa-exclamation-triangle' }} text-white fa-lg"></i>
                            
                            <div class="position-absolute" style="top: -8px; right: -8px;">
                                <span class="bg-white rounded-circle d-flex align-items-center justify-content-center shadow-sm" 
                                      style="width: 22px; height: 22px; border: 2px solid {{ $paymentStatus === 'success' ? '#198754' : '#dc3545' }};">
                                    <i class="fas {{ $paymentStatus === 'success' ? 'fa-check' : 'fa-times' }} {{ $paymentStatus === 'success' ? 'text-success' : 'text-danger' }}" style="font-size: 12px;"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        @else
            <div class="text-center py-5 text-muted">
                <i class="fas fa-shopping-cart fa-4x mb-3"></i>
                <p>Aucun article à payer</p>
            </div>
        @endif
    </div>

    {{-- Footer --}}
    <div class="card-footer bg-white border-0 pb-4 px-4">
        @if($paymentStatus === 'success' && !$order && !$cart)
            <button class="btn btn-outline-secondary w-100 py-3" data-bs-dismiss="offcanvas">
                Fermer
            </button>
        @else
            <button
                type="button"
                @if(!$this->canPay || $paymentCompleted) disabled @endif
                class="btn btn-primary w-100 py-3 fw-medium {{ !$this->canPay ? 'opacity-50' : '' }}"
                wire:click="pay">
                <i class="fas fa-check me-2"></i>
                @if($order)
                    Confirmer le paiement
                @else
                    Créer et payer
                @endif
            </button>
        @endif
    </div>
</div>

<script>
document.addEventListener('livewire:init', function() {
    let timer;
    
    Livewire.on('startTimer', function() {
        if (timer) clearTimeout(timer);
        timer = setTimeout(function() {
            Livewire.dispatch('resetPaymentStatus');
        }, 3000);
    });
});
</script>