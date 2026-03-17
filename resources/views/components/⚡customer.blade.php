<?php

use Livewire\Component;
use App\Models\Person;
use App\Models\Customer;

new class extends Component {
    // Champs du formulaire
    public $phone = '';
    public $name = '';
    public $firstname = '';
    public $cnib = '';
    public $email = '';
    public $personId = null;
    public $customerId = null;
    public $searchMode = true;
    public $viewMode = false;

    // Pour le custom select
    public $people = [];
    public $personSearch = '';
    public $dropdownOpen = false;
    public $selectedPersonId = null;

    // Dans le composant enfant (customer)
    protected $listeners = [
        'clearCustomerSelection' => 'resetSelection',
        'clearCustomerFromPay' => 'resetSelection',
    ];

    public function resetSelection()
    {
        $this->selectedPersonId = null;
        $this->personId = null;
        $this->customerId = null;
        $this->name = '';
        $this->firstname = '';
        $this->phone = '';
        $this->email = '';
        $this->cnib = '';
        $this->searchMode = true;
        $this->viewMode = false;
        $this->dropdownOpen = false;
        $this->personSearch = '';

        // Recharger les listes
        $this->loadPeople();
        $this->loadPinnedCustomers();
    }

    // Pour les clients épinglés
    public $pinnedCustomers = [];

    public function mount()
    {
        $this->loadPeople();
        $this->loadPinnedCustomers();
    }

    public function loadPinnedCustomers()
    {
        // Récupère les 5 derniers clients
        $this->pinnedCustomers = Customer::with('person')
            ->whereHas('person')
            ->latest()
            ->limit(5)
            ->get()
            ->filter(fn($c) => $c->person)
            ->map(
                fn($c) => [
                    'id' => $c->id,
                    'person_id' => $c->person->id,
                    'name' => $c->person->name,
                    'firstname' => $c->person->firstname,
                    'phone' => $c->person->phone,
                    'initials' => strtoupper(substr($c->person->firstname, 0, 1) . substr($c->person->name, 0, 1)),
                ],
            )
            ->values()
            ->toArray();
    }

    public function loadPeople()
    {
        $query = Customer::with('person');

        if (!empty($this->personSearch)) {
            $query->whereHas('person', function ($q) {
                $q->where('name', 'like', '%' . $this->personSearch . '%')
                    ->orWhere('firstname', 'like', '%' . $this->personSearch . '%')
                    ->orWhere('phone', 'like', '%' . $this->personSearch . '%');
            });
        }

        $this->people = $query
            ->limit(20)
            ->get()
            ->filter(fn($c) => $c->person)
            ->map(
                fn($c) => [
                    'id' => $c->id,
                    'person_id' => $c->person->id,
                    'name' => $c->person->name,
                    'firstname' => $c->person->firstname,
                    'phone' => $c->person->phone,
                    'email' => $c->person->email,
                    'cnib' => $c->person->cnib,
                    'display' => $c->person->name . ' ' . $c->person->firstname . ' - ' . $c->person->phone,
                ],
            )
            ->values()
            ->toArray();
    }

    public function updatedPersonSearch()
    {
        $this->loadPeople();
    }

    public function toggleDropdown()
    {
        $this->dropdownOpen = !$this->dropdownOpen;
        if ($this->dropdownOpen) {
            $this->personSearch = '';
            $this->loadPeople();
        }
    }

    // À ajouter dans la méthode selectPerson() après avoir chargé les données
    public function selectPerson($id)
    {
        \Log::info('selectPerson appelé avec ID: ' . $id);

        $this->selectedPersonId = $id;
        $this->dropdownOpen = false;

        $customer = Customer::with('person')->find($id);
        if ($customer && $customer->person) {
            $this->personId = $customer->person->id;
            $this->customerId = $customer->id;
            $this->name = $customer->person->name;
            $this->firstname = $customer->person->firstname;
            $this->phone = $customer->person->phone;
            $this->email = $customer->person->email;
            $this->cnib = $customer->person->cnib;

            // DISPATCHER LE CLIENT SÉLECTIONNÉ
            $this->dispatch('customerSelected', [
                'id' => $customer->id,
                'person_id' => $customer->person->id,
                'name' => $customer->person->name,
                'firstname' => $customer->person->firstname,
                'phone' => $customer->person->phone,
                'email' => $customer->person->email,
                'full_name' => $customer->person->firstname . ' ' . $customer->person->name,
            ]);
        }

        $this->loadPeople();
        $this->loadPinnedCustomers();
    }

    // À ajouter dans la méthode savePerson() après la création/mise à jour
    public function savePerson()
    {
        $this->validate([
            'phone' => 'required|string|max:20',
            'name' => 'required|string|max:100',
            'firstname' => 'required|string|max:100',
            'email' => 'nullable|email',
            'cnib' => 'nullable|string|max:50',
        ]);

        if ($this->personId) {
            $person = Person::find($this->personId);
            $person->update([
                'phone' => $this->phone,
                'name' => $this->name,
                'firstname' => $this->firstname,
                'cnib' => $this->cnib,
                'email' => $this->email,
            ]);

            // DISPATCHER LE CLIENT MIS À JOUR
            $this->dispatch('customerUpdated', [
                'id' => $this->customerId,
                'person_id' => $person->id,
                'name' => $person->name,
                'firstname' => $person->firstname,
                'phone' => $person->phone,
                'email' => $person->email,
                'full_name' => $person->firstname . ' ' . $person->name,
            ]);

            session()->flash('message', 'Client mis à jour!');
        } else {
            $person = Person::create([
                'phone' => $this->phone,
                'name' => $this->name,
                'firstname' => $this->firstname,
                'cnib' => $this->cnib,
                'email' => $this->email,
            ]);

            $customer = Customer::create([
                'person_id' => $person->id,
                'customer_number' => 'CUST-' . str_pad($person->id, 5, '0', STR_PAD_LEFT),
            ]);

            $this->customerId = $customer->id;
            $this->selectedPersonId = $customer->id;

            // DISPATCHER LE NOUVEAU CLIENT
            $this->dispatch('customerCreated', [
                'id' => $customer->id,
                'person_id' => $person->id,
                'name' => $person->name,
                'firstname' => $person->firstname,
                'phone' => $person->phone,
                'email' => $person->email,
                'full_name' => $person->firstname . ' ' . $person->name,
            ]);

            session()->flash('message', 'Nouveau client créé!');
        }

        $this->personId = $person->id;
        $this->loadPeople();
        $this->loadPinnedCustomers();
    }

    // Optionnel : méthode pour dispatcher manuellement
    public function dispatchCustomer()
    {
        if ($this->customerId) {
            $this->dispatch('customerSelected', [
                'id' => $this->customerId,
                'person_id' => $this->personId,
                'name' => $this->name,
                'firstname' => $this->firstname,
                'phone' => $this->phone,
                'email' => $this->email,
                'full_name' => $this->firstname . ' ' . $this->name,
            ]);
        }
    }

    public function editPerson($personId)
    {
        $this->selectPerson($personId);
        $this->searchMode = false;
        $this->viewMode = false;
    }

    public function viewPersonDetails($personId)
    {
        $this->selectPerson($personId);
        $this->searchMode = false;
        $this->viewMode = true;
    }

    public function newPerson()
    {
        $this->searchMode = false;
        $this->viewMode = false;
        $this->resetPersonForm();
        $this->selectedPersonId = null;
        $this->dropdownOpen = false;
    }

    public function newSearch()
    {
        $this->searchMode = true;
        $this->viewMode = false;
        $this->personSearch = '';
        $this->dropdownOpen = false;
        $this->loadPeople();
        $this->loadPinnedCustomers();
    }

    protected function resetPersonForm()
    {
        $this->personId = null;
        $this->customerId = null;
        $this->name = '';
        $this->firstname = '';
        $this->cnib = '';
        $this->email = '';
        $this->phone = '';
    }

    public function getSelectedPersonProperty()
    {
        if (!$this->selectedPersonId) {
            return null;
        }

        return [
            'id' => $this->selectedPersonId,
            'name' => $this->name,
            'firstname' => $this->firstname,
            'phone' => $this->phone,
            'email' => $this->email,
            'cnib' => $this->cnib,
        ];
    }

    public function getTitleProperty()
    {
        return 'Client';
    }
};

?>

<div class="section-card h-100 px-2">
    <div class="card-header-custom p-3">
        <h3 class="h5 mb-0"><i class="fas fa-user me-2"></i> {{ $this->title }}</h3>
    </div>

    <div class="card-body-custom">
        @if (session()->has('message'))
            <div class="alert alert-success mb-3">{{ session('message') }}</div>
        @endif

        @if ($searchMode)
            <!-- SELECTEUR -->
            <div class="mb-4">
                <label class="form-label fw-bold">
                    <i class="fas fa-users me-1"></i> Sélectionner un client
                </label>

                <div class="custom-select-container" wire:ignore.self>
                    <!-- Bouton -->
                    <div class="custom-select-trigger d-flex justify-content-between align-items-center w-100"
                        wire:click="toggleDropdown"
                        style="border:1px solid #ced4da; border-radius:0.375rem; padding:0.5rem 1rem; cursor:pointer; background:white;">
                        <span class="{{ $this->selectedPerson ? '' : 'text-muted' }}">
                            @if ($this->selectedPerson)
                                {{ $this->selectedPerson['name'] }} {{ $this->selectedPerson['firstname'] }} -
                                {{ $this->selectedPerson['phone'] }}
                            @else
                                Choisir un client...
                            @endif
                        </span>
                        <i class="fas fa-chevron-down"></i>
                    </div>

                    <!-- Dropdown -->
                    @if ($dropdownOpen)
                        <div class="custom-select-dropdown"
                            style="position:absolute; width:100%; z-index:1000; background:white; border:1px solid #ced4da; border-radius:0.375rem; margin-top:0.25rem; box-shadow:0 2px 4px rgba(0,0,0,0.1);">

                            <!-- Recherche -->
                            <div style="padding:0.5rem; border-bottom:1px solid #e9ecef;">
                                <input type="text" class="form-control form-control-sm"
                                    wire:model.live.debounce.300ms="personSearch" placeholder="Rechercher..."
                                    autofocus />
                            </div>

                            <!-- Liste -->
                            <div style="max-height:300px; overflow-y:auto;">
                                @forelse($people as $person)
                                    <div wire:key="person-{{ $person['id'] }}"
                                        class="dropdown-item d-flex justify-content-between align-items-center person-item"
                                        style="padding:0.5rem 1rem; border-bottom:1px solid #f1f3f5; {{ $selectedPersonId == $person['id'] ? 'background-color:#d4edda;' : '' }}">

                                        <!-- Partie cliquable pour la sélection -->
                                        <div class="flex-grow-1" wire:click="selectPerson({{ $person['id'] }})"
                                            style="cursor:pointer;">
                                            <div class="fw-bold">{{ $person['name'] }} {{ $person['firstname'] }}
                                            </div>
                                            <small class="text-muted">{{ $person['phone'] }}</small>
                                        </div>

                                        <!-- Actions -->
                                        <div class="person-actions" style="white-space:nowrap;">
                                            <button type="button" class="btn btn-sm btn-link text-primary p-0 me-2"
                                                wire:click="editPerson({{ $person['id'] }})" title="Modifier">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-link text-info p-0"
                                                wire:click="viewPersonDetails({{ $person['id'] }})" title="Détails">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                @empty
                                    <div class="text-center py-4 px-3">
                                        <i class="fas fa-user-slash fa-2x text-muted mb-2"></i>
                                        <p class="text-muted mb-2">Aucun client trouvé</p>
                                        <button type="button" class="btn btn-sm btn-outline-primary w-100"
                                            wire:click="newPerson">
                                            <i class="fas fa-plus-circle me-1"></i>
                                            Créer un nouveau client
                                        </button>
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Boutons sous le select -->
            <div class="d-flex gap-2 mb-3">
                <button type="button" class="btn btn-outline-secondary flex-grow-1" wire:click="newPerson">
                    <i class="fas fa-user-plus me-1"></i> Nouveau
                </button>
                @if ($this->selectedPerson)
                    <button type="button" class="btn btn-outline-primary"
                        wire:click="editPerson({{ $selectedPersonId }})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-outline-info"
                        wire:click="viewPersonDetails({{ $selectedPersonId }})">
                        <i class="fas fa-eye"></i>
                    </button>
                @endif
            </div>

            <!-- CLIENTS ÉPINGLÉS -->
            @if (count($pinnedCustomers) > 0)
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted mb-2">
                        <i class="fas fa-thumbtack me-1"></i> Clients récents
                    </label>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($pinnedCustomers as $pinned)
                            <div wire:key="pinned-customer-{{ $pinned['id'] }}"
                                class="pinned-item d-flex align-items-center gap-2 p-2 rounded border"
                                style="cursor:pointer; background-color: {{ $selectedPersonId == $pinned['id'] ? '#d4edda' : '#f8f9fa' }}; border-color: #dee2e6;"
                                wire:click="selectPerson({{ $pinned['id'] }})"
                                title="{{ $pinned['firstname'] }} {{ $pinned['name'] }} - {{ $pinned['phone'] }}">
                                <div class="pinned-initials rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                                    style="width: 32px; height: 32px; font-size: 14px; font-weight: bold;">
                                    {{ $pinned['initials'] }}
                                </div>
                                <div class="pinned-info" style="line-height: 1.2;">
                                    <div class="small fw-bold">{{ $pinned['firstname'] }} {{ $pinned['name'] }}</div>
                                    <small class="text-muted">{{ $pinned['phone'] }}</small>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @else
            <!-- DÉTAILS OU FORMULAIRE -->
            <div wire:key="customer-section-{{ $selectedPersonId ?? 'new' }}-{{ $viewMode ? 'details' : 'form' }}">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="mb-0">
                        @if ($viewMode)
                            <span class="badge bg-info">Détails client</span>
                        @elseif($this->selectedPerson)
                            <span class="badge bg-success">Modification client</span>
                        @else
                            <span class="badge bg-primary">Nouveau client</span>
                        @endif
                    </h6>
                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="newSearch">
                        <i class="fas fa-arrow-left me-1"></i> Retour
                    </button>
                </div>

                @if ($viewMode)
                    <!-- MODE DÉTAILS - Affichage seul -->
                    <div class="details-view" wire:key="details-{{ $selectedPersonId }}">
                        <div class="text-center mb-4">
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3"
                                style="width: 100px; height: 100px;">
                                <i class="fas fa-user-circle fa-4x text-primary"></i>
                            </div>
                            <h4>{{ $name }} {{ $firstname }}</h4>
                            <span class="badge bg-primary">Client</span>
                        </div>

                        <div class="bg-light p-3 rounded-3 mb-4">
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Téléphone</span>
                                <span class="fw-bold">{{ $phone }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">Email</span>
                                <span class="fw-bold">{{ $email ?: 'Non renseigné' }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-2 border-bottom">
                                <span class="text-muted">CNIB</span>
                                <span class="fw-bold">{{ $cnib ?: 'Non renseigné' }}</span>
                            </div>
                            @if ($customerId)
                                <div class="d-flex justify-content-between py-2">
                                    <span class="text-muted">N° Client</span>
                                    <span class="fw-bold">#{{ str_pad($customerId, 5, '0', STR_PAD_LEFT) }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-outline-primary flex-grow-1"
                                wire:click="editPerson({{ $selectedPersonId }})">
                                <i class="fas fa-edit me-1"></i> Modifier
                            </button>
                            <button type="button" class="btn btn-outline-secondary flex-grow-1"
                                wire:click="newSearch">
                                <i class="fas fa-check me-1"></i> OK
                            </button>
                        </div>
                    </div>
                @else
                    <!-- MODE FORMULAIRE (Création/Modification) -->
                    <form wire:submit.prevent="savePerson" wire:key="form-{{ $selectedPersonId ?? 'new' }}">
                        <div class="mb-3">
                            <label class="form-label">Nom *</label>
                            <input type="text" class="form-control" wire:model.live="name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Prénom *</label>
                            <input type="text" class="form-control" wire:model.live="firstname">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Téléphone *</label>
                            <input type="text" class="form-control" wire:model.live="phone">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">CNIB</label>
                            <input type="text" class="form-control" wire:model="cnib">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" wire:model="email">
                        </div>

                        <button type="submit" class="btn btn-primary w-100"
                            @if (empty($name) || empty($firstname) || empty($phone)) disabled @endif>
                            @if ($personId)
                                Mettre à jour
                            @else
                                Enregistrer
                            @endif
                        </button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    <style>
        .custom-select-container {
            position: relative;
            width: 100%;
        }

        .person-actions {
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .person-item:hover .person-actions {
            opacity: 1;
        }

        .dropdown-item[style*="background-color: #d4edda"] {
            border: none !important;
        }

        .d-flex.gap-2 {
            display: flex;
            gap: 0.5rem;
        }

        .flex-grow-1 {
            flex: 1;
        }

        .pinned-item {
            transition: all 0.2s ease;
            min-width: 160px;
            flex: 0 1 auto;
        }

        .pinned-item:hover {
            background-color: #e9ecef !important;
            border-color: #adb5bd !important;
        }

        .pinned-initials {
            flex-shrink: 0;
        }

        .pinned-info {
            overflow: hidden;
        }

        .pinned-info div,
        .pinned-info small {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100px;
        }
    </style>
</div>
