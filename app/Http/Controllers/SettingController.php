<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class SettingController extends Controller
{
    /**
     * Affiche les paramètres en utilisant le cache.
     */
    public function index()
    {
        $groups = config('settings.groups', []);
        $fieldsConfig = config('settings.fields', []);

        $userId = Auth::id();
        // On récupère les settings du user depuis le cache (préparé par le provider)
        $settings = Cache::get('settings_user_' . $userId, []);

        return view('settings.index', compact('groups', 'fieldsConfig', 'settings'));
    }

    /**
     * Met à jour un paramètre pour le user courant.
     */
    public function update(Request $request)
    {
        $key = $request->input('key');
        $value = $request->input('value');
        $userId = Auth::id();

        $isAdmin = true; // À remplacer par la vraie vérif admin

        // Protection : seuls les admins peuvent modifier les settings system
        if (str_starts_with($key, 'system') && !$isAdmin) {
            abort(403, 'Paramètre système interdit.');
        }

        // Crée ou met à jour le paramètre pour l'utilisateur courant
        Setting::updateOrCreate(
            [
                'key' => $key,
                'user_id' => $userId
            ],
            [
                'value' => $value,
                'is_system' => str_starts_with($key, 'system')
            ]
        );

        // On vide le cache pour forcer le provider à régénérer les settings
        Cache::forget("settings_user_$userId");
        Cache::forget("settings_system");

        return back()->with('success', 'Paramètre mis à jour.');
    }
}