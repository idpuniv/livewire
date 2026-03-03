<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Menus\Menus;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('menus')->truncate();

        $allMenus = [
            ...Menus::sidebar(),
            ...Menus::navbar(),
        ];

        foreach ($allMenus as $menu) {
            $parentId = DB::table('menus')->insertGetId([
                'slug' => $menu['slug'],
                'label' => $menu['label'],
                'icon' => $menu['icon'],
                'route' => $menu['route'] ?? null,
                'order' => $menu['order'],
                'permission' => $menu['permission'] ?? null,
                'type' => $menu['type'] ?? 'sidebar',
                'menu_type' => $menu['type'] ?? null,
                'is_active' => $menu['active'] ?? true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (!empty($menu['children'])) {
                foreach ($menu['children'] as $child) {
                    DB::table('menus')->insert([
                        'slug' => $child['slug'],
                        'label' => $child['label'],
                        'icon' => $child['icon'],
                        'route' => $child['route'] ?? null,
                        'order' => $child['order'],
                        'permission' => $child['permission'] ?? null,
                        'type' => $menu['type'],
                        'parent_id' => $parentId,
                        'is_active' => $child['active'] ?? true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            if (!empty($menu['items'])) {
                foreach ($menu['items'] as $item) {
                    DB::table('menus')->insert([
                        'slug' => $item['slug'],
                        'label' => $item['label'],
                        'icon' => $item['icon'],
                        'route' => $item['route'] ?? null,
                        'permission' => $item['permission'] ?? null,
                        'type' => 'navbar',
                        'menu_type' => $item['type'] ?? 'link',
                        'parent_id' => $parentId,
                        'is_active' => $item['active'] ?? true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Menus créés avec succès !');
    }
}