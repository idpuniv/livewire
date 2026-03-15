<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SettingsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Charge les settings après que l'application soit prête
        $this->app->booted(function () {
            if (!app()->runningInConsole()) {
                $this->loadSettings();
            }
        });
    }

    protected function loadSettings(): void
    {
        if (Auth::check()) {
            $userId = Auth::id();
            $cacheKey = "user_settings_{$userId}";
            
            // Récupère depuis le cache ou charge depuis la BD
            $userSettings = Cache::remember($cacheKey, 3600, function () use ($userId) {
                return $this->getUserSettings($userId);
            });
            
            // Fusionne les settings dans la config
            foreach ($userSettings as $key => $value) {
                config(["settings.{$key}" => $value]);
            }
        }
    }

    protected function getUserSettings($userId): array
    {
        // Récupère tous les paramètres par défaut du fichier config
        $config = config('settings');
        $defaults = collect($config)
            ->except(['fields', 'groups', 'metadata'])
            ->toArray();
        
        // Récupère les paramètres utilisateur de la BD
        $dbSettings = Setting::where('user_id', $userId)
            ->get()
            ->pluck('value', 'key')
            ->toArray();
        
        // Fusionne avec priorité à la BD
        return array_merge($defaults, $dbSettings);
    }
}                                                                   