<?php

namespace App\Permissions;

final class ProductPermissions
{
    public const VIEW   = 'product.view';
    public const LIST   = 'product.list';
    public const CREATE = 'product.create';
    public const UPDATE = 'product.update';
    public const DELETE = 'product.delete';

    public const STOCK_CREATE = 'product.stock.create';
    public const STOCK_LIST = 'product.stock.list';
    public const STOCK_HISTORY = 'product.stock.history';
    public const STOCK_ADJUST  = 'product.stock.adjust';

    public const GUARD = 'web';

    public static function labels(): array
    {
        return [
            self::VIEW   => 'Voir un produit',
            self::LIST   => 'Lister les produits',
            self::CREATE => 'Créer un produit',
            self::UPDATE => 'Modifier un produit',
            self::DELETE => 'Supprimer un produit',

            self::STOCK_CREATE => 'Enregistrer un mouvement de stock (entrée/sortie)',
            self::STOCK_LIST => 'Consulter la liste des mouvements de stock',
            self::STOCK_HISTORY => 'Consulter l\'historique des mouvements',
            self::STOCK_ADJUST  => 'Effectuer un ajustement de stock',
        ];
    }

    public static function read(): array
    {
        return [
            self::VIEW,
            self::LIST,
            self::STOCK_LIST,
            self::STOCK_HISTORY,
        ];
    }

    public static function write(): array
    {
        return [
            self::CREATE,
            self::UPDATE,
            self::DELETE,
            self::STOCK_CREATE,
            self::STOCK_ADJUST,
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

    public static function productPermissions(): array
    {
        return [
            self::VIEW,
            self::LIST,
            self::CREATE,
            self::UPDATE,
            self::DELETE,
        ];
    }

    public static function stockPermissions(): array
    {
        return [
            self::STOCK_CREATE,
            self::STOCK_LIST,
            self::STOCK_HISTORY,
            self::STOCK_ADJUST,
        ];
    }
}
