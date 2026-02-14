<?php

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\Product;

new class() extends Component {
    use WithPagination;

    public string $name = '';
    public string $code = '';
    public ?string $description = '';
    public float $price = 0;
    public int $stock = 0;
    public bool $published = false;

    public $editingProductId = null;
    public $showModal = false;

    public string $search = '';
    public $filterPublished = '';
    public int $perPage = 5;

    // Nouveaux: Sélection multiple et actions groupées
    public $selectedProducts = [];
    public bool $selectAll = false;
    public array $bulkActions = [
        'publish' => 'Publier',
        'unpublish' => 'Dépublier',
        'delete' => 'Supprimer',
    ];
    public $selectedBulkAction = '';

    public function updatedSearch()
    {
        $this->resetPage();
    }
    public function updatedFilterPublished()
    {
        $this->resetPage();
    }

    // Gestion de la sélection multiple
    public function updatedSelectAll($value)
    {
        if ($value) {
            $this->selectedProducts = $this->products->pluck('id')->toArray();
        } else {
            $this->selectedProducts = [];
        }
    }

    public function updatedSelectedProducts()
    {
        $this->selectAll = count($this->selectedProducts) === $this->products->count();
    }

    // Action groupée
    public function performBulkAction()
    {
        if (empty($this->selectedProducts) || empty($this->selectedBulkAction)) {
            return;
        }

        $productIds = $this->selectedProducts;

        switch ($this->selectedBulkAction) {
            case 'publish':
                Product::whereIn('id', $productIds)->update(['published_at' => now()]);
                session()->flash('success', count($productIds) . ' produit(s) publié(s) !');
                break;
            case 'unpublish':
                Product::whereIn('id', $productIds)->update(['published_at' => null]);
                session()->flash('success', count($productIds) . ' produit(s) dépublié(s) !');
                break;
            case 'delete':
                Product::destroy($productIds);
                session()->flash('success', count($productIds) . ' produit(s) supprimé(s) !');
                break;
        }

        $this->selectedProducts = [];
        $this->selectAll = false;
        $this->selectedBulkAction = '';
    }

    // Vider la sélection (panier)
    public function clearSelection()
    {
        $this->selectedProducts = [];
        $this->selectAll = false;
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
                $this->stock = $product->stock;
                $this->published = (bool) $product->published_at;
                $this->editingProductId = $product->id;
            }
        } else {
            $this->reset(['name', 'code', 'description', 'price', 'stock', 'published', 'editingProductId']);
        }
        $this->showModal = true;
    }

    public function save()
    {
        $validated = $this->validate([
            'name' => 'required|max:255',
            'code' => 'required|unique:products,code' . ($this->editingProductId ? ',' . $this->editingProductId : ''),
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'published' => 'boolean',
        ]);

        $validated['published_at'] = $this->published ? now() : null;

        if ($this->editingProductId) {
            Product::find($this->editingProductId)->update($validated);
            session()->flash('success', 'Produit mis à jour !');
        } else {
            Product::create($validated);
            session()->flash('success', 'Produit créé !');
        }

        $this->reset(['name', 'code', 'description', 'price', 'stock', 'published', 'editingProductId']);
        $this->showModal = false;
    }

    public function delete($id)
    {
        Product::destroy($id);
        session()->flash('success', 'Produit supprimé !');
    }

    public function with(): array
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

        return ['products' => $query->latest()->paginate($this->perPage)];
    }

    // Réinitialiser la sélection
    public function resetSelection()
    {
        $this->selectedProducts = [];
        $this->selectAll = false;
    }
};

?>

<div class="container py-4">
    <!-- Flash Message -->
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show shadow-sm border-0" role="alert">
        <div class="d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div class="flex-grow-1">{{ session('success') }}</div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    @endif


    <!-- Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4">
        <div class="mb-3 mb-md-0">
            <h1 class="h2 fw-bold text-primary mb-1">Gestion des Produits</h1>
            <p class="text-muted mb-0">Créez, modifiez et gérez vos produits</p>
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
                        wire:click="$set(['search'=>'','filterPublished'=>''])">
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
                                        wire:model.live="selectAll"
                                        type="checkbox" id="selectAll">
                                </div>
                            </th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Nom</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Code</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Prix</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Stock</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom">Statut</th>
                            <th class="py-3 px-4 fw-semibold text-dark border-bottom text-end">
                                <div class="d-flex justify-content-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm p-0 border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="#" wire:click.prevent="performBulkAction('publish')">Publier</a></li>
                                            <li><a class="dropdown-item" href="#" wire:click.prevent="performBulkAction('unpublish')">Dépublier</a></li>
                                            <li>
                                                <hr class="dropdown-divider">
                                            </li>
                                            <li><a class="dropdown-item text-danger" href="#" wire:click.prevent="performBulkAction('delete')" wire:confirm="Supprimer les produits sélectionnés ?">Supprimer</a></li>
                                        </ul>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                        <tr wire:key="{{ $product->id }}">
                            <td class="py-3 px-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox"
                                        wire:model.live="selectedProducts"
                                        value="{{ $product->id }}">
                                </div>
                            </td>
                            <td class="py-3 px-4">{{ Str::limit($product->name,50) }}</td>
                            <td class="py-3 px-4">{{ $product->code }}</td>
                            <td class="py-3 px-4">{{ number_format($product->price,2) }} FCFA</td>
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
                                        wire:confirm="Êtes-vous sûr ?">
                                        <i class="bi bi-trash me-1"></i>
                                        Supprimer
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
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
            {{ $products->links() }}
        </div>
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
                                <label class="form-label fw-semibold">Prix <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" class="form-control" wire:model="price">
                            </div>
                            <div class="col">
                                <label class="form-label fw-semibold">Stock <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" wire:model="stock">
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