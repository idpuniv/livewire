<div class="p-4">
    {{-- En-tête du reçu --}}
    <div class="text-center mb-4">
        <div class="bg-success bg-opacity-10 rounded-circle p-3 d-inline-flex mb-3">
            <i class="fas fa-check-circle text-success fa-3x"></i>
        </div>
        <h5 class="text-success fw-bold mb-1">Paiement réussi !</h5>
        <p class="text-muted small">Commande #{{ $orderId }}</p>
    </div>

    {{-- Détails du paiement --}}
    <div class="bg-light rounded-3 p-3 mb-4">
        <div class="d-flex justify-content-between mb-2">
            <span class="text-secondary">Montant total</span>
            <span class="fw-bold text-primary">{{ number_format($total, 2) }} XOF</span>
        </div>
        <div class="d-flex justify-content-between mb-2">
            <span class="text-secondary">Montant reçu</span>
            <span class="fw-bold">{{ number_format($amountPaid, 2) }} XOF</span>
        </div>
        <div class="d-flex justify-content-between">
            <span class="text-secondary">Monnaie rendue</span>
            <span class="fw-bold text-success">{{ number_format($change, 2) }} XOF</span>
        </div>
    </div>

    {{-- Informations client --}}
    <div class="mb-4">
        <div class="d-flex justify-content-between mb-2">
            <span class="text-secondary">Client</span>
            <span class="fw-medium">{{ $customerName }}</span>
        </div>
        <div class="d-flex justify-content-between mb-2">
            <span class="text-secondary">Téléphone</span>
            <span class="fw-medium">{{ $customerPhone }}</span>
        </div>
        <div class="d-flex justify-content-between">
            <span class="text-secondary">Date & Heure</span>
            <span class="fw-medium">{{ $date }} {{ $time }}</span>
        </div>
    </div>

    {{-- Bouton de fermeture --}}
    <button
        type="button"
        class="btn btn-outline-secondary w-100 py-2"
        onclick="Livewire.dispatch('resetReceipt')">
        <i class="fas fa-times me-2"></i>
        Fermer
    </button>
</div>