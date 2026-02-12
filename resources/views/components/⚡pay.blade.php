<?php

use Livewire\Component;
use App\Models\Order;
use App\Models\Cart;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

new class () extends Component {
    public ?Order $order = null;

    public float $amountPaid = 0;
    public float $change = 0;
    public string $paymentMethod = 'cash';
    public ?Cart $cart = null;

    protected $listeners = [
        'refreshPay' => 'refreshPay',
        'loadOrder'  => 'loadOrder',
        'cartUpdated' => 'refreshCart'
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
        // Recharger la commande avec les nouvelles données
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
        $this->cart->subtotal = 0;
        $this->cart->total = 0;
        $this->change = 0;

    }

    private function calculateChange(): void
    {
        $total = $this->order?->invoice?->total ?? 0;
        $this->change = max($this->amountPaid - $total, 0);
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
                'status'    => 'success',
            ]);

            Transaction::create([
                'payment_id'       => $payment->id,
                'transaction_type' => 'payment',
                'amount'           => $total,
                'status'           => 'success',
            ]);

            $this->order->update([
                'status'       => 'confirmed',
                'amount_paid'  => $total,
            ]);

            $this->order->invoice->update([
                'status' => 'paid',
            ]);

            DB::commit();

            $this->order = Order::with(['items', 'invoice'])->find($this->order->id);

            $this->dispatch('order-paid', orderId: $this->order->id);
            $this->dispatch('clearCart2');
            $this->order = null;
            $this->syncAmounts();

            session()->flash('success', 'Paiement effectué avec succès.');

        } catch (\Throwable $e) {

            DB::rollBack();
            report($e);

            session()->flash('error', 'Erreur lors du paiement.');
        }
    }

};

?>


<div class="section-card h-100 px-2">
    <div class="card-header-custom p-3">
        <h3 class="h5 mb-0">
            <i class="fas fa-cash-register me-2"></i> Paiement - Commande
             @if($order)
                <span>#{{$order?->id}}</span>
             @endif
        </h3>
       
    </div>

    <div class="card-body-custom px-2">

        {{-- Résumé --}}
        <div class="mb-4">
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Sous-total:</span>
                <span class="fw-bold">
                    {{ number_format($order?->invoice?->subtotal ?? $cart->subtotal ?? 0, 2) }}
                </span>
            </div>

            <div class="d-flex justify-content-between mb-2">
                <span class="text-danger">TVA:</span>
                <span class="fw-bold text-danger">
                    {{ number_format($order?->invoice?->tax ?? $cart?->tax ?? 0, 2) }}
                </span>
            </div>
        </div>

        <div class="border-top pt-3 mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="fw-bold fs-5">Total à payer</div>
                    <small class="text-muted">
                        {{ $order?->items?->count() ?? $cart->items?->count() ?? 0 }} articles •
                        {{ $order?->items?->sum('quantity') ?? $cart?->items?->sum('quantity') ?? 0 }} unités
                    </small>
                </div>
                <div class="display-5 fw-bold text-primary">
                    {{ number_format($order?->invoice?->total ?? $cart?->total ?? 0, 2) }}
                </div>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Montant remis</label>

            <div class="input-group input-group-lg mb-3">
                <span class="input-group-text">XOF</span>
                <input
                    type="number"
                    class="form-control text-end fs-5"
                    step="0.01"
                    wire:model.lazy="amountPaid"
                />
            </div>

            <div class="alert alert-success text-center py-3">
                <div class="text-muted mb-1">Monnaie à rendre</div>
                <div class="fs-3 fw-bold">
                    {{ number_format($change, 2) }}
                </div>
            </div>
        </div>
    </div>

    <div class="card-footer-custom">
        <div class="d-grid gap-2">

            <button
                type="button"
                class="btn btn-primary btn-lg"
                wire:click="pay"
            >
                <i class="fas fa-credit-card me-1"></i> Procéder au paiement
            </button>
        </div>
    </div>
</div>
