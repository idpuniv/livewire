<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class LoadSettings
{
    public function handle(Request $request, Closure $next)
    {
        $userId = Auth::check() ? Auth::id() : null;

        $cacheKey = "settings_user_" . ($userId ?? 'guest');
        
        $settings = Cache::remember($cacheKey, 60 * 60, function () use ($userId) {
            // Charger les valeurs par défaut
            $config = config('settings');
            $defaults = $this->flattenSettings($config);
            
            // Charger les settings globaux (toujours présents)
            $globals = Setting::whereNull('user_id')
                ->pluck('value', 'key')
                ->toArray();
            
            // Charger les settings user seulement si connecté
            $userSettings = $userId 
                ? Setting::where('user_id', $userId)->pluck('value', 'key')->toArray() 
                : [];
            
            // Fusion : user > global > default
            return array_merge($defaults, $globals, $userSettings);
        });
        
        // 🔥 DEBUG : Affiche les settings (connecté ou non)
        
        // Rendre accessible dans toute l'app
        app()->instance('user_settings', $settings);
        
        return $next($request);
    }
    
    protected function flattenSettings(array $settings, string $prefix = ''): array
    {
        $flat = [];
        
        foreach ($settings as $key => $value) {
            if (in_array($key, ['fields', 'groups'])) {
                continue;
            }
            
            if (is_array($value)) {
                $flat = array_merge($flat, $this->flattenSettings($value, $prefix . $key . '.'));
            } else {
                $flat[$prefix . $key] = $value;
            }
        }
        
        return $flat;
    }
}