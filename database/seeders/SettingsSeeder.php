<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Récupère toutes les clés du fichier de config SAUF fields et groups
        $config = collect(config('settings'))
            ->except(['fields', 'groups'])
            ->toArray();

        foreach ($config as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key, 'user_id' => null],
                ['value' => $value]
            );
        }

        $this->command->info('Settings seeded successfully.');
    }
}