<?php

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads; // 👈 NOUVEAU
use App\Models\Product;
use App\Models\Supplier;
use App\Models\StockMovement;
use App\Permissions\ProductPermissions;
use Illuminate\Support\Facades\Storage; // 👈 NOUVEAU

new class extends Component {
    use WithPagination;
    use WithFileUploads; // 👈 NOUVEAU

    // Propriétés existantes
    public string $name = '';
    public string $code = '';
    public ?string $description = '';
    public $price = '';
    public $tva_rate = '';
    public $tva_amount = '';
    public $stock = '';
    public $stock_threshold = 5;
    public bool $published = false;
    public string $tva_input_mode = 'rate';
    public bool $showSearch = false;

    // 👇 NOUVEAU : pour l'image (facultative)
    public $image = null; // Image existante en base
    public $tempImage = null; // Image temporaire uploadée

    public $editingProductId = null;
    public $showModal = false;

    public string $search = '';
    public $filterPublished = '';
    public int $perPage = 5;

    public $selectedProducts = [];
    public bool $selectAll = false;
    public array $bulkActions = [
        'publish' => 'Publier',
        'unpublish' => 'Dépublier',
        'delete' => 'Supprimer',
    ];
    public $selectedBulkAction = '';

    // 👇 NOUVEAU : pour le toggle des filtres sur mobile
    public bool $showFilters = false;

    public function getCanListProductsProperty()
    {
        return auth()->user()?->can(ProductPermissions::LIST) ?? false;
    }

    public function getCanViewProductProperty()
    {
        return auth()->user()?->can(ProductPermissions::VIEW) ?? false;
    }

    public function getCanCreateProductProperty()
    {
        return auth()->user()?->can(ProductPermissions::CREATE) ?? false;
    }

    public function getCanUpdateProductProperty()
    {
        return auth()->user()?->can(ProductPermissions::UPDATE) ?? false;
    }

    public function getCanDeleteProductProperty()
    {
        return auth()->user()?->can(ProductPermissions::DELETE) ?? false;
    }

    public function getCanStockCreateProperty()
    {
        return auth()->user()?->can(ProductPermissions::STOCK_CREATE) ?? false;
    }

    public function getCanStockListProperty()
    {
        return auth()->user()?->can(ProductPermissions::STOCK_LIST) ?? false;
    }

    public function getCanStockHistoryProperty()
    {
        return auth()->user()?->can(ProductPermissions::STOCK_HISTORY) ?? false;
    }

    public function getCanStockAdjustProperty()
    {
        return auth()->user()?->can(ProductPermissions::STOCK_ADJUST) ?? false;
    }

    // Nouveaux : pour les mouvements de stock
    public $showStockModal = false;
    public $showHistoryModal = false;
    public $selectedProductForStock = null;
    public $movementType = 'in';
    public $movementQuantity = '';
    public $movementSupplier = '';
    public $movementReference = '';
    public $movementNotes = '';
    public $movementHistory = [];
    public $suppliers = [];

    // Calculs TVA existants
    public function updatedTvaRate()
    {
        if ($this->tva_input_mode === 'rate') {
            $price = floatval($this->price);
            $rate = floatval($this->tva_rate);
            $this->calculated_tva_amount = $price > 0 ? ($price * $rate) / 100 : 0;
        }
    }

    public function updatedTvaAmount()
    {
        if ($this->tva_input_mode === 'amount') {
            $price = floatval($this->price);
            $amount = floatval($this->tva_amount);
            $this->calculated_tva_rate = $price > 0 ? ($amount / $price) * 100 : 0;
        }
    }

    public function updatedTvaInputMode($mode)
    {
        $this->updatedTvaRate();
        $this->updatedTvaAmount();
    }

    public function getPriceTtcProperty()
    {
        $price = floatval($this->price);
        $amount = floatval($this->tva_amount);
        return $price + $amount;
    }

    // 👇 NOUVEAU : validation auto quand on upload une image
    public function updatedTempImage()
    {
        $this->validate([
            'tempImage' => 'image|max:2048', // 2MB max, facultatif
        ]);
    }

    // Méthodes existantes
    public function updatedSearch()
    {
        $this->resetPage();
        $this->selectedProducts = [];
        $this->selectAll = false;
    }

    public function updatedFilterPublished()
    {
        $this->resetPage();
        $this->selectedProducts = [];
        $this->selectAll = false;
    }

    // 👇 NOUVEAU : mise à jour perPage
    public function updatedPerPage()
    {
        $this->resetPage();
        $this->selectedProducts = [];
        $this->selectAll = false;
    }

    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedProducts = $this->getProductsForCurrentPage()->pluck('id')->toArray();
        } else {
            $this->selectedProducts = [];
        }
    }

    public function updatedSelectedProducts()
    {
        $currentPageProductIds = $this->getProductsForCurrentPage()->pluck('id')->toArray();
        $selectedOnCurrentPage = array_intersect($this->selectedProducts, $currentPageProductIds);

        $this->selectAll = !empty($selectedOnCurrentPage) && count($selectedOnCurrentPage) === count($currentPageProductIds);
    }

    private function getProductsForCurrentPage()
    {
        if (!$this->getCanListProductsProperty()) {
            return Product::query()->whereRaw('0 = 1');
        }

        $query = Product::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")->orWhere('description', 'like', "%{$this->search}%");
            });
        }

        if ($this->filterPublished !== '') {
            if ($this->filterPublished) {
                $query->whereNotNull('published_at');
            } else {
                $query->whereNull('published_at');
            }
        }

        return $query->latest()->paginate($this->perPage);
    }

    public function performBulkAction($action = null)
    {
        if (!$this->getCanUpdateProductProperty() && !$this->getCanDeleteProductProperty()) {
            abort(403, 'Accès refusé pour actions en masse.');
        }

        if ($action) {
            $this->selectedBulkAction = $action;
        }

        if (empty($this->selectedProducts)) {
            session()->flash('error', 'Veuillez sélectionner au moins un produit.');
            return;
        }

        if (empty($this->selectedBulkAction)) {
            session()->flash('error', 'Veuillez choisir une action.');
            return;
        }

        $productIds = $this->selectedProducts;

        switch ($this->selectedBulkAction) {
            case 'publish':
            case 'unpublish':
                if (!$this->getCanUpdateProductProperty()) {
                    abort(403, 'Accès refusé pour modifier l\'état des produits.');
                }
                break;
            case 'delete':
                if (!$this->getCanDeleteProductProperty()) {
                    abort(403, 'Accès refusé pour supprimer des produits.');
                }
                break;
        }

        switch ($this->selectedBulkAction) {
            case 'publish':
                $updated = Product::whereIn('id', $productIds)->update(['published_at' => now()]);
                session()->flash('success', $updated . ' produit(s) publié(s) !');
                break;
            case 'unpublish':
                $updated = Product::whereIn('id', $productIds)->update(['published_at' => null]);
                session()->flash('success', $updated . ' produit(s) dépublié(s) !');
                break;
            case 'delete':
                $deleted = Product::whereIn('id', $productIds)->delete();
                session()->flash('success', $deleted . ' produit(s) supprimé(s) !');
                break;
        }

        $this->selectedProducts = [];
        $this->selectAll = false;
        $this->selectedBulkAction = '';
    }

    public function clearSelection()
    {
        $this->selectedProducts = [];
        $this->selectAll = false;
        session()->flash('success', 'Sélection effacée.');
    }

    public function openModal($id = null)
    {
        if ($id && !$this->getCanUpdateProductProperty()) {
            abort(403, 'Accès refusé pour modifier le produit.');
        }

        if (!$id && !$this->getCanCreateProductProperty()) {
            abort(403, 'Accès refusé pour créer un produit.');
        }

        if ($id) {
            $product = Product::find($id);
            if ($product) {
                $this->name = $product->name;
                $this->code = $product->code;
                $this->description = $product->description;
                $this->price = $product->price;
                $this->tva_rate = $product->tva_rate ?? '';
                $this->tva_amount = $product->tva_rate > 0 ? ($product->price * $product->tva_rate) / 100 : '';
                $this->stock = $product->stock;
                $this->stock_threshold = $product->stock_threshold ?? 5;
                $this->published = !is_null($product->published_at);
                $this->editingProductId = $product->id;
                $this->image = $product->image;
                $this->tempImage = null;
            }
        } else {
            $this->reset(['name', 'code', 'description', 'published', 'editingProductId', 'image', 'tempImage']);
            $this->price = '';
            $this->tva_rate = '';
            $this->tva_amount = '';
            $this->stock = '';
            $this->stock_threshold = 5;
            $this->tva_input_mode = 'rate';
        }
        $this->showModal = true;
    }

    public function save()
    {
        // Vérifier les permissions
        if ($this->editingProductId && !$this->getCanUpdateProductProperty()) {
            abort(403, 'Accès refusé pour mettre à jour le produit.');
        }

        if (!$this->editingProductId && !$this->getCanCreateProductProperty()) {
            abort(403, 'Accès refusé pour créer le produit.');
        }

        // 👇 NOUVEAU : validation de l'image (facultative)
        $validated = $this->validate([
            'name' => 'required|max:255',
            'code' => 'required|unique:products,code' . ($this->editingProductId ? ',' . $this->editingProductId : ''),
            'description' => 'nullable|string',
            'published' => 'boolean',
            'stock_threshold' => 'nullable|integer|min:0|max:100',
            'tempImage' => 'nullable|image|max:2048', // facultatif
        ]);

        // Préparer les données
        $price = $this->price === '' ? 0 : floatval($this->price);
        $stock = $this->stock === '' ? 0 : intval($this->stock);
        $stock_threshold = $this->stock_threshold === '' ? 5 : intval($this->stock_threshold);

        // Calculer la TVA
        if ($this->tva_input_mode === 'amount' && $price > 0) {
            $amount = floatval($this->tva_amount);
            $tva_rate = $amount > 0 ? ($amount / $price) * 100 : 0;
        } else {
            $tva_rate = $this->tva_rate === '' ? 0 : floatval($this->tva_rate);
        }

        $data = [
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
            'price' => $price,
            'tva_rate' => $tva_rate,
            'stock' => $stock,
            'stock_threshold' => $stock_threshold,
            'published_at' => $this->published ? now() : null,
        ];

        // Mode édition
        if ($this->editingProductId) {
            $product = Product::find($this->editingProductId);
            $oldStock = $product->stock;

            // 👇 NOUVEAU : gestion de l'image en modification
            if ($this->tempImage) {
                // Supprimer l'ancienne image si elle existe
                if ($product->image) {
                    Storage::disk('public')->delete($product->image);
                }
                // Upload de la nouvelle image
                $data['image'] = $this->tempImage->store('products', 'public');
            } elseif ($this->image === null && $product->image) {
                // L'utilisateur a supprimé l'image
                Storage::disk('public')->delete($product->image);
                $data['image'] = null;
            } else {
                // Conserver l'image existante
                $data['image'] = $product->image;
            }

            $product->update($data);

            // Si le stock a changé, enregistrer un mouvement d'ajustement
            if ($oldStock != $stock) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'adjustment',
                    'quantity' => $stock,
                    'reference' => 'Ajustement manuel',
                    'notes' => "Stock modifié de $oldStock à $stock lors de l'édition",
                    'created_by' => auth()->id(),
                ]);
            }

            session()->flash('success', 'Produit mis à jour avec succès !');
        }
        // Mode création
        else {
            // 👇 NOUVEAU : upload de l'image si fournie
            if ($this->tempImage) {
                $data['image'] = $this->tempImage->store('products', 'public');
            }

            $product = Product::create($data);

            // Si stock > 0, créer un mouvement d'entrée
            if ($stock > 0) {
                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'in',
                    'quantity' => $stock,
                    'reference' => 'Stock initial',
                    'notes' => 'Stock initial à la création du produit',
                    'created_by' => auth()->id(),
                ]);
            }

            session()->flash('success', 'Produit créé avec succès !');
        }

        // 👇 NOUVEAU : reset de l'image
        $this->reset(['name', 'code', 'description', 'published', 'editingProductId', 'image', 'tempImage']);
        $this->price = '';
        $this->tva_rate = '';
        $this->tva_amount = '';
        $this->stock = '';
        $this->stock_threshold = 5;
        $this->tva_input_mode = 'rate';
        $this->showModal = false;
    }

    public function delete($id)
    {
        if (!$this->getCanDeleteProductProperty()) {
            abort(403, 'Accès refusé pour supprimer le produit.');
        }

        // 👇 NOUVEAU : supprimer l'image avant le produit
        $product = Product::find($id);
        if ($product && $product->image) {
            Storage::disk('public')->delete($product->image);
        }

        Product::destroy($id);
        session()->flash('success', 'Produit supprimé !');
    }

    public function mount()
    {
        if (!$this->getCanListProductsProperty()) {
            abort(403, 'Accès refusé.');
        }

        if ($this->getCanStockCreateProperty() || $this->getCanStockListProperty()) {
            $this->suppliers = Supplier::with('person')->get();
        }
    }

    public function openStockModal($productId, $type = 'in')
    {
        if (!$this->getCanStockCreateProperty()) {
            abort(403, 'Accès refusé pour gérer le stock.');
        }

        $product = Product::find($productId);
        if ($product) {
            $this->selectedProductForStock = $product;
            $this->movementType = $type;
            $this->movementQuantity = '';
            $this->movementSupplier = '';
            $this->movementReference = '';
            $this->movementNotes = '';
            $this->showStockModal = true;
        }
    }

    public function openHistoryModal($productId)
    {
        if (!$this->getCanStockHistoryProperty()) {
            abort(403, 'Accès refusé pour consulter l\'historique du stock.');
        }

        $product = Product::find($productId);
        if ($product) {
            $this->selectedProductForStock = $product;
            $this->movementHistory = StockMovement::with(['supplier.person', 'user'])
                ->where('product_id', $productId)
                ->latest()
                ->take(50)
                ->get();
            $this->showHistoryModal = true;
        }
    }

    public function saveStockMovement()
    {
        // Vérifier la permission selon le type de mouvement
        if ($this->movementType === 'adjustment') {
            if (!auth()->user()->can(ProductPermissions::STOCK_ADJUST)) {
                abort(403, 'Accès refusé pour les ajustements de stock.');
            }
        } else {
            if (!auth()->user()->can(ProductPermissions::STOCK_CREATE)) {
                abort(403, 'Accès refusé pour les mouvements de stock.');
            }
        }

        $this->validate([
            'movementType' => 'required|in:in,out,adjustment',
            'movementQuantity' => 'required|integer|min:1',
        ]);

        $product = $this->selectedProductForStock;

        if ($this->movementType === 'out' && $this->movementQuantity > $product->stock) {
            session()->flash('error', 'Stock insuffisant.');
            return;
        }

        // Préparer les données avec conversion des chaînes vides en null
        $data = [
            'product_id' => $product->id,
            'type' => $this->movementType,
            'quantity' => $this->movementQuantity,
            'supplier_id' => $this->movementType === 'in' && !empty($this->movementSupplier) ? $this->movementSupplier : null,
            'reference' => !empty($this->movementReference) ? $this->movementReference : null,
            'notes' => !empty($this->movementNotes) ? $this->movementNotes : null,
            'created_by' => auth()->id(),
        ];

        // Créer le mouvement de stock
        StockMovement::create($data);

        // Mettre à jour le stock du produit
        if ($this->movementType === 'in') {
            $product->stock += $this->movementQuantity;
        } elseif ($this->movementType === 'out') {
            $product->stock -= $this->movementQuantity;
        } else {
            // adjustment
            $product->stock = $this->movementQuantity;
        }
        $product->save();

        // Vérifier si le stock est passé sous le seuil
        if ($product->stock <= $product->stock_threshold) {
            session()->flash('warning', "⚠️ Attention : Le stock de {$product->name} est sous le seuil d'alerte ({$product->stock}/{$product->stock_threshold})");
        }

        session()->flash('success', 'Mouvement de stock enregistré avec succès !');

        // Fermer le modal et réinitialiser les champs
        $this->showStockModal = false;
        $this->reset(['movementQuantity', 'movementSupplier', 'movementReference', 'movementNotes']);
    }

    public function getTypeBadgeClass($type)
    {
        return match ($type) {
            'in' => 'bg-success',
            'out' => 'bg-danger',
            'adjustment' => 'bg-info',
            default => 'bg-secondary',
        };
    }

    public function getTypeLabel($type)
    {
        return match ($type) {
            'in' => 'Entrée',
            'out' => 'Sortie',
            'adjustment' => 'Ajustement',
            default => $type,
        };
    }

    public function isBelowThreshold($stock, $threshold)
    {
        return $stock <= $threshold;
    }

    public function getStockStatusClass($stock, $threshold = null)
    {
        $threshold = $threshold ?? $this->stock_threshold;

        if ($stock <= 0) {
            return 'text-danger fw-bold';
        }
        if ($stock <= $threshold) {
            return 'text-warning fw-bold';
        }
        if ($stock <= $threshold * 2) {
            return 'text-info fw-bold';
        }
        return 'text-success';
    }

    public function getStockIcon($stock, $threshold = null)
    {
        $threshold = $threshold ?? $this->stock_threshold;

        if ($stock <= 0) {
            return 'bi bi-exclamation-triangle-fill text-danger';
        }
        if ($stock <= $threshold) {
            return 'bi bi-exclamation-circle-fill text-warning';
        }
        if ($stock <= $threshold * 2) {
            return 'bi bi-info-circle-fill text-info';
        }
        return 'bi bi-check-circle-fill text-success';
    }

    public function getStockMessage($stock, $threshold = null)
    {
        $threshold = $threshold ?? $this->stock_threshold;

        if ($stock <= 0) {
            return 'RUPTURE DE STOCK !';
        }
        if ($stock <= $threshold) {
            return 'Stock critique (sous le seuil)';
        }
        if ($stock <= $threshold * 2) {
            return 'Stock faible';
        }
        return 'Stock normal';
    }

    // Méthodes gardées pour compatibilité
    public function getStockStatusClassOld($stock)
    {
        if ($stock <= 0) {
            return 'text-danger fw-bold';
        }
        if ($stock < 10) {
            return 'text-warning fw-bold';
        }
        return 'text-success';
    }

    public function getStockIconOld($stock)
    {
        if ($stock <= 0) {
            return 'bi bi-exclamation-triangle-fill text-danger';
        }
        if ($stock < 10) {
            return 'bi bi-exclamation-circle-fill text-warning';
        }
        return 'bi bi-check-circle-fill text-success';
    }

    public function with(): array
    {
        return ['products' => $this->getProductsForCurrentPage()];
    }
};
?>

<div class="container py-4">
    <!-- Flash Messages -->
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div class="flex-grow-1">{{ session('success') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    @endif

    @if (session('warning'))
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-0" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div class="flex-grow-1">{{ session('warning') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <div class="flex-grow-1">{{ session('error') }}</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    @endif

    <!-- Header compact -->
    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-3 mb-md-4">
        <div class="d-flex w-100 align-items-center justify-content-between">
            <h1 class="h5 h4-md fw-bold text-white mb-0">Produits</h1>
            <div class="d-flex gap-2">
                <!-- Bouton recherche pour mobile uniquement -->
                <button class="btn btn-outline-light d-md-none" type="button" wire:click="$toggle('showSearch')">
                    <i class="bi bi-search"></i>
                </button>

                <!-- Bouton filtre pour mobile uniquement -->
                <button class="btn btn-outline-light d-md-none position-relative" type="button"
                    data-bs-toggle="offcanvas" data-bs-target="#filtersOffcanvas" aria-controls="filtersOffcanvas">
                    <i class="bi bi-funnel"></i>
                    @if ($filterPublished !== '')
                        <span
                            class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle">
                            <span class="visually-hidden">Filtres actifs</span>
                        </span>
                    @endif
                </button>

                <!-- Bouton création -->
                @can(App\Permissions\ProductPermissions::CREATE)
                    <button class="btn btn-primary" wire:click="openModal()">
                        <i class="bi bi-plus-circle d-md-none"></i>
                        <span class="d-none d-md-inline">Nouveau Produit</span>
                    </button>
                @endcan
            </div>
        </div>
    </div>

    <!-- Barre de recherche mobile (cachée par défaut) -->
    @if ($showSearch)
        <div class="d-md-none mb-3">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input type="text" class="form-control border-start-0" placeholder="Rechercher un produit..."
                    wire:model.live.debounce.300ms="search">
                @if ($search)
                    <button class="btn btn-outline-secondary" type="button"
                        wire:click="$set('search', ''); $set('showSearch', false)">
                        <i class="bi bi-x"></i>
                    </button>
                @endif
            </div>
        </div>
    @endif

    <!-- Version Desktop des filtres (toujours visible) -->
    <div class="card border-0 shadow-sm mb-4 d-none d-md-block">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark mb-2">Rechercher</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0"
                            placeholder="Rechercher par nom ou description..." wire:model.live.debounce.300ms="search">
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold text-dark mb-2">Filtrer par statut</label>
                    <select class="form-select" wire:model.live="filterPublished">
                        <option value="">Tous les produits</option>
                        <option value="1">Produits publiés</option>
                        <option value="0">Produits non publiés</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-outline-secondary w-100"
                        wire:click="$set('search', ''); $set('filterPublished', '')">
                        <i class="bi bi-arrow-clockwise me-1"></i>
                        Réinitialiser
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Badges des filtres actifs sur mobile -->
    @if ($search || $filterPublished !== '')
        <div class="d-flex d-md-none flex-wrap gap-1 mb-3">
            @if ($search)
                <span class="badge bg-primary d-inline-flex align-items-center gap-1 py-2 px-3">
                    <i class="bi bi-search"></i>
                    <span>{{ Str::limit($search, 15) }}</span>
                    <i class="bi bi-x-circle-fill ms-2" wire:click="$set('search', '')" style="cursor: pointer;"></i>
                </span>
            @endif
            @if ($filterPublished !== '')
                <span class="badge bg-info d-inline-flex align-items-center gap-1 py-2 px-3">
                    <i class="bi bi-funnel"></i>
                    <span>{{ $filterPublished == '1' ? 'Publiés' : 'Non publiés' }}</span>
                    <i class="bi bi-x-circle-fill ms-2" wire:click="$set('filterPublished', '')"
                        style="cursor: pointer;"></i>
                </span>
            @endif
        </div>
    @endif

    <!-- Offcanvas Filtres pour Mobile (uniquement le filtre statut) -->
    <!-- Offcanvas Filtres pour Mobile (uniquement le filtre statut) -->
    <div class="offcanvas offcanvas-bottom" tabindex="-1" id="filtersOffcanvas" aria-labelledby="filtersOffcanvasLabel"
        data-bs-backdrop="static" data-bs-keyboard="false"
        style="height: auto; max-height: 90vh; border-top-left-radius: 20px; border-top-right-radius: 20px;">
        <div class="offcanvas-header" style="border-top-left-radius: 20px; border-top-right-radius: 20px;">
            <h5 class="offcanvas-title" id="filtersOffcanvasLabel">
                <i class="bi bi-funnel me-2"></i>
                Filtrer par statut
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fermer"></button>
        </div>
        <div class="offcanvas-body">
            <div class="mb-4">
                <select class="form-select form-select-lg" wire:model.live="filterPublished">
                    <option value="">Tous les produits</option>
                    <option value="1">Produits publiés</option>
                    <option value="0">Produits non publiés</option>
                </select>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary flex-grow-1" wire:click="$set('filterPublished', '')">
                    <i class="bi bi-arrow-clockwise me-1"></i>
                    Réinitialiser
                </button>
                <button class="btn btn-primary flex-grow-1"
                    onclick="bootstrap.Offcanvas.getInstance(document.getElementById('filtersOffcanvas')).hide()">
                    <i class="bi bi-check-lg me-1"></i>
                    Fermer
                </button>
            </div>
        </div>
    </div>

    <!-- Table des produits - avec scroll horizontal et vertical -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <!-- 👇 NOUVEAU : Sélecteur de pagination et scroll table -->
            <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                <div class="d-flex align-items-center">
                    <label class="text-muted me-2 small">Afficher</label>
                    <select class="form-select form-select-sm" style="width: auto;" wire:model.live="perPage">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span class="text-muted ms-2 small">entrées</span>
                </div>
                <div class="text-muted small d-none d-md-block">
                    Total: {{ $products->total() }} produit(s)
                </div>
            </div>

            <!-- Conteneur avec scroll horizontal et vertical -->
            <div class="table-responsive" style="max-height: 600px; overflow-y: auto; overflow-x: auto;">
                <table class="table table-hover align-middle mb-0" style="min-width: 1400px;">
                    <thead class="table-light"
                        style="position: sticky; top: 0; z-index: 10; background-color: #f8f9fa;">
                        <tr>
                            <th class="py-3 px-4" width="50">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" wire:model.live="selectAll"
                                        wire:key="select-all-checkbox-{{ count($selectedProducts) }}" id="selectAll">
                                </div>
                            </th>

                            <!-- 👇 NOUVELLE COLONNE IMAGE -->
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Image</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Nom</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Code</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Prix HT</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">TVA</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Prix TTC</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Stock</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Statut</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom text-end">Actions</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom text-end">
                                @canany([App\Permissions\ProductPermissions::UPDATE,
                                    App\Permissions\ProductPermissions::DELETE])
                                    <div class="d-flex justify-content-end">
                                        <div class="dropdown">
                                            <button class="btn btn-sm p-0 border-0" type="button"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                @can(App\Permissions\ProductPermissions::UPDATE)
                                                    <li>
                                                        <button class="dropdown-item"
                                                            wire:click="performBulkAction('publish')">
                                                            Publier
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item"
                                                            wire:click="performBulkAction('unpublish')">
                                                            Dépublier
                                                        </button>
                                                    </li>
                                                @endcan
                                                @can(App\Permissions\ProductPermissions::DELETE)
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item text-danger"
                                                            wire:click="performBulkAction('delete')"
                                                            wire:confirm="Êtes-vous sûr de vouloir supprimer les produits sélectionnés ?">
                                                            Supprimer
                                                        </button>
                                                    </li>
                                                @endcan
                                            </ul>
                                        </div>
                                    </div>
                                @endcanany
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            @php
                                $tvaAmount = ($product->price * $product->tva_rate) / 100;
                            @endphp
                            <tr wire:key="{{ $product->id }}"
                                class="{{ in_array($product->id, $selectedProducts) ? 'table-primary' : '' }}">
                                <td class="py-3 px-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" value="{{ $product->id }}"
                                            wire:model.live="selectedProducts"
                                            wire:key="checkbox-{{ $product->id }}-{{ in_array($product->id, $selectedProducts) ? 'checked' : 'unchecked' }}-{{ rand() }}">
                                    </div>
                                </td>

                                <td class="py-3 px-4">
                                    @if ($product->image)
                                        <img src="{{ asset('storage/' . $product->image) }}"
                                            alt="{{ $product->name }}" class="rounded-circle"
                                            style="width: 40px; height: 40px; object-fit: cover;">
                                    @else
                                        <div class="bg-light rounded-circle d-flex align-items-center justify-content-center"
                                            style="width: 40px; height: 40px;">
                                            <i class="bi bi-image text-muted"></i>
                                        </div>
                                    @endif
                                </td>

                                <td class="py-3 px-4">{{ Str::limit($product->name, 50) }}</td>
                                <td class="py-3 px-4">{{ $product->code }}</td>
                                <td class="py-3 px-4">{{ number_format($product->price, 0, ',', ' ') }} FCFA</td>
                                <td class="py-3 px-4">
                                    {{ $product->tva_rate }}% ({{ number_format($tvaAmount, 0, ',', ' ') }} FCFA)
                                </td>
                                <td class="py-3 px-4">{{ number_format($product->price + $tvaAmount, 0, ',', ' ') }}
                                    FCFA</td>
                                <td class="py-3 px-4">
                                    <div class="d-flex align-items-center gap-1"
                                        title="{{ $this->getStockMessage($product->stock, $product->stock_threshold ?? 5) }}">
                                        <i
                                            class="{{ $this->getStockIcon($product->stock, $product->stock_threshold ?? 5) }}"></i>
                                        <span
                                            class="{{ $this->getStockStatusClass($product->stock, $product->stock_threshold ?? 5) }}">
                                            {{ $product->stock }}
                                        </span>
                                        @if ($this->isBelowThreshold($product->stock, $product->stock_threshold ?? 5))
                                            <span class="badge bg-warning text-dark ms-1" title="Seuil d'alerte">
                                                <i class="bi bi-bell"></i> {{ $product->stock_threshold ?? 5 }}
                                            </span>
                                        @endif
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    @if ($product->published_at)
                                        <span class="badge bg-success-subtle text-success px-3 py-2 rounded-pill">
                                            <i class="bi bi-check-circle me-1"></i>
                                            Publié
                                        </span>
                                    @else
                                        <span class="badge bg-warning-subtle text-warning px-3 py-2 rounded-pill">
                                            <i class="bi bi-clock me-1"></i>
                                            Non publié
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-end">
                                    <div class="action-icons d-flex justify-content-end gap-1">
                                        @can(App\Permissions\ProductPermissions::STOCK_CREATE)
                                            <button class="btn btn-sm btn-link p-1 text-success"
                                                wire:click="openStockModal({{ $product->id }}, 'in')"
                                                title="Entrée de stock">
                                                <i class="bi bi-plus-circle fs-5"></i>
                                            </button>
                                            <button class="btn btn-sm btn-link p-1 text-warning"
                                                wire:click="openStockModal({{ $product->id }}, 'out')"
                                                title="Sortie de stock">
                                                <i class="bi bi-dash-circle fs-5"></i>
                                            </button>
                                        @endcan

                                        @can(App\Permissions\ProductPermissions::STOCK_ADJUST)
                                            <button class="btn btn-sm btn-link p-1 text-info"
                                                wire:click="openStockModal({{ $product->id }}, 'adjustment')"
                                                title="Ajustement de stock">
                                                <i class="bi bi-pencil-square fs-5"></i>
                                            </button>
                                        @endcan

                                        @can(App\Permissions\ProductPermissions::STOCK_HISTORY)
                                            <button class="btn btn-sm btn-link p-1 text-info"
                                                wire:click="openHistoryModal({{ $product->id }})" title="Historique">
                                                <i class="bi bi-clock-history fs-5"></i>
                                            </button>
                                        @endcan

                                        @can(App\Permissions\ProductPermissions::UPDATE)
                                            <button class="btn btn-sm btn-link p-1 text-primary"
                                                wire:click="openModal({{ $product->id }})" title="Modifier">
                                                <i class="bi bi-pencil fs-5"></i>
                                            </button>
                                        @endcan

                                        @can(App\Permissions\ProductPermissions::DELETE)
                                            <button class="btn btn-sm btn-link p-1 text-danger"
                                                wire:click="delete({{ $product->id }})"
                                                wire:confirm="Êtes-vous sûr de vouloir supprimer ce produit ?"
                                                title="Supprimer">
                                                <i class="bi bi-trash fs-5"></i>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-end"></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center py-5 text-muted">
                                    <i class="bi bi-box-seam display-5 mb-3"></i>
                                    <h5 class="fw-semibold mb-2">Aucun produit trouvé</h5>
                                    <p>{{ $search ? 'Aucun résultat pour votre recherche.' : 'Commencez par créer votre premier produit.' }}
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- PAGINATION -->
       @if ($products->hasPages())
    <div class="card-footer bg-white border-top-0 pt-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div class="text-muted small mb-2 mb-md-0">
                Affichage de {{ $products->firstItem() }} à {{ $products->lastItem() }} sur
                {{ $products->total() }} produits
            </div>

            <nav aria-label="Pagination">
                <ul class="pagination my-0" style="gap: 2px;">
                    {{-- Lien Previous --}}
                    <li class="page-item {{ $products->onFirstPage() ? 'disabled' : '' }}">
                        <a class="page-link" href="#" wire:click.prevent="previousPage" rel="prev"
                            style="border-radius: 4px;">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>

                    {{-- Première page --}}
                    @if($products->currentPage() > 3)
                        <li class="page-item">
                            <a class="page-link" href="#" wire:click.prevent="gotoPage(1)" style="border-radius: 4px;">1</a>
                        </li>
                        @if($products->currentPage() > 4)
                            <li class="page-item disabled">
                                <span class="page-link" style="border-radius: 4px;">...</span>
                            </li>
                        @endif
                    @endif

                    {{-- Pages autour de la page courante --}}
                    @foreach(range(max(1, $products->currentPage() - 2), min($products->lastPage(), $products->currentPage() + 2)) as $page)
                        <li class="page-item {{ $page == $products->currentPage() ? 'active' : '' }}">
                            <a class="page-link" href="#"
                                wire:click.prevent="gotoPage({{ $page }})"
                                style="border-radius: 4px;">
                                {{ $page }}
                            </a>
                        </li>
                    @endforeach

                    {{-- Dernière page --}}
                    @if($products->currentPage() < $products->lastPage() - 2)
                        @if($products->currentPage() < $products->lastPage() - 3)
                            <li class="page-item disabled">
                                <span class="page-link" style="border-radius: 4px;">...</span>
                            </li>
                        @endif
                        <li class="page-item">
                            <a class="page-link" href="#" wire:click.prevent="gotoPage({{ $products->lastPage() }})" style="border-radius: 4px;">{{ $products->lastPage() }}</a>
                        </li>
                    @endif

                    {{-- Lien Next --}}
                    <li class="page-item {{ !$products->hasMorePages() ? 'disabled' : '' }}">
                        <a class="page-link" href="#" wire:click.prevent="nextPage" rel="next"
                            style="border-radius: 4px;">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
@endif
    </div>

    <!-- Modal Produit -->
    <!-- Modal Produit -->
@if ($showModal)
    <div class="modal fade show d-block" style="background-color: rgba(0,0,0,0.5);" tabindex="-1"
        aria-modal="true" role="dialog">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="max-height: 90vh; display: flex; flex-direction: column;">
                <div class="modal-header bg-light border-bottom">
                    <h5 class="modal-title fw-bold text-dark">
                        <i class="bi bi-{{ $editingProductId ? 'pencil' : 'plus-circle' }} me-2"></i>
                        {{ $editingProductId ? 'Modifier le produit' : 'Nouveau produit' }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="$set('showModal', false)"></button>
                </div>

                <form wire:submit.prevent="save" style="display: flex; flex-direction: column; flex: 1; overflow: hidden;">
                    <div class="modal-body" style="overflow-y: auto; flex: 1; padding: 1rem;">
                        <!-- Ligne 1: Nom et Code -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" wire:model="name"
                                    placeholder="Nom du produit">
                                @error('name') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" wire:model="code"
                                    placeholder="Code unique">
                                @error('code') <span class="text-danger small">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <!-- Ligne 2: Description (full width) -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea class="form-control" wire:model="description" rows="2" placeholder="Description du produit..."></textarea>
                        </div>

                        <!-- Ligne 3: Prix et Stock -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Prix HT <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" step="0.01" class="form-control" wire:model.live="price">
                                    <span class="input-group-text bg-white">FCFA</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Stock <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" class="form-control" wire:model="stock" min="0">
                                    <span class="input-group-text bg-white">unités</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">
                                    Seuil d'alerte
                                    <i class="bi bi-info-circle text-muted ms-1"></i>
                                </label>
                                <input type="number" class="form-control" wire:model="stock_threshold"
                                    min="0" max="100" step="1" placeholder="5">
                            </div>
                        </div>

                        <!-- Ligne 4: TVA - Carte compacte -->
                        <div class="card bg-light border-0 mb-3">
                            <div class="card-body">
                                <h6 class="fw-semibold mb-3">
                                    <i class="bi bi-percent me-2"></i>
                                    Taxe (TVA)
                                </h6>
                                
                                <!-- Mode de saisie en ligne -->
                                <div class="row g-3">
                                    <div class="col-md-5">
                                        <label class="form-label">Mode de saisie</label>
                                        <div class="btn-group w-100" role="group">
                                            <input type="radio" class="btn-check" name="tva_input_mode"
                                                id="mode_rate" value="rate" wire:model.live="tva_input_mode">
                                            <label class="btn btn-outline-primary btn-sm" for="mode_rate">Taux (%)</label>

                                            <input type="radio" class="btn-check" name="tva_input_mode"
                                                id="mode_amount" value="amount" wire:model.live="tva_input_mode">
                                            <label class="btn btn-outline-primary btn-sm" for="mode_amount">Montant</label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-7">
                                        @if ($tva_input_mode === 'rate')
                                            <label class="form-label">Taux</label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" class="form-control" wire:model.live="tva_rate">
                                                <span class="input-group-text bg-white">%</span>
                                            </div>
                                        @else
                                            <label class="form-label">Montant TVA</label>
                                            <div class="input-group">
                                                <input type="number" step="0.01" class="form-control" wire:model.live="tva_amount">
                                                <span class="input-group-text bg-white">FCFA</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Affichage calcul automatique -->
                                @if($price > 0)
                                    <div class="mt-2 text-muted small">
                                        @if($tva_input_mode === 'rate' && $tva_rate > 0)
                                            <span>Montant TVA: {{ number_format(($price * $tva_rate) / 100, 0, ',', ' ') }} FCFA</span>
                                        @elseif($tva_input_mode === 'amount' && $tva_amount > 0)
                                            <span>Taux TVA: {{ number_format(($tva_amount / $price) * 100, 2, ',', ' ') }}%</span>
                                        @endif
                                        <span class="mx-2">|</span>
                                        <span class="fw-bold">TTC: {{ number_format($price + ($tva_input_mode === 'rate' ? ($price * $tva_rate) / 100 : $tva_amount), 0, ',', ' ') }} FCFA</span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Ligne 5: Image et Publication -->
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Image du produit</label>
                                
                                @if ($image && !$tempImage)
                                    <div class="d-flex align-items-center mb-2 p-2 border rounded">
                                        <img src="{{ asset('storage/' . $image) }}" alt="Aperçu"
                                            class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                        <span class="ms-2 small text-muted">{{ basename($image) }}</span>
                                        <button type="button" class="btn btn-sm btn-link text-danger ms-auto p-0"
                                            wire:click="$set('image', null)" wire:confirm="Supprimer cette image ?">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                @endif

                                @if ($tempImage)
                                    <div class="d-flex align-items-center mb-2 p-2 border rounded">
                                        <img src="{{ $tempImage->temporaryUrl() }}" alt="Aperçu"
                                            class="rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                        <span class="ms-2 small text-muted">{{ $tempImage->getClientOriginalName() }}</span>
                                        <button type="button" class="btn btn-sm btn-link text-danger ms-auto p-0"
                                            wire:click="$set('tempImage', null)">
                                            <i class="bi bi-x-circle"></i>
                                        </button>
                                    </div>
                                @endif

                                <input type="file" class="form-control @error('tempImage') is-invalid @enderror"
                                    wire:model="tempImage" accept="image/jpeg,image/png,image/gif">
                                @error('tempImage')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text text-muted small">
                                    Formats: JPG, PNG, GIF. Max: 2Mo
                                </div>
                            </div>
                            
                            <div class="col-md-4 d-flex flex-column justify-content-end">
                                <div class="form-check form-switch p-0">
                                    <label class="form-check-label fw-semibold mb-2 d-block">Publication</label>
                                    <div class="d-flex align-items-center">
                                        <input class="form-check-input me-2" type="checkbox" wire:model="published"
                                            id="publishedSwitch" style="width: 40px; height: 20px;">
                                        <label class="form-check-label" for="publishedSwitch">
                                            {{ $published ? 'Publié' : 'Non publié' }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('showModal', false)">
                            <i class="bi bi-x-lg me-1"></i>
                            Annuler
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-{{ $editingProductId ? 'check-lg' : 'plus-circle' }} me-1"></i>
                            {{ $editingProductId ? 'Mettre à jour' : 'Créer' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
@endif

    <!-- Modal Mouvement de Stock -->
    @if ($showStockModal && $selectedProductForStock)
        <div class="modal fade show d-block" style="background-color: rgba(0,0,0,0.5);" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title">
                            @if ($movementType === 'in')
                                <i class="bi bi-plus-circle text-success me-2"></i>Entrée de stock
                            @elseif($movementType === 'out')
                                <i class="bi bi-dash-circle text-warning me-2"></i>Sortie de stock
                            @else
                                <i class="bi bi-pencil-square text-info me-2"></i>Ajustement
                            @endif
                        </h5>
                        <button type="button" class="btn-close" wire:click="$set('showStockModal', false)"></button>
                    </div>

                    <form wire:submit.prevent="saveStockMovement">
                        <div class="modal-body">
                            <div class="bg-light p-3 rounded mb-3">
                                <div class="d-flex justify-content-between">
                                    <span class="fw-bold">{{ $selectedProductForStock->name }}</span>
                                    <span class="badge bg-secondary">Stock:
                                        {{ $selectedProductForStock->stock }}</span>
                                </div>
                                @if ($selectedProductForStock->stock_threshold > 0)
                                    <div class="small text-muted mt-1">
                                        Seuil d'alerte: {{ $selectedProductForStock->stock_threshold }}
                                    </div>
                                @endif
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Quantité</label>
                                <input type="number" class="form-control form-control-lg"
                                    wire:model="movementQuantity" min="1" autofocus>
                            </div>

                            @if ($movementType === 'in')
                                <div class="mb-3">
                                    <label class="form-label">Fournisseur</label>
                                    <select class="form-select" wire:model="movementSupplier">
                                        <option value="">-- Optionnel --</option>
                                        @foreach ($suppliers as $supplier)
                                            <option value="{{ $supplier->id }}">
                                                {{ $supplier->person?->name ?? $supplier->company_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div class="mb-3">
                                <label class="form-label">Référence</label>
                                <input type="text" class="form-control" wire:model="movementReference"
                                    placeholder="N° BL, facture...">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" wire:model="movementNotes" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-light"
                                wire:click="$set('showStockModal', false)">Annuler</button>
                            <button type="submit" class="btn btn-primary">Confirmer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Modal Historique des mouvements -->
    @if ($showHistoryModal && $selectedProductForStock)
        <div class="modal fade show d-block" style="background-color: rgba(0,0,0,0.5);" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-light">
                        <h5 class="modal-title">
                            <i class="bi bi-clock-history me-2"></i>
                            Historique - {{ $selectedProductForStock->name }}
                        </h5>
                        <button type="button" class="btn-close"
                            wire:click="$set('showHistoryModal', false)"></button>
                    </div>

                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Type</th>
                                        <th>Quantité</th>
                                        <th>Fournisseur</th>
                                        <th>Référence</th>
                                        <th>Par</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($movementHistory as $movement)
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
                                            <td>{{ $movement->user?->name }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Aucun mouvement</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light"
                            wire:click="$set('showHistoryModal', false)">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <style>
        .action-icons {
            opacity: 0.5;
            transition: opacity 0.2s;
        }

        tr:hover .action-icons {
            opacity: 1;
        }

        .action-icons button:hover {
            transform: scale(1.2);
        }

        .pagination .page-link {
            border: none;
            margin: 0;
            padding: 0.5rem 0.75rem;
            color: #6c757d;
            background: transparent;
        }

        .pagination .page-link:hover {
            background-color: #f8f9fa;
            color: #0d6efd;
        }

        .pagination .active .page-link {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
            font-weight: 500;
        }

        .pagination .page-link:focus {
            box-shadow: none;
            outline: none;
        }

        .pagination .disabled .page-link {
            color: #adb5bd;
            pointer-events: none;
        }

        /* Styles pour le tableau scrollable */
        .table-responsive {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e0 #f1f5f9;
        }

        .table-responsive::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .table-responsive::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb {
            background: #cbd5e0;
            border-radius: 4px;
        }

        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Sticky header dans le tableau */
        .table thead th {
            position: sticky;
            top: 0;
            background-color: #f8f9fa;
            z-index: 10;
            box-shadow: 0 2px 2px -1px rgba(0, 0, 0, 0.1);
        }

        /* Ajustements responsifs */
        @media (max-width: 767px) {
            .container {
                padding-left: 10px;
                padding-right: 10px;
            }

            .card-body {
                padding: 1rem;
            }

            .table td,
            .table th {
                white-space: nowrap;
            }
        }
    </style>
</div>
