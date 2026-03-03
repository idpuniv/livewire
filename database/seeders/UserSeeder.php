<?php
// database/seeders/UserSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Roles\Roles;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'email' => 'admin@example.com',
                'name' => 'Admin',
                'role' => Roles::ADMIN
            ],
            [
                'email' => 'cashier@example.com',
                'name' => 'Caissier',
                'role' => Roles::CASHIER
            ],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                ]
            );

            if (isset($userData['role']) && $userData['role']) {
                // Le rôle est identifié par son name (slug)
                $user->syncRoles([$userData['role']]);
            }
        }

        $this->command->info('Utilisateurs créés avec succès !');
    }
}