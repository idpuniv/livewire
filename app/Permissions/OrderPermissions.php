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
            self::VIEW   => 'Voir un order',
            self::LIST   => 'Lister les orders',
            self::CREATE => 'Créer un order',
            self::UPDATE => 'Modifier un order',
            self::DELETE => 'Supprimer un order',
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
