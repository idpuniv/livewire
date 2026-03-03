<?php

namespace App\Roles;

use App\Permissions\OrderPermissions;
use App\Permissions\PaymentPermissions;
use App\Permissions\ProductPermissions;
use App\Permissions\UserPermissions;
use App\Permissions\SystemPermissions;
use App\Permissions\ReportPermissions;

final class Roles
{
    public const ADMIN = 'admin';
    public const SUPER_ADMIN = 'super_admin';
    public const RECORDER = 'recorder';
    public const CASHIER = 'cashier';
    public const SENIOR_CASHIER = 'senior_cashier';
    public const FLOOR_MANAGER = 'floor_manager';
    public const STORE_MANAGER = 'store_manager';

    public const GUARD = 'web';

    public static function labels(): array
    {
        return [
            self::ADMIN => 'Administrateur',
            self::SUPER_ADMIN => 'Super Administrateur',
            self::RECORDER => 'Caissier Enregistreur',
            self::CASHIER => 'Caissier',
            self::SENIOR_CASHIER => 'Caissier Senior',
            self::FLOOR_MANAGER => 'Manager de surface',
            self::STORE_MANAGER => 'Manager de magasin',
        ];
    }

    public static function getPermissions(string $role): array
    {
        return match($role) {
            // === ADMIN ===
            self::ADMIN => [
                OrderPermissions::CREATE,
                OrderPermissions::VIEW,
                OrderPermissions::UPDATE,
                OrderPermissions::DELETE,
                PaymentPermissions::PROCESS,
                PaymentPermissions::VIEW,
                PaymentPermissions::REFUND,
                UserPermissions::CREATE,
                UserPermissions::VIEW,
                UserPermissions::UPDATE,
                UserPermissions::DELETE,
                UserPermissions::ASSIGN_ROLES,
                SystemPermissions::VIEW_LOGS,
                SystemPermissions::MANAGE_SETTINGS,
            ],
            
            // === CAISSIERS ===
            self::RECORDER => [
                // Permissions individuelles
                OrderPermissions::CREATE,
                OrderPermissions::VIEW,
                OrderPermissions::LIST,
                ProductPermissions::VIEW,
                // Pas de PaymentPermissions
            ],
            
            self::CASHIER => [
                OrderPermissions::CREATE,
                OrderPermissions::VIEW,
                OrderPermissions::LIST,
                PaymentPermissions::PROCESS,  // ← Permission spécifique
                PaymentPermissions::VIEW,
                ProductPermissions::VIEW,
            ],
            
            self::SENIOR_CASHIER => [
                OrderPermissions::CREATE,
                OrderPermissions::VIEW,
                OrderPermissions::LIST,
                OrderPermissions::UPDATE,
                PaymentPermissions::PROCESS,
                PaymentPermissions::VIEW,
                PaymentPermissions::REFUND,    // ← Permission spécifique
                ProductPermissions::VIEW,
            ],
            
            default => []
        };
    }

    public static function all(): array
    {
        return array_keys(self::labels());
    }

    public static function guard(): string
    {
        return self::GUARD;
    }
}