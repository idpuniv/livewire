<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\StockMovement;

new class () extends Component {
    use WithPagination;

    // Filtres pour l'historique
    public $search = '';
    public $perPage = 15;
    public $dateFrom = '';
    public $dateTo = '';
    public $typeFilter = '';
    public $productFilter = '';
    public $supplierFilter = '';

    // Vue courante (stock ou historique)
    public $view = 'stock'; // stock, history

    // Pour les mouvements
    public $showMovementModal = false;
    public $selectedProductId = null;
    public $selectedProductName = '';
    public $selectedProductStock = 0;
    public $movementType = 'in';
    public $movementQuantity = '';
    public $movementSupplier = '';
    public $movementReference = '';
    public $movementNotes = '';

    // Pour les détails produit
    public $showDetailsModal = false;
    public $detailsProduct = null;
    public $detailsMovements = [];

    public $suppliers = [];
    public $products = [];

    public function mount()
    {
        $this->suppliers = Supplier::with('person')->get();
        $this->products = Product::orderBy('name')->get();
    }

    public function openMovementModal($productId, $type = 'in')
    {
        $product = Product::find($productId);
        if (!$product) return;
        
        $this->selectedProductId = $product->id;
        $this->selectedProductName = $product->name;
        $this->selectedProductStock = $product->stock;
        $this->movementType = $type;
        $this->movementQuantity = '';
        $this->movementSupplier = '';
        $this->movementReference = '';
        $this->movementNotes = '';
        $this->showMovementModal = true;
    }

    public function openDetailsModal($productId)
    {
        $this->detailsProduct = Product::find($productId);
        $this->detailsMovements = StockMovement::with(['supplier.person', 'user'])
            ->where('product_id', $productId)
            ->latest()
            ->take(20)
            ->get();
        $this->showDetailsModal = true;
    }

    public function saveMovement()
    {
        $this->validate([
            'movementType' => 'required|in:in,out,adjustment',
            'movementQuantity' => 'required|integer|min:1',
        ]);

        if ($this->movementType === 'out' && $this->movementQuantity > $this->selectedProductStock) {
            session()->flash('error', 'Stock insuffisant.');
            return;
        }

        $data = [
            'product_id' => $this->selectedProductId,
            'type' => $this->movementType,
            'quantity' => $this->movementQuantity,
            'supplier_id' => $this->movementType === 'in' ? $this->movementSupplier : null,
            'reference' => $this->movementReference,
            'notes' => $this->movementNotes,
            'user_id' => auth()->id(),
        ];

        StockMovement::create($data);

        $product = Product::find($this->selectedProductId);
        if ($this->movementType === 'in') {
            $product->stock += $this->movementQuantity;
        } elseif ($this->movementType === 'out') {
            $product->stock -= $this->movementQuantity;
        } else {
            $product->stock = $this->movementQuantity;
        }
        $product->save();

        session()->flash('success', 'Mouvement enregistré.');
        $this->showMovementModal = false;
    }

    public function getStockProductsProperty()
    {
        return Product::query()
            ->when($this->search, function ($query) {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%");
            })
            ->orderBy('name')
            ->paginate($this->perPage);
    }

    public function getHistoryMovementsProperty()
    {
        $query = StockMovement::with(['product', 'supplier.person', 'user']);

        if ($this->search) {
            $query->whereHas('product', function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('code', 'like', "%{$this->search}%");
            });
        }

        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }

        if ($this->productFilter) {
            $query->where('product_id', $this->productFilter);
        }

        if ($this->supplierFilter) {
            $query->where('supplier_id', $this->supplierFilter);
        }

        if ($this->dateFrom) {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        return $query->latest()->paginate($this->perPage);
    }

    public function getStockStatusClass($stock)
    {
        if ($stock <= 0) return 'text-danger fw-bold';
        if ($stock < 10) return 'text-warning fw-bold';
        return 'text-success';
    }

    public function getStockIcon($stock)
    {
        if ($stock <= 0) return 'bi bi-exclamation-triangle-fill text-danger';
        if ($stock < 10) return 'bi bi-exclamation-circle-fill text-warning';
        return 'bi bi-check-circle-fill text-success';
    }

    public function getTypeBadgeClass($type)
    {
        return match($type) {
            'in' => 'bg-success',
            'out' => 'bg-danger',
            'adjustment' => 'bg-info',
            default => 'bg-secondary'
        };
    }

    public function getTypeLabel($type)
    {
        return match($type) {
            'in' => 'Entrée',
            'out' => 'Sortie',
            'adjustment' => 'Ajustement',
            default => $type
        };
    }

    public function resetFilters()
    {
        $this->reset(['search', 'typeFilter', 'productFilter', 'supplierFilter', 'dateFrom', 'dateTo']);
    }

    public function with(): array
    {
        return [
            'stockProducts' => $this->view === 'stock' ? $this->stockProducts : [],
            'historyMovements' => $this->view === 'history' ? $this->historyMovements : [],
            'suppliers' => $this->suppliers,
            'productsList' => $this->products,
        ];
    }
};
?>

<div class="container py-4">
    <!-- Flash Messages -->
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <span>{{ session('success') }}</span>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <span>{{ session('error') }}</span>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    </div>
    @endif

    <!-- Header avec onglets -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2 fw-bold text-white mb-0">
            <i class="bi bi-boxes me-2"></i>
            Gestion des Stocks
        </h1>
        <div class="btn-group">
            <button class="btn {{ $view === 'stock' ? 'btn-primary' : 'btn-outline-light' }}"
                wire:click="$set('view', 'stock')">
                <i class="bi bi-box-seam me-2"></i>Stock
            </button>
            <button class="btn {{ $view === 'history' ? 'btn-primary' : 'btn-outline-light' }}"
                wire:click="$set('view', 'history')">
                <i class="bi bi-clock-history me-2"></i>Historique
            </button>
        </div>
    </div>

    @if($view === 'stock')
        <!-- Vue Stock -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-10">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0">
                                <i class="bi bi-search text-muted"></i>
                            </span>
                            <input type="text"
                                class="form-control border-start-0"
                                placeholder="Rechercher un produit..."
                                wire:model.live.debounce.300ms="search">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-secondary w-100" wire:click="$set('search', '')">
                            <i class="bi bi-arrow-clockwise me-2"></i>
                            Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 px-4">Produit</th>
                                <th class="py-3 px-4">Code</th>
                                <th class="py-3 px-4">Prix</th>
                                <th class="py-3 px-4 text-center">Stock</th>
                                <th class="py-3 px-4 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stockProducts as $product)
                            <tr wire:key="{{ $product->id }}">
                                <td class="py-3 px-4">
                                    <div class="fw-semibold">{{ $product->name }}</div>
                                    <small class="text-muted">{{ Str::limit($product->description, 50) }}</small>
                                </td>
                                <td class="py-3 px-4">{{ $product->code }}</td>
                                <td class="py-3 px-4">{{ number_format($product->price, 0, ',', ' ') }} FCFA</td>
                                <td class="py-3 px-4 text-center">
                                    <div class="d-flex align-items-center justify-content-center gap-2">
                                        <i class="{{ $this->getStockIcon($product->stock) }} fs-5"></i>
                                        <span class="fs-5 fw-bold {{ $this->getStockStatusClass($product->stock) }}">
                                            {{ $product->stock }}
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-end">
                                    <div class="action-icons d-flex justify-content-end gap-2">
                                        <button class="btn btn-sm btn-link p-0 text-secondary" 
                                            wire:click="openMovementModal({{ $product->id }}, 'in')"
                                            title="Entrée">
                                            <i class="bi bi-plus-circle fs-5"></i>
                                        </button>
                                        <button class="btn btn-sm btn-link p-0 text-secondary" 
                                            wire:click="openMovementModal({{ $product->id }}, 'out')"
                                            title="Sortie">
                                            <i class="bi bi-dash-circle fs-5"></i>
                                        </button>
                                        <button class="btn btn-sm btn-link p-0 text-secondary" 
                                            wire:click="openMovementModal({{ $product->id }}, 'adjustment')"
                                            title="Ajuster">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </button>
                                        <button class="btn btn-sm btn-link p-0 text-secondary" 
                                            wire:click="openDetailsModal({{ $product->id }})"
                                            title="Détails">
                                            <i class="bi bi-eye fs-5"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-box-seam display-5 mb-3"></i>
                                    <h5>Aucun produit trouvé</h5>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($stockProducts->hasPages())
            <div class="card-footer bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">
                        {{ $stockProducts->firstItem() }} - {{ $stockProducts->lastItem() }} sur {{ $stockProducts->total() }}
                    </span>
                    {{ $stockProducts->links() }}
                </div>
            </div>
            @endif
        </div>

    @else
        <!-- Vue Historique -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Recherche</label>
                        <input type="text" class="form-control" wire:model.live.debounce.300ms="search" 
                            placeholder="Produit...">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Type</label>
                        <select class="form-select" wire:model.live="typeFilter">
                            <option value="">Tous</option>
                            <option value="in">Entrées</option>
                            <option value="out">Sorties</option>
                            <option value="adjustment">Ajustements</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Produit</label>
                        <select class="form-select" wire:model.live="productFilter">
                            <option value="">Tous</option>
                            @foreach($productsList as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label fw-semibold">Fournisseur</label>
                        <select class="form-select" wire:model.live="supplierFilter">
                            <option value="">Tous</option>
                            @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->person?->name ?? $supplier->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-outline-secondary w-100" wire:click="resetFilters">
                            <i class="bi bi-arrow-clockwise me-2"></i>
                            Reset
                        </button>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Date début</label>
                        <input type="date" class="form-control" wire:model.live="dateFrom">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Date fin</label>
                        <input type="date" class="form-control" wire:model.live="dateTo">
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4">Type</th>
                                <th class="py-3 px-4">Produit</th>
                                <th class="py-3 px-4">Quantité</th>
                                <th class="py-3 px-4">Fournisseur</th>
                                <th class="py-3 px-4">Référence</th>
                                <th class="py-3 px-4">Par</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($historyMovements as $movement)
                            <tr wire:key="{{ $movement->id }}">
                                <td class="py-3 px-4">{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                                <td class="py-3 px-4">
                                    <span class="badge {{ $this->getTypeBadgeClass($movement->type) }} px-3 py-2">
                                        {{ $this->getTypeLabel($movement->type) }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">{{ $movement->product->name }}</td>
                                <td class="py-3 px-4 fw-bold">{{ number_format($movement->quantity) }}</td>
                                <td class="py-3 px-4">{{ $movement->supplier?->person?->name ?? '-' }}</td>
                                <td class="py-3 px-4">{{ $movement->reference ?? '-' }}</td>
                                <td class="py-3 px-4">{{ $movement->user?->name }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-clock-history display-5 mb-3"></i>
                                    <h5>Aucun mouvement trouvé</h5>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($historyMovements->hasPages())
            <div class="card-footer bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small">
                        {{ $historyMovements->firstItem() }} - {{ $historyMovements->lastItem() }} sur {{ $historyMovements->total() }}
                    </span>
                    {{ $historyMovements->links() }}
                </div>
            </div>
            @endif
        </div>
    @endif

    <!-- Modal Mouvement -->
    @if($showMovementModal)
    <div class="modal fade show d-block" style="background-color: rgba(0,0,0,0.5);" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light">
                    <h5 class="modal-title">
                        @if($movementType === 'in')
                            <i class="bi bi-plus-circle text-success me-2"></i>Entrée de stock
                        @elseif($movementType === 'out')
                            <i class="bi bi-dash-circle text-warning me-2"></i>Sortie de stock
                        @else
                            <i class="bi bi-pencil-square text-info me-2"></i>Ajustement
                        @endif
                    </h5>
                    <button type="button" class="btn-close" wire:click="$set('showMovementModal', false)"></button>
                </div>

                <form wire:submit.prevent="saveMovement">
                    <div class="modal-body">
                        <div class="bg-light p-3 rounded mb-3">
                            <div class="d-flex justify-content-between">
                                <span class="fw-bold">{{ $selectedProductName }}</span>
                                <span class="badge bg-secondary">Stock: {{ $selectedProductStock }}</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Quantité</label>
                            <input type="number" class="form-control form-control-lg" wire:model="movementQuantity" min="1" autofocus>
                        </div>

                        @if($movementType === 'in')
                        <div class="mb-3">
                            <label class="form-label">Fournisseur</label>
                            <select class="form-select" wire:model="movementSupplier">
                                <option value="">-- Optionnel --</option>
                                @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->person?->name ?? $supplier->company_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        @endif

                        <div class="mb-3">
                            <label class="form-label">Référence</label>
                            <input type="text" class="form-control" wire:model="movementReference" placeholder="N° BL, facture...">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" wire:model="movementNotes" rows="2"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" wire:click="$set('showMovementModal', false)">Annuler</button>
                        <button type="submit" class="btn btn-primary">Confirmer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- Modal Détails -->
    @if($showDetailsModal && $detailsProduct)
    <div class="modal fade show d-block" style="background-color: rgba(0,0,0,0.5);" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light">
                    <h5 class="modal-title">
                        <i class="bi bi-info-circle me-2"></i>
                        {{ $detailsProduct->name }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="$set('showDetailsModal', false)"></button>
                </div>

                <div class="modal-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <p class="mb-1"><span class="text-muted">Code:</span> {{ $detailsProduct->code }}</p>
                            <p class="mb-1"><span class="text-muted">Prix:</span> {{ number_format($detailsProduct->price, 0, ',', ' ') }} FCFA</p>
                        </div>
                        <div class="col-md-6">
                            <div class="bg-light p-3 rounded text-center">
                                <span class="text-muted">Stock actuel</span>
                                <h2 class="{{ $this->getStockStatusClass($detailsProduct->stock) }}">{{ $detailsProduct->stock }}</h2>
                            </div>
                        </div>
                    </div>

                    <h6 class="fw-semibold mb-3">Historique des mouvements</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Quantité</th>
                                    <th>Fournisseur</th>
                                    <th>Référence</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($detailsMovements as $movement)
                                <tr>
                                    <td>{{ $movement->created_at->format('d/m/Y H:i') }}</td>
                                    <td>
                                        <span class="badge {{ $this->getTypeBadgeClass($movement->type) }}">
                                            {{ $this->getTypeLabel($movement->type) }}
                                        </span>
                                    </td>
                                    <td>{{ $movement->quantity }}</td>
                                    <td>{{ $movement->supplier?->person?->name ?? '-' }}</td>
                                    <td>{{ $movement->reference ?? '-' }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Aucun mouvement</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" wire:click="$set('showDetailsModal', false)">Fermer</button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <style>
        .action-icons {
            opacity: 0;
            transition: opacity 0.2s;
        }
        tr:hover .action-icons {
            opacity: 1;
        }
        .action-icons button {
            transition: transform 0.2s;
        }
        .action-icons button:hover {
            transform: scale(1.2);
            color: #0d6efd !important;
        }
    </style>
</div>