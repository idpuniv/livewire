<?php

namespace App\Permissions;

final class SystemPermissions
{
    // Paramètres système
    public const VIEW_SETTINGS   = 'system.settings.view';
    public const EDIT_SETTINGS    = 'system.settings.edit';
    
    // Maintenance
    public const VIEW_MAINTENANCE = 'system.maintenance.view';
    public const TOGGLE_MAINTENANCE = 'system.maintenance.toggle';

    // Logs et monitoring
    public const VIEW_LOGS        = 'system.logs.view';
    public const CLEAR_LOGS       = 'system.logs.clear';
    // Cache
    public const VIEW_CACHE       = 'system.cache.view';
    public const CLEAR_CACHE      = 'system.cache.clear';
    
    // Sécurité
    public const VIEW_SECURITY    = 'system.security.view';
    public const EDIT_SECURITY    = 'system.security.edit';
    
    public const GUARD = 'web';

    public static function labels(): array
    {
        return [
            self::VIEW_SETTINGS     => 'Voir les paramètres système',
            self::EDIT_SETTINGS     => 'Modifier les paramètres système',
            self::VIEW_MAINTENANCE  => 'Voir le mode maintenance',
            self::TOGGLE_MAINTENANCE => 'Activer/désactiver la maintenance',
            self::VIEW_LOGS         => 'Voir les logs',
            self::CLEAR_LOGS        => 'Effacer les logs',
            self::VIEW_CACHE        => 'Voir le cache',
            self::CLEAR_CACHE       => 'Vider le cache',
            self::VIEW_SECURITY     => 'Voir la sécurité',
            self::EDIT_SECURITY     => 'Modifier la sécurité',
        ];
    }

    public static function read(): array
    {
        return [
            self::VIEW_SETTINGS,
            self::VIEW_MAINTENANCE,
            self::VIEW_LOGS,
            self::VIEW_CACHE,
            self::VIEW_SECURITY,
        ];
    }

    public static function write(): array
    {
        return [
            self::EDIT_SETTINGS,
            self::TOGGLE_MAINTENANCE,
            self::CLEAR_LOGS,
            self::CLEAR_CACHE,
            self::EDIT_SECURITY,
        ];
    }

    public static function guard(): string
    {
        return self::GUARD;
    }

    public static function all(): array
    {
        return array_keys(self::labels());
    }
}