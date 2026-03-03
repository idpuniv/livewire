<?php

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
        $allPermissions = [
            ...OrderPermissions::all(),
            ...PaymentPermissions::all(),
        ];

        foreach ($allPermissions as $permission) {
            DB::table('permissions')->updateOrInsert([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        foreach (Roles::all() as $roleSlug) {
            DB::table('roles')->updateOrInsert(
                [
                    'slug' => $roleSlug,
                    'guard_name' => Roles::guard(),
                ],
                [
                    'name' => Roles::labels()[$roleSlug],
                ]
            );
        }

        foreach (Roles::all() as $roleSlug) {
            $role = DB::table('roles')->where('slug', $roleSlug)->first();
            $permissions = Roles::getPermissions($roleSlug);
            
            $permissionIds = DB::table('permissions')
                ->whereIn('name', $permissions)
                ->pluck('id');

            DB::table('role_has_permissions')
                ->where('role_id', $role->id)
                ->delete();

            foreach ($permissionIds as $permId) {
                DB::table('role_has_permissions')->insert([
                    'role_id' => $role->id,
                    'permission_id' => $permId,
                ]);
            }
        }

        $this->command->info('Rôles et permissions créés avec succès !');
    }
}