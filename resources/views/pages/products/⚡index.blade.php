<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Product;

new class () extends Component {
    use WithPagination;

    public string $name = '';
    public string $code = '';
    public ?string $description = '';
    public $price = '';
    public $tva_rate = '';
    public $tva_amount = '';
    public $stock = '';
    public bool $published = false;
    public string $tva_input_mode = 'rate';

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

    // Calculs simples - on ne touche JAMAIS à la valeur saisie
 public function updatedTvaRate()
{
    if ($this->tva_input_mode === 'rate') {
        $price = floatval($this->price);
        $rate  = floatval($this->tva_rate);

        // Calcul pour affichage uniquement
        $this->calculated_tva_amount = $price > 0 ? ($price * $rate) / 100 : 0;
    }
}

public function updatedTvaAmount()
{
    if ($this->tva_input_mode === 'amount') {
        $price  = floatval($this->price);
        $amount = floatval($this->tva_amount);

        // Calcul pour affichage uniquement
        $this->calculated_tva_rate = $price > 0 ? ($amount / $price) * 100 : 0;
    }
}


public function updatedTvaInputMode($mode)
{
    // On ne modifie jamais la valeur saisie
    // On met juste à jour les calculs pour affichage
    $this->updatedTvaRate();
    $this->updatedTvaAmount();
}


    public function getPriceTtcProperty()
{
    $price  = floatval($this->price);
    $amount = floatval($this->tva_amount);

    return $price + $amount;
}

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

        $this->selectAll = !empty($selectedOnCurrentPage) &&
                          count($selectedOnCurrentPage) === count($currentPageProductIds);
    }

    private function getProductsForCurrentPage()
    {
        $query = Product::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('description', 'like', "%{$this->search}%");
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
                $this->published = !is_null($product->published_at);
                $this->editingProductId = $product->id;
            }
        } else {
            $this->reset(['name', 'code', 'description', 'published', 'editingProductId']);
            $this->price = '';
            $this->tva_rate = '';
            $this->tva_amount = '';
            $this->stock = '';
            $this->tva_input_mode = 'rate';
        }
        $this->showModal = true;
    }

    public function save()
{
    // Validation des champs requis
    $validated = $this->validate([
        'name' => 'required|max:255',
        'code' => 'required|unique:products,code' . ($this->editingProductId ? ',' . $this->editingProductId : ''),
        'description' => 'nullable|string',
        'published' => 'boolean',
    ]);

    // Préparation des valeurs numériques
    $price = $this->price === '' ? 0 : floatval($this->price);
    $stock = $this->stock === '' ? 0 : intval($this->stock);
    
    // Calcul de la TVA au moment de la sauvegarde
    if ($this->tva_input_mode === 'amount' && $price > 0) {
        // Mode montant : on calcule le taux à partir du montant saisi
        $amount = floatval($this->tva_amount);
        $tva_rate = $amount > 0 ? ($amount / $price) * 100 : 0;
    } else {
        // Mode pourcentage : on prend le taux saisi directement
        $tva_rate = $this->tva_rate === '' ? 0 : floatval($this->tva_rate);
    }

    // Construction du tableau de données
    $data = [
        'name' => $this->name,
        'code' => $this->code,
        'description' => $this->description,
        'price' => $price,
        'tva_rate' => $tva_rate,  // ✅ Valeur calculée au moment de la sauvegarde
        'stock' => $stock,
        'published_at' => $this->published ? now() : null,
    ];

    // Sauvegarde en base
    if ($this->editingProductId) {
        // Mise à jour
        $product = Product::find($this->editingProductId);
        $product->update($data);
        session()->flash('success', 'Produit mis à jour avec succès !');
    } else {
        // Création
        Product::create($data);
        session()->flash('success', 'Produit créé avec succès !');
    }

    // Réinitialisation du formulaire
    $this->reset(['name', 'code', 'description', 'published', 'editingProductId']);
    $this->price = '';
    $this->tva_rate = '';
    $this->tva_amount = '';
    $this->stock = '';
    $this->tva_input_mode = 'rate';
    $this->showModal = false;
}

    public function delete($id)
    {
        Product::destroy($id);
        session()->flash('success', 'Produit supprimé !');
    }

    public function with(): array
    {
        return ['products' => $this->getProductsForCurrentPage()];
    }
};
?>


<div class="container py-4">
    <!-- Flash Messages -->
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div class="flex-grow-1">{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    @endif

    @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
            <div class="flex-grow-1">{{ session('error') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    @endif

    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h1 class="h2 fw-bold text-white mb-1">Gestion des Produits</h1>
        </div>
        <button class="btn btn-primary d-flex align-items-center shadow-sm" wire:click="openModal()">
            <i class="bi bi-plus-circle me-2"></i>
            Nouveau Produit
        </button>
    </div>

    <!-- Filtres -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold text-dark mb-2">Rechercher</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text"
                            class="form-control border-start-0"
                            placeholder="Rechercher par nom ou description..."
                            wire:model.live.debounce.300ms="search">
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

    <!-- Table des produits -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="py-3 px-4" width="50">
                                <div class="form-check">
                                    <input class="form-check-input"
                                        type="checkbox" 
                                        wire:model.live="selectAll"
                                        wire:key="select-all-checkbox-{{ count($selectedProducts) }}"
                                        id="selectAll">
                                </div>
                            </th>
                            
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Nom</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Code</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Prix HT</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">TVA</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Prix TTC</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Stock</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Statut</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom text-end">Actions</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom text-end">
                                <div class="d-flex justify-content-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li>
                                                <button class="dropdown-item" wire:click="performBulkAction('publish')">
                                                    Publier
                                                </button>
                                            </li>
                                            <li>
                                                <button class="dropdown-item" wire:click="performBulkAction('unpublish')">
                                                    Dépublier
                                                </button>
                                            </li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li>
                                                <button class="dropdown-item text-danger" wire:click="performBulkAction('delete')" 
                                                    wire:confirm="Êtes-vous sûr de vouloir supprimer les produits sélectionnés ?">
                                                    Supprimer
                                                </button>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                        @php
                            $tvaAmount = ($product->price * $product->tva_rate) / 100;
                        @endphp
                        <tr wire:key="{{ $product->id }}" class="{{ in_array($product->id, $selectedProducts) ? 'table-primary' : '' }}">
                            <td class="py-3 px-4">
                                <div class="form-check">
                                    <input class="form-check-input" 
    type="checkbox"
    value="{{ $product->id }}"
    wire:model.live="selectedProducts"
    wire:key="checkbox-{{ $product->id }}-{{ in_array($product->id, $selectedProducts) ? 'checked' : 'unchecked' }}-{{ rand() }}">
                                </div>
                            </td>

                            <td class="py-3 px-4">{{ Str::limit($product->name, 50) }}</td>
                            <td class="py-3 px-4">{{ $product->code }}</td>
                            <td class="py-3 px-4">{{ number_format($product->price, 0, ',', ' ') }} FCFA</td>
                            <td class="py-3 px-4">
                                {{ $product->tva_rate }}% ({{ number_format($tvaAmount, 0, ',', ' ') }} FCFA)
                            </td>
                            <td class="py-3 px-4">{{ number_format($product->price + $tvaAmount, 0, ',', ' ') }} FCFA</td>
                            <td class="py-3 px-4">{{ $product->stock }}</td>
                            <td class="py-3 px-4">
                                @if($product->published_at)
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
                                <div class="d-flex justify-content-end gap-2">
                                    <button class="btn btn-outline-primary btn-sm d-flex align-items-center"
                                        wire:click="openModal({{ $product->id }})">
                                        <i class="bi bi-pencil me-1"></i>
                                        Modifier
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm d-flex align-items-center"
                                        wire:click="delete({{ $product->id }})"
                                        wire:confirm="Êtes-vous sûr de vouloir supprimer ce produit ?">
                                        <i class="bi bi-trash me-1"></i>
                                        Supprimer
                                    </button>
                                </div>
                            </td>
                            <td class="py-3 px-4 text-end"></td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-box-seam display-5 mb-3"></i>
                                <h5 class="fw-semibold mb-2">Aucun produit trouvé</h5>
                                <p>{{ $search ? 'Aucun résultat pour votre recherche.' : 'Commencez par créer votre premier produit.' }}</p>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

   @if($products->hasPages())
<div class="card-footer bg-white border-top-0 pt-3">
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-muted small">
            Affichage de {{ $products->firstItem() }} à {{ $products->lastItem() }} sur {{ $products->total() }} produits
        </div>
        
        <nav aria-label="Pagination">
            <ul class="pagination my-0" style="gap: 2px;">
                {{-- Previous --}}
                <li class="page-item {{ $products->onFirstPage() ? 'disabled' : '' }}">
                    <a class="page-link" href="#" wire:click.prevent="previousPage" rel="prev" style="border-radius: 4px;">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>

                {{-- Pages --}}
                @foreach ($products->getUrlRange(1, $products->lastPage()) as $page => $url)
                    <li class="page-item {{ $page == $products->currentPage() ? 'active' : '' }}">
                        <a class="page-link" href="#" wire:click.prevent="gotoPage({{ $page }})" style="border-radius: 4px;">
                            {{ $page }}
                        </a>
                    </li>
                @endforeach

                {{-- Next --}}
                <li class="page-item {{ !$products->hasMorePages() ? 'disabled' : '' }}">
                    <a class="page-link" href="#" wire:click.prevent="nextPage" rel="next" style="border-radius: 4px;">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>
</div>

<style>
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
</style>
@endif
    </div>

    <!-- Modal Produit -->
    @if($showModal)
    <div class="modal fade show d-block" style="background-color: rgba(0,0,0,0.5);" tabindex="-1" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-light border-bottom">
                    <h5 class="modal-title fw-bold text-dark">
                        {{ $editingProductId ? 'Modifier le produit' : 'Nouveau produit' }}
                    </h5>
                    <button type="button" class="btn-close" wire:click="$set('showModal', false)"></button>
                </div>

                <form wire:submit.prevent="save">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" wire:model="name" placeholder="Nom du produit">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" wire:model="code" placeholder="Code unique du produit">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea class="form-control" wire:model="description" rows="3"></textarea>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col">
                                <label class="form-label fw-semibold">Prix HT <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" wire:model.live="price">
                            </div>
                            <div class="col">
                                <label class="form-label fw-semibold">Stock <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" wire:model="stock">
                            </div>
                        </div>

                        <!-- Section TVA avec double saisie -->
<div class="card bg-light border-0 mb-3">
    <div class="card-body">
        <h6 class="fw-semibold mb-3">Taxe (TVA)</h6>
        
        <!-- Mode de saisie -->
        <div class="mb-3">
            <label class="form-label">Mode de saisie</label>
            <div class="btn-group w-100" role="group">
                <input type="radio" class="btn-check" name="tva_input_mode" id="mode_rate" value="rate" wire:model.live="tva_input_mode">
                <label class="btn btn-outline-primary" for="mode_rate">Saisir en %</label>
                
                <input type="radio" class="btn-check" name="tva_input_mode" id="mode_amount" value="amount" wire:model.live="tva_input_mode">
                <label class="btn btn-outline-primary" for="mode_amount">Saisir en FCFA</label>
            </div>
        </div>

        <!-- Saisie par taux -->
        @if($tva_input_mode === 'rate')
        <div class="mb-3">
            <label class="form-label">Taux (%)</label>
            <div class="input-group">
                <input type="number" step="0.01" class="form-control" wire:model.live="tva_rate">
                <span class="input-group-text bg-white">%</span>
            </div>
            @if($price > 0 && $tva_rate > 0)
            @php
                $montantTva = (floatval($price) * floatval($tva_rate)) / 100;
            @endphp
            <div class="form-text text-success">
                Montant TVA: {{ number_format($montantTva, 0, ',', ' ') }} FCFA
            </div>
            @endif
        </div>
        @else
        <!-- Saisie par montant -->
        <div class="mb-3">
            <label class="form-label">Montant TVA (FCFA)</label>
            <div class="input-group">
                <input type="number" step="0.01" class="form-control" wire:model.live="tva_amount">
                <span class="input-group-text bg-white">FCFA</span>
            </div>
            @if($price > 0 && $tva_amount > 0)
            @php
                $tauxEquivalant = (floatval($tva_amount) / floatval($price)) * 100;
            @endphp
            <div class="form-text text-success">
                Taux équivalent: {{ number_format($tauxEquivalant, 2) }}%
            </div>
            @endif
        </div>
        @endif

        <!-- Prix TTC -->
        @if($price > 0 && ($tva_rate > 0 || $tva_amount > 0))
        <div class="mt-3 p-2 bg-white rounded">
            <div class="d-flex justify-content-between">
                <span class="text-secondary">Prix TTC:</span>
                <span class="fw-bold text-success">
                    @php
                        $prix = floatval($price);
                        $montant = $tva_input_mode === 'rate' 
                            ? ($prix * floatval($tva_rate)) / 100 
                            : floatval($tva_amount);
                    @endphp
                    {{ number_format($prix + $montant, 0, ',', ' ') }} FCFA
                </span>
            </div>
        </div>
        @endif
    </div>
</div>

                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" wire:model="published" id="publishedSwitch">
                            <label class="form-check-label fw-semibold" for="publishedSwitch">Publié</label>
                        </div>
                    </div>

                    <div class="modal-footer border-top">
                        <button type="button" class="btn btn-outline-secondary" wire:click="$set('showModal', false)">Annuler</button>
                        <button type="submit" class="btn btn-primary">{{ $editingProductId ? 'Mettre à jour' : 'Créer' }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif
</div>