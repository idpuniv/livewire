<?php

use Livewire\Component;

new class extends Component
{
    public int $index;
    public string $code;
    public string $phone;
    
    public array $countryCodes = [
        '+226' => 'Burkina Faso',
        '+33' => 'France',
        '+1' => 'USA',
        '+237' => 'Cameroun',
        '+225' => 'Côte d\'Ivoire',
        '+221' => 'Sénégal',
        '+223' => 'Mali',
        '+227' => 'Niger',
        '+228' => 'Togo',
        '+229' => 'Bénin',
    ];

    public function mount($index, $code, $phone)
    {
        $this->index = $index;
        $this->code = $code;
        $this->phone = $phone;
    }

    public function setCode($code)
    {
        $this->dispatch('updateContactCode', $this->index, $code);
    }

    public function updatedPhone($value)
    {
        $this->dispatch('updateContactPhone', $this->index, $value);
    }
};
?>

<div class="d-flex align-items-center gap-2" style="min-width: 350px;">
    <!-- Dropdown Bootstrap pour le code pays -->
    <div class="dropdown">
        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" style="min-width: 100px;">
            {{ $code }}
        </button>
        <ul class="dropdown-menu" style="max-height: 300px; overflow-y: auto;">
            @foreach($countryCodes as $c => $country)
                <li>
                    <a href="#" class="dropdown-item" wire:click.prevent="setCode('{{ $c }}')">
                        {{ $country }} ({{ $c }})
                    </a>
                </li>
            @endforeach
        </ul>
    </div>

    <!-- Input numéro -->
    <input type="text" 
           wire:model.live="phone" 
           placeholder="Numéro de téléphone" 
           class="form-control"/>
</div>