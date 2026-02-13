<?php

use App\Enums\Status;
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
                'status'           => Status::SUCCESS,
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
            event(new \App\Events\OrderPayed($this->order));
            $this->order = Order::with(['items', 'invoice'])->find($this->order->id);

            $this->dispatch('orderPaid', orderId: $this->order->id);
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

        {{-- Montant remis --}}
        <div class="mb-3">
            <label class="form-label small fw-medium text-secondary mb-1">Montant remis</label>
            <div class="input-group">
                <input
                    type="number"
                    class="form-control form-control-lg bg-light border-0"
                    placeholder="0"
                    step="0.01"
                    wire:model.lazy="amountPaid"
                />
                <span class="input-group-text bg-light border-0 text-secondary">XOF</span>
            </div>
        </div>

        {{-- Monnaie --}}
        <div class="bg-success bg-opacity-10 rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-success small fw-medium">Monnaie à rendre</span>
                <span class="h5 mb-0 text-success fw-bold">{{ number_format($change, 2) }} XOF</span>
            </div>
        </div>
    </div>

    {{-- Footer --}}
    <div class="card-footer bg-white border-0 pb-4 px-4">
        <button
            type="button"
            class="btn btn-primary w-100 py-3 fw-medium"
            wire:click="pay"
        >
            <i class="fas fa-check me-2"></i>
            Confirmer le paiement
        </button>
    </div>
</div>

