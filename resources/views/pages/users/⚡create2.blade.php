<?php

use Livewire\Component;
use App\Models\User;
use App\Models\Contact;
use Illuminate\Support\Facades\Hash;

new class extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public array $contacts = [
        ['code' => '+226', 'phone' => '', 'name' => '']
    ];

    protected array $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6',
        'contacts' => 'required|array|min:1',
        'contacts.*.phone' => 'required|string|unique:contacts,phone',
        'contacts.*.code' => 'required|string',
        'contacts.*.name' => 'required|string|max:255',
    ];

    protected $listeners = [
        'updateContactPhone' => 'updateContactPhone',
        'updateContactCode' => 'updateContactCode'
    ];

    public function addContact()
    {
        $this->contacts[] = ['code' => '+226', 'phone' => '', 'name' => ''];
    }

    public function removeContact($index)
    {
        unset($this->contacts[$index]);
        $this->contacts = array_values($this->contacts);
    }

    public function updateContactPhone($index, $phone)
    {
        if (isset($this->contacts[$index])) {
            $this->contacts[$index]['phone'] = $phone;
        }
    }

    public function updateContactCode($index, $code)
    {
        if (isset($this->contacts[$index])) {
            $this->contacts[$index]['code'] = $code;
        }
    }

    public function save()
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        foreach($this->contacts as $contact) {
            if(!empty($contact['phone']) && !empty($contact['name'])) {
                $user->contacts()->create([
                    'name' => $contact['name'],
                    'phone' => $contact['phone'],
                ]);
            }
        }

        // Réinitialisation simple - PAS BESOIN D'ÉVÉNEMENT
        $this->reset(['name', 'email', 'password']);
        $this->contacts = [['code' => '+226', 'phone' => '', 'name' => '']];

        session()->flash('message', 'Utilisateur créé avec succès !');
    }
};
?>

<div class="p-6 bg-white rounded shadow-md">
    @if(session()->has('message'))
        <div class="mb-4 text-green-600">{{ session('message') }}</div>
    @endif

    <form wire:submit.prevent="save">
        <!-- Nom -->
        <div class="mb-4">
            <label class="block mb-1 font-medium">Nom</label>
            <input type="text" wire:model="name" class="border p-2 w-full rounded"/>
            @error('name') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <!-- Email -->
        <div class="mb-4">
            <label class="block mb-1 font-medium">Email</label>
            <input type="email" wire:model="email" class="border p-2 w-full rounded"/>
            @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <!-- Mot de passe -->
        <div class="mb-4">
            <label class="block mb-1 font-medium">Mot de passe</label>
            <input type="password" wire:model="password" class="border p-2 w-full rounded"/>
            @error('password') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
        </div>

        <!-- Contacts -->
        <div class="mb-4">
            <label class="block mb-1 font-medium">Contacts</label>
            
            @foreach($contacts as $index => $contact)
                <div class="border p-3 mb-3 rounded" wire:key="contact-{{ $index }}">
                    <div class="row">
                        <div class="col-md-4 mb-2">
                            <input type="text" 
                                   wire:model="contacts.{{ $index }}.name" 
                                   placeholder="Nom du contact" 
                                   class="form-control"/>
                            @error('contacts.' . $index . '.name') 
                                <span class="text-red-500 text-sm">{{ $message }}</span> 
                            @enderror
                        </div>
                        <div class="col-md-6 mb-2">
                            <livewire:phone 
                                :key="'phone-'.$index"
                                :index="$index"
                                :code="$contacts[$index]['code']"
                                :phone="$contacts[$index]['phone']"
                            />
                            @error('contacts.' . $index . '.phone') 
                                <span class="text-red-500 text-sm">{{ $message }}</span> 
                            @enderror
                        </div>
                        <div class="col-md-2">
                            <button type="button" wire:click="removeContact({{ $index }})" class="btn btn-danger">
                                Supprimer
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach

            <button type="button" wire:click="addContact" class="btn btn-primary mt-2">
                + Ajouter un contact
            </button>
        </div>

        <button type="submit" class="btn btn-success px-4 py-2">
            Créer l'utilisateur
        </button>
    </form>
</div>