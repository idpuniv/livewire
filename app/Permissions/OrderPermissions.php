<?php

namespace App\Permissions;

final class OrderPermissions
{
    public const VIEW   = 'order.view';
    public const LIST   = 'order.list';

    public const CREATE = 'order.create';
    public const UPDATE = 'order.update';
    public const DELETE = 'order.delete';

    public const GUARD = 'web'; 

    public static function labels(): array
    {
        return [
            self::VIEW   => 'Voir Order',
            self::LIST   => 'Lister les Orders',
            self::CREATE => 'Créer Order',
            self::UPDATE => 'Modifier Order',
            self::DELETE => 'Supprimer Order',
        ];
    }

    public static function read(): array
    {
        return [
            self::VIEW,
            self::LIST,
        ];
    }

    public static function write(): array
    {
        return [
            self::CREATE,
            self::UPDATE,
            self::DELETE,
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