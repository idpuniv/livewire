<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Setting;

class SettingController extends Controller
{
    /**
     * Afficher tous les settings
     */
    public function index()
    {
        // Récupère tous les settings de la BD
        $settings = Setting::orderBy('key')->get();
        
        // Récupère les groupes depuis la config
        $groups = config('settings.groups', []);
        
        // Prépare toutes les configurations des champs pour la vue
        $fieldsConfig = [];
        foreach ($groups as $groupKey => $group) {
            foreach ($group['fields'] as $fieldKey) {
                $fieldsConfig[$fieldKey] = config("settings.fields.{$fieldKey}", []);
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

        $key = $request->input('key');
        $value = $request->input('value');

        // Pour les switchs checkbox (envoie 0 si décoché)
        if ($request->has('checkbox') && $request->input('checkbox') === '0') {
            $value = '0';
        }

        // Pour tester : user_id = null (global)
        Setting::set($key, $value, null);

        return redirect()->back()->with('success', "Le paramètre a été mis à jour !");
    }

    /**
     * Mettre à jour plusieurs settings à la fois
     */
    public function updateGroup(Request $request)
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        $settings = $request->input('settings');
        
        foreach ($settings as $key => $value) {
            Setting::set($key, $value, null);
        }

        return redirect()->back()->with('success', "Les paramètres ont été mis à jour !");
    }

    /**
     * Réinitialiser un setting (supprime la valeur personnalisée)
     */
    public function reset($key)
    {
        Setting::remove($key, null);
        
        return redirect()->back()->with('success', "Le paramètre a été réinitialisé !");
    }

    /**
     * Afficher les détails d'un setting
     */
    public function show($key)
    {
        $setting = Setting::where('key', $key)->first();
        $value = $setting ? $setting->value : config("settings.{$key}");
        $config = config("settings.fields.{$key}", []);
        
        return response()->json([
            'key' => $key,
            'value' => $value,
            'config' => $config
        ]);
    }
}