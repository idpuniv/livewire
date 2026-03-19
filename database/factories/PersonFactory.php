<?php

namespace Database\Factories;

use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        // Liste de prénoms et noms ivoiriens pour plus de réalisme
        $firstnames = [
            'Kouassi', 'Konan', 'Koffi', 'Ahou', 'Aya', 'Adjoua', 
            'Amoin', 'Affoué', 'Amenan', 'N’Dri', 'N’Guessan', 'N’Da',
            'Kanga', 'Kouadio', 'Kra', 'Kouakou', 'Koffi', 'Brou',
            'Zokou', 'Doh', 'Loba', 'Gohi', 'Vanié', 'Tano'
        ];
        
        $names = [
            'Konan', 'Kouassi', 'Bamba', 'Traoré', 'Touré', 'Cissé',
            'Diallo', 'Koné', 'Coulibaly', 'Soro', 'Ouattara', 'Kanté',
            'Sangaré', 'Diomandé', 'Karamoko', 'Yeo', 'Diarra', 'Kamagaté',
            'Doumbia', 'Tuo', 'Pale', 'Gnakabi', 'Loboué', 'N’Zi'
        ];

        // Téléphones ivoiriens
        $prefixes = ['01', '02', '03', '04', '05', '06', '07', '08', '09'];
        $phone = $this->faker->randomElement($prefixes) . $this->faker->numberBetween(00000000, 99999999);

        return [
            'name' => $this->faker->randomElement($names),
            'firstname' => $this->faker->randomElement($firstnames),
            'phone' => $phone,
            'email' => $this->faker->unique()->safeEmail(),
            'cnib' => $this->faker->optional(0.7)->regexify('[A-Z0-9]{12}'), // 70% ont un CNIB
        ];
    }

    /**
     * Indiquer que la personne est un client.
     */
    public function customer(): static
    {
        return $this->afterCreating(function (Person $person) {
            $person->customer()->create([
                'customer_number' => 'CUST-' . str_pad($person->id, 5, '0', STR_PAD_LEFT),
                'credit_limit' => $this->faker->optional(0.5)->numberBetween(100000, 5000000),
                'payment_terms' => $this->faker->randomElement(['comptant', '15 jours', '30 jours', '45 jours']),
            ]);
        });
    }

    /**
     * Indiquer que la personne est un fournisseur.
     */
    public function supplier(): static
    {
        return $this->afterCreating(function (Person $person) {
            $person->supplier()->create([
                'supplier_number' => 'SUPP-' . str_pad($person->id, 5, '0', STR_PAD_LEFT),
                'company_name' => $this->faker->company(),
                'tax_number' => $this->faker->optional(0.8)->regexify('[A-Z0-9]{10}'),
            ]);
        });
    }

    /**
     * Indiquer que la personne n'a pas de CNIB.
     */
    public function withoutCnib(): static
    {
        return $this->state(fn (array $attributes) => [
            'cnib' => null,
        ]);
    }

    /**
     * Indiquer que la personne n'a pas d'email.
     */
    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
        ]);
    }
}