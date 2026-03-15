<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // Récupère toutes les clés du fichier de config sauf fields et groups
        $config = collect(config('settings'))
            ->except(['fields', 'groups'])
            ->toArray();

        $flatSettings = $this->flattenSettings($config);

        foreach ($flatSettings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key, 'user_id' => null],
                ['value' => $value]
            );
        }

        $this->command->info('Settings seeded successfully.');
    }

    /**
     * Aplatit le tableau multi-niveaux en tableau clé => valeur
     */
    protected function flattenSettings(array $settings, string $prefix = ''): array
    {
        $flat = [];
        foreach ($settings as $key => $value) {
            if (is_array($value) && !isset($value['type'])) {
                // tableau imbriqué (ex: system.security)
                $flat = array_merge($flat, $this->flattenSettings($value, $prefix . $key . '.'));
            } else {
                // valeur simple ou champ config
                $flat[$prefix . $key] = is_array($value) && isset($value['default']) ? $value['default'] : $value;
            }
        }
        return $flat;
    }
}