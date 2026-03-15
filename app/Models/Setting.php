<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'user_id'];
    
    protected $casts = [
        'value' => 'json',
    ];
    
    // Cache pour les métadonnées
    protected static $metadataCache = [];

    /**
     * Récupère un paramètre avec support de la notation pointée
     */
    public static function get($key, $default = null, $userId = null)
    {
        $userId = $userId ?? (Auth::check() ? Auth::id() : null);
        
        // Support de la notation pointée (ex: notifications.email)
        if (strpos($key, '.') !== false) {
            return static::getNested($key, $default, $userId);
        }

        $cacheKey = static::getCacheKey($key, $userId);
        
        return Cache::rememberForever($cacheKey, function () use ($key, $default, $userId) {
            $setting = self::where('key', $key)
                ->where(function($q) use ($userId) {
                    $q->whereNull('user_id')
                      ->orWhere('user_id', $userId);
                })
                ->orderByRaw('user_id IS NOT NULL DESC')
                ->first();

            if ($setting && $setting->value !== null) {
                return $setting->value;
            }

            // Fallback sur le fichier de config
            $configValue = config("settings.{$key}");
            
            if (is_array($configValue)) {
                return $configValue;
            }
            
            return $configValue ?? $default;
        });
    }

    /**
     * Récupère un paramètre imbriqué (notation pointée)
     */
    protected static function getNested($key, $default = null, $userId = null)
    {
        $parts = explode('.', $key, 2);
        $baseKey = $parts[0];
        $nestedKey = $parts[1];
        
        $baseValue = static::get($baseKey, [], $userId);
        
        if (is_array($baseValue)) {
            return Arr::get($baseValue, $nestedKey, $default);
        }
        
        return $default;
    }

    /**
     * Récupère tout un groupe de paramètres
     */
    public static function getGroup($group, $userId = null)
    {
        $userId = $userId ?? (Auth::check() ? Auth::id() : null);
        $cacheKey = "settings_group_{$group}_user_{$userId}";
        
        return Cache::rememberForever($cacheKey, function () use ($group, $userId) {
            // Récupère les valeurs de la BD
            $dbSettings = self::where('key', 'LIKE', "{$group}.%")
                ->where(function($q) use ($userId) {
                    $q->whereNull('user_id')
                      ->orWhere('user_id', $userId);
                })
                ->orderByRaw('user_id IS NOT NULL DESC')
                ->get()
                ->mapWithKeys(function ($item) use ($group) {
                    $key = str_replace("{$group}.", '', $item->key);
                    return [$key => $item->value];
                })
                ->toArray();
            
            // Valeurs par défaut
            $defaultSettings = config("settings.{$group}", []);
            
            return array_merge($defaultSettings, $dbSettings);
        });
    }

    /**
     * Définit un paramètre
     */
    public static function set($key, $value, $userId = null)
    {
        $userId = $userId ?? (Auth::check() ? Auth::id() : null);
        
        $setting = self::updateOrCreate(
            ['key' => $key, 'user_id' => $userId],
            ['value' => $value]
        );

        static::clearCache($key, $userId);
        
        if (strpos($key, '.') !== false) {
            $group = explode('.', $key)[0];
            static::clearGroupCache($group, $userId);
        }

        return $setting;
    }

    /**
     * Définit plusieurs paramètres à la fois
     */
    public static function setMany(array $settings, $userId = null)
    {
        $userId = $userId ?? (Auth::check() ? Auth::id() : null);
        $updated = [];
        
        foreach ($settings as $key => $value) {
            $updated[] = static::set($key, $value, $userId);
        }
        
        return $updated;
    }

    /**
     * Supprime un paramètre (renommé pour éviter conflit avec Eloquent)
     */
    public static function remove($key, $userId = null)  // RENOMMÉ DE delete() À remove()
    {
        $userId = $userId ?? (Auth::check() ? Auth::id() : null);
        
        $deleted = self::where('key', $key)
            ->where('user_id', $userId)
            ->delete();
        
        if ($deleted) {
            static::clearCache($key, $userId);
            
            if (strpos($key, '.') !== false) {
                $group = explode('.', $key)[0];
                static::clearGroupCache($group, $userId);
            }
        }
        
        return $deleted;
    }

    /**
     * Génère une clé de cache
     */
    protected static function getCacheKey($key, $userId = null)
    {
        return "setting_{$key}_" . ($userId ?? 'global');
    }

    /**
     * Nettoie le cache d'un paramètre
     */
    protected static function clearCache($key, $userId = null)
    {
        Cache::forget(static::getCacheKey($key, $userId));
        Cache::forget(static::getCacheKey($key, 'global'));
    }

    /**
     * Nettoie le cache d'un groupe
     */
    protected static function clearGroupCache($group, $userId = null)
    {
        $cacheKey = "settings_group_{$group}_" . ($userId ?? 'global');
        Cache::forget($cacheKey);
    }

    /**
     * Récupère la configuration d'un champ depuis le fichier settings.php
     */
    public static function getFieldConfig($key)
    {
        return config("settings.fields.{$key}", []);
    }

    /**
     * Récupère tous les groupes avec leurs champs configurés
     */
    public static function getGroups()
    {
        return config('settings.groups', []);
    }

    /**
     * Récupère le type d'un champ
     */
    public static function getFieldType($key)
    {
        return config("settings.fields.{$key}.type", 'text');
    }

    /**
     * Récupère les options d'un champ select
     */
    public static function getFieldOptions($key)
    {
        return config("settings.fields.{$key}.options", []);
    }

    /**
     * Vérifie si un paramètre existe
     */
    public static function has($key, $userId = null)
    {
        return static::get($key, null, $userId) !== null;
    }

    /**
     * Récupère tous les paramètres
     */
    public static function getAll($userId = null, $perPage = null)
    {
        $query = self::where('user_id', $userId)
            ->orWhereNull('user_id')
            ->orderBy('key');
            
        if ($perPage) {
            return $query->paginate($perPage);
        }
        
        return $query->get();
    }
}