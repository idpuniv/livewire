<?php

use Livewire\Component;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

new class () extends Component {
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public array $contacts = [
        ['name' => '', 'phone' => '']
    ];

    protected array $rules = [
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6',
        'contacts' => 'required|array',
        'contacts.*.name' => 'required|string',
        'contacts.*.phone' => 'required|string|unique:contacts,phone',
    ];

    // Ajouter un contact
    public function addContact()
    {
        $this->contacts[] = ['name' => '', 'phone' => ''];
    }

    // Supprimer un contact
    public function removeContact($index)
    {
        unset($this->contacts[$index]);
        $this->contacts = array_values($this->contacts);
    }

    // Enregistrer l'utilisateur + contacts
    public function save()
    {
        $this->validate();

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => Hash::make($this->password),
        ]);

        // createMany pour tous les contacts
        $user->contacts()->createMany(
            collect($this->contacts)
                ->filter(fn ($c) => !empty($c['name']) && !empty($c['phone']))
                ->toArray()
        );

        $this->reset(['name', 'email', 'password', 'contacts']);
        $this->contacts = [['name' => '', 'phone' => '']];

        session()->flash('message', 'Utilisateur créé avec succès !');
    }
};

?>

<div class="p-6 bg-white rounded shadow-md">
    @if(session()->has('message'))
        <div class="mb-4 text-green-600">{{ session('message') }}</div>
    @endif

    <form wire:submit.prevent="save">
        <div class="mb-4">
            <label>Nom</label>
            <input type="text" wire:model.defer="name" class="border p-2 w-full"/>
            @error('name') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <div class="mb-4">
            <label>Email</label>
            <input type="email" wire:model.defer="email" class="border p-2 w-full"/>
            @error('email') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <div class="mb-4">
            <label>Mot de passe</label>
            <input type="password" wire:model.defer="password" class="border p-2 w-full"/>
            @error('password') <span class="text-red-500">{{ $message }}</span> @enderror
        </div>

        <div class="mb-4">
            <label>Contacts</label>
            @foreach($contacts as $index => $contact)
                <div class="flex mb-2 space-x-2">
                    <input type="text" wire:model.defer="contacts.{{ $index }}.name" placeholder="Nom" class="border p-2 flex-1"/>
                    <input type="text" wire:model.defer="contacts.{{ $index }}.phone" placeholder="Téléphone" class="border p-2 flex-1"/>
                    <button type="button" wire:click="removeContact({{ $index }})" class="bg-red-500 text-white px-2 rounded">Supprimer</button>
                </div>
                @error('contacts.' . $index . '.name') <span class="text-red-500">{{ $message }}</span> @enderror
                @error('contacts.' . $index . '.phone') <span class="text-red-500">{{ $message }}</span> @enderror
            @endforeach
            <button type="button" wire:click="addContact" class="mt-2 bg-blue-500 text-white px-4 py-1 rounded">Ajouter un contact</button>
        </div>

        <button type="submit" class="bg-green-500 text-white px-4 py-2 rounded">Créer l'utilisateur</button>
    </form>
</div>
