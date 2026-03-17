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
        $allGroups = config('settings.groups', []);
        $fieldsConfig = config('settings.fields', []);

        $user = Auth::user();
        $isAdmin = $user->is_admin; // Admin = false

        // Filtrer les groupes : cacher le groupe 'system' si pas admin
        $groups = array_filter($allGroups, function($groupKey) use ($isAdmin) {
            if ($groupKey === 'system' && !$isAdmin) {
                return false;
            }
            return true;
        }, ARRAY_FILTER_USE_KEY);

        // Récupère les settings du user depuis le cache
        $settings = Cache::get('settings_user_' . $user->id, []);

        return view('settings.index', compact('groups', 'fieldsConfig', 'settings'));
    }

    /**
     * Met à jour un paramètre pour le user courant.
     */
    public function update(Request $request)
    {
        $key = $request->input('key');
        $value = $request->input('value');
        $user = Auth::user();

        $isAdmin = $user->is_admin;

        if (str_starts_with($key, 'system') && !$isAdmin) {
            abort(403, 'Paramètre système interdit.');
        }

        Setting::updateOrCreate(
            [
                'key' => $key,
                'user_id' => $user->id
            ],
            [
                'value' => $value,
                'is_system' => str_starts_with($key, 'system')
            ]
        );

        Cache::forget("settings_user_$user->id");

        return back()->with('success', 'Paramètre mis à jour.');
    }
}