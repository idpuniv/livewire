<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Charger le fichier config/settings.php
        $configSettings = config('settings', []);

        foreach ($configSettings as $key => $value) {
            // Créer ou mettre à jour le setting global (user_id = null)
            Setting::updateOrCreate(
                ['key' => $key, 'user_id' => null],
                ['value' => is_array($value) ? json_encode($value) : $value]
            );
        }

        $this->command->info('Settings from config/settings.php seeded successfully.');
    }
}
