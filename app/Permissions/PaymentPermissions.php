<?php

namespace App\Permissions;

final class PaymentPermissions
{
    public const VIEW   = 'payment.view';
    public const LIST   = 'payment.list';
    public const CREATE = 'payment.create';
    public const UPDATE = 'payment.update';
    public const DELETE = 'payment.delete';

    public const GUARD = 'web';

    public static function labels(): array
    {
        return [
            self::VIEW   => 'Voir un payment',
            self::LIST   => 'Lister les payments',
            self::CREATE => 'Créer un payment',
            self::UPDATE => 'Modifier un payment',
            self::DELETE => 'Supprimer un payment',
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
