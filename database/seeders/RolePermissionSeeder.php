<?php
// database/seeders/RolePermissionSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Roles\Roles;
use App\Permissions\OrderPermissions;
use App\Permissions\PaymentPermissions;
use App\Permissions\ProductPermissions;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Vide les tables
        DB::table('role_has_permissions')->delete();
        DB::table('permissions')->delete();
        DB::table('roles')->delete();

        // Classes de permissions
        $permissionClasses = [
            OrderPermissions::class,
            PaymentPermissions::class,
        ];

        // Indexer les labels par name (slug)
        $labels = [];
        foreach ($permissionClasses as $class) {
            foreach ($class::all() as $name) {
                $labels[$name] = $class::labels()[$name];
            }
        }

        // Création des rôles et permissions par guard
        foreach (Roles::guards() as $guard) {
            foreach (Roles::of($guard) as $roleSlug => $roleData) {
                // 1. Crée le rôle (name = slug, label dans une autre colonne?)
                $roleId = DB::table('roles')->insertGetId([
                    'name' => $roleSlug,              // 'admin', 'cashier', etc.
                    'label' => $roleData['label'],     // 'Administrateur', 'Caissier'
                    'guard_name' => $guard,
                ]);

                $permissionIds = [];

                // 2. Crée chaque permission
                foreach ($roleData['permissions'] as $permissionName) {
                    DB::table('permissions')->updateOrInsert(
                        [
                            'name' => $permissionName,              // 'order.create'
                            'guard_name' => $guard,
                        ],
                        [
                            'label' => $labels[$permissionName] ?? $permissionName,
                        ]
                    );

                    $permission = DB::table('permissions')
                        ->where('name', $permissionName)
                        ->where('guard_name', $guard)
                        ->first();

                    if ($permission) {
                        $permissionIds[] = $permission->id;
                    }
                }

                // 3. Associe
                foreach ($permissionIds as $permId) {
                    DB::table('role_has_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permId,
                    ]);
                }
            }
        }

        $this->command->info('Rôles et permissions créés avec succès !');
    }
}