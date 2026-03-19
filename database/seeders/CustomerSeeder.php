<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Person;
use App\Models\Customer;
use Illuminate\Support\Str;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        // Créer 50 personnes avec la factory
        $people = Person::factory(5)->create();

        // Pour chaque personne, créer un client
        foreach ($people as $person) {
            Customer::create([
                'person_id' => $person->id,
                'customer_number' => 'CUST-' . Str::padLeft($person->id, 5, '0'),
                'credit_limit' => rand(0, 1) ? rand(100000, 5000000) : null,
                'payment_terms' => ['comptant', '15 jours', '30 jours', '45 jours'][array_rand(['comptant', '15 jours', '30 jours', '45 jours'])],
            ]);
        }
    }
}