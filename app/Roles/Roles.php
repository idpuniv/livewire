<?php

namespace App\Roles;

use App\Permissions\OrderPermissions;
use App\Permissions\PaymentPermissions;
use App\Permissions\ProductPermissions;
use App\Permissions\SystemPermissions;

final class Roles
{
    public const ADMIN = 'admin';
    public const CASHIER = 'cashier';
    public const STOCK_MANAGER = 'stock_manager';

    public static function web(): array
    {
        return [
            self::ADMIN => [
                'label' => 'Administrateur',
                'permissions' => [
                    ...SystemPermissions::all(),
                    OrderPermissions::CREATE,
                    OrderPermissions::VIEW,
                    OrderPermissions::UPDATE,
                    OrderPermissions::DELETE,
                    PaymentPermissions::CREATE,
                    PaymentPermissions::VIEW,
                    PaymentPermissions::UPDATE,
                    PaymentPermissions::DELETE,
                    ProductPermissions::VIEW,
                    ProductPermissions::LIST,
                    ProductPermissions::CREATE,
                    ProductPermissions::UPDATE,
                    ProductPermissions::DELETE,
                    ProductPermissions::STOCK_CREATE,
                    ProductPermissions::STOCK_HISTORY,
                    ProductPermissions::STOCK_ADJUST,
                ]
            ],
            self::STOCK_MANAGER => [
                'label' => 'Gestionnaire de stock',
                'permissions' => [
                    ProductPermissions::VIEW,
                    ProductPermissions::LIST,
                    ProductPermissions::STOCK_CREATE,
                    ProductPermissions::STOCK_ADJUST,
                    ProductPermissions::STOCK_HISTORY,
                ]
            ],

            self::CASHIER => [
                'label' => 'Caissier',
                'permissions' => [
                    OrderPermissions::CREATE,
                    OrderPermissions::VIEW,
                    OrderPermissions::LIST,
                    PaymentPermissions::VIEW,
                ]
            ],
        ];
    }

    public static function admin(): array
    {
        return [
            self::ADMIN => [
                'label' => 'Super Admin',
                'permissions' => [
                    ...OrderPermissions::all(),
                    ...PaymentPermissions::all(),
                    ...ProductPermissions::all(),
                    // ...UserPermissions::all(),
                    // ...SystemPermissions::all(),
                ]
            ],
        ];
    }

    public static function guards(): array
    {
        return ['web'];
    }

    public static function of(string $guard): array
    {
        return match ($guard) {
            'web' => self::web(),
            default => [],
        };
    }

    public static function permissions(string $guard, string $role): array
    {
        return self::of($guard)[$role]['permissions'] ?? [];
    }

    public static function label(string $guard, string $role): string
    {
        return self::of($guard)[$role]['label'] ?? $role;
    }

    public static function has(string $guard, string $role): bool
    {
        return isset(self::of($guard)[$role]);
    }

    public static function all(): array
    {
        return [
            self::ADMIN,
            self::CASHIER,
        ];
    }

    public static function guard(): string
    {
        return 'web';
    }
}
