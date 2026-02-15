<?php

use App\Enums\Status;
use Livewire\Component;
use App\Models\Order;
use App\Models\Cart;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Events\PaymentCompleted;

new class() extends Component {
    public ?Order $order = null;

    public $amountPaid = 0;
    public float $change = 0;
    public string $paymentMethod = 'cash';
    public ?Cart $cart = null;
    
    public ?string $paymentStatus = null;

    protected $listeners = [
        'refreshPay' => 'refreshPay',
        'loadOrder'  => 'loadOrder',
        'cartUpdated' => 'refreshCart',
        'resetPaymentStatus' => 'resetPaymentStatus'
    ];

    public function mount(?Order $order = null): void
    {
        $this->order = $order;
        $this->syncAmounts();
    }

    public function refreshPay(): void
    {
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
        $this->cart = Cart::find($cartId);
    }

    public function loadOrder(int $orderId): void
    {
        $this->order = Order::with(['items', 'invoice'])->find($orderId);
        $this->syncAmounts();
    }

    public function updatedAmountPaid(): void
    {
        $this->calculateChange();
    }

    private function syncAmounts(): void
    {
        $total = $this->order?->invoice?->total ?? 0;
        $this->amountPaid = $total;
        $this->change = 0;
    }

    private function calculateChange(): void
    {
        $total = floatval($this->order?->invoice?->total ?? 0);
        $amountPaid = floatval($this->amountPaid ?? 0);
        $this->change = max($amountPaid - $total, 0);
    }

    public function pay(): void
    {
        if (!$this->order || !$this->order->invoice) {
            session()->flash('error', 'Aucune commande à payer.');
            return;
        }

        $total = $this->order->invoice->total;

        if ($this->paymentMethod === 'cash' && $this->amountPaid < $total) {
            session()->flash('error', 'Montant insuffisant.');
            return;
        }

        try {
            DB::beginTransaction();

            $payment = Payment::create([
                'order_id'  => $this->order->id,
                'user_id'   => Auth::id(),
                'method'    => $this->paymentMethod,
                'amount'    => $total,
                'status'    => Status::SUCCESS,
            ]);

            Transaction::create([
                'payment_id'       => $payment->id,
                'transaction_type' => 'payment',
                'amount'           => $total,
                'status'           => Status::SUCCESS,
            ]);

            $this->order->update([
                'status'       => Status::CONFIRMED,
                'amount_paid'  => $total,
            ]);

            $this->order->invoice->update([
                'status' => Status::PAID,
            ]);

            DB::commit();
            event(new PaymentCompleted($payment));
            
            $this->paymentStatus = 'success';
            
            $this->order = Order::with(['items', 'invoice'])->find($this->order->id);
            $this->dispatch('clearCart2');
            $this->order = null;
            $this->syncAmounts();

            session()->flash('success', 'Paiement effectué avec succès.');
            
            // Déclencher le timer pour cacher l'icône
            $this->dispatch('startTimer');
            
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);
            
            $this->paymentStatus = 'error';
            session()->flash('error', 'Erreur lors du paiement.');
            
            // Déclencher le timer pour cacher l'icône
            $this->dispatch('startTimer');
        }
    }
    
    public function resetPaymentStatus()
    {
        $this->paymentStatus = null;
    }
};

?>

<div class="card border-0 shadow-sm rounded-10 h-100">
    {{-- Header --}}
    <div class="card-header bg-white border-0 pt-4 px-4">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-semibold">
                <i class="fas fa-credit-card me-2 text-primary"></i>
                Paiement
            </h5>
            @if($order)
            <span class="badge bg-dark text-white px-3 py-2 rounded-pill">
                N° {{ $order->id }}
            </span>
            @endif
        </div>
    </div>

    {{-- Body --}}
    <div class="card-body px-4">
        {{-- Récapitulatif --}}
        <div class="mb-4">
            <div class="d-flex justify-content-between mb-2">
                <span class="text-secondary">Sous-total</span>
                <span class="fw-medium">{{ number_format($order?->invoice?->subtotal ?? $cart->subtotal ?? 0, 2) }} XOF</span>
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
                <span class="h4 mb-0 text-primary fw-bold">{{ number_format($order?->invoice?->total ?? $cart?->total ?? 0, 2) }} XOF</span>
            </div>
            <small class="text-secondary d-block mt-1">
                {{ $order?->items?->count() ?? $cart->items?->count() ?? 0 }} articles •
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
                    wire:model.live="amountPaid" />
                <span class="input-group-text bg-light border-0 text-secondary">XOF</span>
            </div>
            {{-- Hauteur fixe pour éviter le repositionnement --}}
            <div class="mt-2 small" style="height: 24px;">
                @if($amountPaid > 0)
                <span class="{{ $amountPaid >= ($order?->invoice?->total ?? 0) ? 'text-success' : 'text-danger' }}">
                    <i class="fas fa-{{ $amountPaid >= ($order?->invoice?->total ?? 0) ? 'check-circle' : 'exclamation-circle' }} me-1"></i>
                    {{ $amountPaid >= ($order?->invoice?->total ?? 0) ? 'Montant suffisant' : 'Montant insuffisant' }}
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
        <div style="height: 120px; position: relative;">
            @if($paymentStatus)
            <div id="payment-status-icon" class="position-absolute start-50 translate-middle-x" style="top: 30px;">
                <div class="position-relative">
                    {{-- Halo/auréole --}}
                    <div class="position-absolute top-50 start-50 translate-middle 
                                {{ $paymentStatus === 'success' ? 'bg-success' : 'bg-danger' }} bg-opacity-10 rounded-circle"
                         style="width: 70px; height: 70px;">
                    </div>

                    {{-- Cercle principal --}}
                    <div class="position-relative {{ $paymentStatus === 'success' ? 'bg-success' : 'bg-danger' }} rounded-circle d-flex align-items-center justify-content-center"
                         style="width: 50px; height: 50px;">
                        <i class="fas {{ $paymentStatus === 'success' ? 'fa-shopping-cart' : 'fa-exclamation-triangle' }} text-white fa-lg"></i>
                        
                        {{-- Petite icône sur la bordure --}}
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
    </div>

    {{-- Footer --}}
    <div class="card-footer bg-white border-0 pb-4 px-4">
        <button
            type="button"
            @if(!$order) disabled @endif
            class="btn btn-primary w-100 py-3 fw-medium"
            wire:click="pay">
            <i class="fas fa-check me-2"></i>
            Confirmer le paiement
        </button>
    </div>
</div>

<script>
document.addEventListener('livewire:init', function() {
    let timer;
    
    Livewire.on('startTimer', function() {
        // Effacer le timer précédent s'il existe
        if (timer) clearTimeout(timer);
        
        // Démarrer un nouveau timer pour cacher l'icône après 3 secondes
        timer = setTimeout(function() {
            Livewire.dispatch('resetPaymentStatus');
        }, 3000);
    });
});
</script>