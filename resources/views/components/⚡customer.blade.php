<?php

use Livewire\Component;
use App\Models\Customer;

new class extends Component
{
    // Champs du formulaire
    public $phone = '';
    public $name = '';
    public $firstname = '';
    public $cnib = '';
    public $email = '';
    public $customerId = null;
    public $searchMode = true;
    public $searchLoading = false;
    
    // Liste des clients pour le select
    public $customers = [];
    public $selectedCustomer = '';

    public function mount()
    {
        // Charger la liste des clients pour le select
        $this->loadCustomers();
    }

    public function loadCustomers()
    {
        $this->customers = Customer::all()->toArray();
    }

    // Rechercher un client par téléphone
    public function searchCustomer()
    {
        $this->searchLoading = true;
        
        // Nettoyer le numéro de téléphone (enlever les espaces, +, etc.)
        $cleanPhone = preg_replace('/[^0-9]/', '', $this->phone);
        
        // Rechercher le client
        $customer = Customer::where('phone', 'like', '%' . $cleanPhone . '%')
                          ->orWhere('phone', 'like', '%' . $this->phone . '%')
                          ->first();
        
        if ($customer) {
            // Client trouvé, remplir le formulaire
            $this->customerId = $customer->id;
            $this->name = $customer->name;
            $this->firstname = $customer->firstname;
            $this->cnib = $customer->cnib;
            $this->email = $customer->email;
            $this->searchMode = false;
            
            // Sélectionner dans la liste
            $this->selectedCustomer = $customer->id;
            
            session()->flash('message', 'Client trouvé!');
        } else {
            // Client non trouvé, rester en mode saisie
            $this->searchMode = false;
            $this->customerId = null;
            $this->name = '';
            $this->firstname = '';
            $this->cnib = '';
            $this->email = '';
            $this->selectedCustomer = '';
            
            session()->flash('message', 'Client non trouvé. Remplissez les informations pour créer un nouveau client.');
        }
        
        $this->searchLoading = false;
    }

    // Quand on sélectionne un client dans la liste
    public function updatedSelectedCustomer()
    {
        if ($this->selectedCustomer) {
            $customer = Customer::find($this->selectedCustomer);
            if ($customer) {
                $this->customerId = $customer->id;
                $this->phone = $customer->phone;
                $this->name = $customer->name;
                $this->firstname = $customer->firstname;
                $this->cnib = $customer->cnib;
                $this->email = $customer->email;
                $this->searchMode = false;
            }
        }
    }

    // Réinitialiser pour une nouvelle recherche
    public function newSearch()
    {
        $this->searchMode = true;
        $this->phone = '';
        $this->customerId = null;
        $this->selectedCustomer = '';
    }

    // Enregistrer le client (création ou mise à jour)
    public function saveCustomer()
    {
        $this->validate([
            'phone' => 'required|string|max:20',
            'name' => 'required|string|max:100',
            'firstname' => 'required|string|max:100',
            'email' => 'nullable|email',
            'cnib' => 'nullable|string|max:50'
        ]);

        if ($this->customerId) {
            // Mise à jour du client existant
            $customer = Customer::find($this->customerId);
            $customer->update([
                'phone' => $this->phone,
                'name' => $this->name,
                'firstname' => $this->firstname,
                'cnib' => $this->cnib,
                'email' => $this->email
            ]);
            
            session()->flash('success', 'Client mis à jour avec succès!');
        } else {
            // Création d'un nouveau client
            $customer = Customer::create([
                'phone' => $this->phone,
                'name' => $this->name,
                'firstname' => $this->firstname,
                'cnib' => $this->cnib,
                'email' => $this->email
            ]);
            
            $this->customerId = $customer->id;
            session()->flash('success', 'Nouveau client créé avec succès!');
        }

        // Recharger la liste des clients
        $this->loadCustomers();
    }

    // Computed property pour savoir si on peut enregistrer
    public function getCanSaveProperty()
    {
        return !empty($this->phone) && !empty($this->name) && !empty($this->firstname);
    }
};

?>



    <div class="section-card h-100 px-2">
        <div class="card-header-custom p-3">
            <h3 class="h5 mb-0"><i class="fas fa-user me-2"></i> Client</h3>
        </div>
        <div class="card-body-custom">
            @if(session('message'))
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    {{ session('message') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            
            <form wire:submit.prevent="{{ $searchMode ? 'searchCustomer' : 'saveCustomer' }}">
                <!-- Mode recherche par téléphone -->
                @if($searchMode)
                    <div class="mb-4">
                        <label for="phone" class="form-label fw-bold">
                            <i class="fas fa-phone me-1"></i> Rechercher par téléphone
                        </label>
                        <div class="input-group">
                            <input
                                type="tel"
                                class="form-control"
                                id="phone"
                                wire:model.live.debounce.500ms="phone"
                                placeholder="Ex : +226 70 00 00 00"
                                autofocus
                            />
                            <button class="btn btn-outline-primary" type="button" wire:click="searchCustomer" 
                                    @if(empty($phone)) disabled @endif>
                                @if($searchLoading)
                                    <i class="fas fa-spinner fa-spin"></i>
                                @else
                                    <i class="fas fa-search"></i>
                                @endif
                            </button>
                        </div>
                        <div class="form-text">Entrez le numéro de téléphone pour rechercher un client existant</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="profil" class="form-label">Ou sélectionner un client</label>
                        <select class="form-select" id="profil" wire:model.live="selectedCustomer">
                            <option value="">-- Sélectionnez un client --</option>
                            @foreach($customers as $customer)
                                <option value="{{ $customer['id'] }}">
                                    {{ $customer['name'] }} {{ $customer['firstname'] }} ({{ $customer['phone'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="$set('searchMode', false)">
                            <i class="fas fa-user-plus me-1"></i> Créer un nouveau client
                        </button>
                    </div>
                
                <!-- Mode saisie du formulaire -->
                @else
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">
                            @if($customerId)
                                <i class="fas fa-user-check text-success me-1"></i> Client existant
                            @else
                                <i class="fas fa-user-plus text-primary me-1"></i> Nouveau client
                            @endif
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="newSearch">
                            <i class="fas fa-search me-1"></i> Nouvelle recherche
                        </button>
                    </div>

                    <div class="mb-3">
                        <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            class="form-control"
                            id="nom"
                            wire:model="name"
                            placeholder="Entrez votre nom"
                            required
                        />
                    </div>

                    <div class="mb-3">
                        <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            class="form-control"
                            id="prenom"
                            wire:model="firstname"
                            placeholder="Entrez votre prénom"
                            required
                        />
                    </div>

                    <div class="mb-3">
                        <label for="telephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                        <input
                            type="tel"
                            class="form-control"
                            id="telephone"
                            wire:model="phone"
                            placeholder="Ex : +226 70 00 00 00"
                            required
                        />
                    </div>

                    <div class="mb-3">
                        <label for="cnib" class="form-label">CNIB</label>
                        <input
                            type="text"
                            class="form-control"
                            id="cnib"
                            wire:model="cnib"
                            placeholder="Numéro CNIB"
                        />
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input
                            type="email"
                            class="form-control"
                            id="email"
                            wire:model="email"
                            placeholder="exemple@email.com"
                        />
                    </div>
                @endif
            </form>
        </div>
        
        @if(!$searchMode)
        <div class="card-footer-custom">
            <button type="button" class="btn btn-primary w-100" wire:click="saveCustomer" 
                    @if(!$this->canSave) disabled @endif>
                @if($customerId)
                    <i class="fas fa-save me-1"></i> Mettre à jour
                @else
                    <i class="fas fa-user-plus me-1"></i> Enregistrer client
                @endif
            </button>
        </div>
        @endif
    </div>