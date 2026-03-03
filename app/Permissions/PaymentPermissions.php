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
            self::VIEW   => 'Voir Payment',
            self::LIST   => 'Lister les Payments',
            self::CREATE => 'Créer Payment',
            self::UPDATE => 'Modifier Payment',
            self::DELETE => 'Supprimer Payment',
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