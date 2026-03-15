<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    /**
     * Afficher tous les settings (depuis la config déjà chargée)
     */
    public function index()
{
    $userId = Auth::id();
    $cacheKey = "user_settings_{$userId}";
    
    // Récupère les settings du cache
    $userSettings = Cache::get($cacheKey, []);
    
    // Récupère les groupes depuis la config
    $groups = config('settings.groups', []);
    
    // Prépare les configurations des champs
    $fieldsConfig = [];
    foreach ($groups as $groupKey => $group) {
        foreach ($group['fields'] as $fieldKey) {
            $fieldsConfig[$fieldKey] = config("settings.fields.{$fieldKey}", []);
        }
    }
    
    // Crée la collection de settings avec les valeurs du cache
    $settings = collect();
    foreach ($groups as $group) {
        foreach ($group['fields'] as $fieldKey) {
            $setting = new Setting();
            $setting->key = $fieldKey;
            
            // Priorité : cache > config
            $setting->value = $userSettings[$fieldKey] ?? config("settings.{$fieldKey}");
            
            // Vérifie si c'est une valeur personnalisée
            $setting->user_id = array_key_exists($fieldKey, $userSettings) ? $userId : null;
            
            $settings->push($setting);
        }
    }
    
    return view('settings.index', compact('settings', 'groups', 'fieldsConfig'));
}

    /**
     * Mettre à jour un setting
     */
    public function update(Request $request)
    {
        $request->validate([
            'key' => 'required|string',
            'value' => 'nullable',
        ]);

        $userId = Auth::id();
        $key = $request->input('key');
        $value = $request->input('value');

        // Pour les switchs checkbox (envoie 0 si décoché)
        if ($request->has('checkbox') && $request->input('checkbox') === '0') {
            $value = '0';
        }

        // Sauvegarde en BD
        Setting::updateOrCreate(
            ['key' => $key, 'user_id' => $userId],
            ['value' => $value]
        );

        // Met à jour le cache
        $cacheKey = "user_settings_{$userId}";
        $userSettings = Cache::get($cacheKey, []);
        $userSettings[$key] = $value;
        Cache::put($cacheKey, $userSettings, 3600);
        
        // Met à jour la config pour cette requête
        config(["settings.{$key}" => $value]);

        return redirect()->back()->with('success', "Le paramètre a été mis à jour !");
    }

    /**
     * Réinitialiser un setting
     */
    public function reset($key)
    {
        $userId = Auth::id();
        
        // Supprime de la BD
        Setting::where('key', $key)
            ->where('user_id', $userId)
            ->delete();
        
        // Met à jour le cache
        $cacheKey = "user_settings_{$userId}";
        $userSettings = Cache::get($cacheKey, []);
        unset($userSettings[$key]);
        Cache::put($cacheKey, $userSettings, 3600);
        
        // Remet la valeur par défaut dans la config
        $defaultValue = config("settings.defaults.{$key}") ?? config("settings.{$key}");
        config(["settings.{$key}" => $defaultValue]);

        return redirect()->back()->with('success', "Le paramètre a été réinitialisé !");
    }
}