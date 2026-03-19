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
                'email' => 'user1@email.com',
                'name' => 'user1',
                'password' => 'user1',
                'role' => Roles::ADMIN,
            ],
             [
                'email' => 'user2@email.com',
                'name' => 'user2',
                'password' => 'user2',
            ],
            [
                'email' => 'admin@example.com',
                'name' => 'Admin',
                'password' => 'admin',
                'role' => Roles::ADMIN
            ],
            [
                'email' => 'cashier@example.com',
                'name' => 'Caissier',
                'password' => 'cashier',
                'role' => Roles::CASHIER
            ],
            [
                'email' => 'manager@example.com',
                'name' => 'Gestionnaire de stock',
                'password' => 'manager',
                'role' => Roles::STOCK_MANAGER
            ],
        ];

        foreach ($users as $userData) {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password'] ?? 'password'),
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