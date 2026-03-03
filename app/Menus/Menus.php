<?php

namespace App\Menus;

use App\Permissions\OrderPermissions;
use App\Permissions\PaymentPermissions;
use App\Permissions\ProductPermissions;
use App\Permissions\UserPermissions;
use App\Permissions\ReportPermissions;
use App\Permissions\SystemPermissions;

final class Menus
{
    // ===== SIDEBAR =====
    public static function sidebar(): array
    {
        $user = auth()->user();
        if (!$user) return [];

        $menus = [
            [
                'slug' => 'dashboard',
                'label' => 'Tableau de bord',
                'icon' => 'fas fa-home',
                'route' => 'dashboard',
                'order' => 1,
                'permission' => null,
                'active' => true, // Activé par défaut
                'children' => []
            ],
            [
                'slug' => 'pos',
                'label' => 'Caisse',
                'icon' => 'fas fa-cash-register',
                'route' => 'pos',
                'order' => 2,
                'permission' => OrderPermissions::CREATE,
                'active' => true,
                'children' => []
            ],
            [
                'slug' => 'orders',
                'label' => 'Commandes',
                'icon' => 'fas fa-shopping-cart',
                'route' => null,
                'order' => 3,
                'permission' => OrderPermissions::VIEW,
                'active' => true,
                'children' => [
                    [
                        'slug' => 'orders.list',
                        'label' => 'Liste des commandes',
                        'icon' => 'fas fa-list',
                        'route' => 'orders.index',
                        'order' => 1,
                        'permission' => OrderPermissions::LIST,
                        'active' => true,
                    ],
                    [
                        'slug' => 'orders.create',
                        'label' => 'Nouvelle commande',
                        'icon' => 'fas fa-plus',
                        'route' => 'orders.create',
                        'order' => 2,
                        'permission' => OrderPermissions::CREATE,
                        'active' => true,
                    ],
                ]
            ],
            [
                'slug' => 'payments',
                'label' => 'Paiements',
                'icon' => 'fas fa-credit-card',
                'route' => null,
                'order' => 4,
                'permission' => PaymentPermissions::VIEW,
                'active' => true,
                'children' => [
                    [
                        'slug' => 'payments.today',
                        'label' => 'Paiements du jour',
                        'icon' => 'fas fa-calendar-day',
                        'route' => 'payments.today',
                        'order' => 1,
                        'permission' => PaymentPermissions::VIEW,
                        'active' => true,
                    ],
                    [
                        'slug' => 'payments.history',
                        'label' => 'Historique',
                        'icon' => 'fas fa-history',
                        'route' => 'payments.history',
                        'order' => 2,
                        'permission' => PaymentPermissions::VIEW,
                        'active' => true,
                    ],
                ]
            ],
            // Menu désactivé (test)
            [
                'slug' => 'old_reports',
                'label' => 'Anciens rapports',
                'icon' => 'fas fa-chart-line',
                'route' => 'reports.old',
                'order' => 5,
                'permission' => ReportPermissions::VIEW,
                'active' => false, // Désactivé
                'children' => []
            ],
        ];

        return self::filter($menus, $user);
    }

    // ===== NAVBAR =====
    public static function navbar(): array
    {
        $user = auth()->user();
        if (!$user) return [];

        $menus = [
            [
                'slug' => 'search',
                'label' => 'Rechercher',
                'icon' => 'fas fa-search',
                'type' => 'search',
                'order' => 1,
                'permission' => null,
                'active' => true,
            ],
            [
                'slug' => 'quick_actions',
                'label' => 'Actions rapides',
                'icon' => 'fas fa-plus-circle',
                'type' => 'dropdown',
                'order' => 2,
                'permission' => OrderPermissions::CREATE,
                'active' => true,
                'items' => [
                    [
                        'slug' => 'quick_new_order',
                        'label' => 'Nouvelle commande',
                        'icon' => 'fas fa-shopping-cart',
                        'route' => 'orders.create',
                        'permission' => OrderPermissions::CREATE,
                        'active' => true,
                    ],
                    [
                        'slug' => 'quick_new_product',
                        'label' => 'Nouveau produit',
                        'icon' => 'fas fa-box',
                        'route' => 'products.create',
                        'permission' => ProductPermissions::CREATE,
                        'active' => false, // Désactivé en attendant
                    ],
                ]
            ],
            [
                'slug' => 'notifications',
                'label' => 'Notifications',
                'icon' => 'fas fa-bell',
                'type' => 'dropdown',
                'order' => 3,
                'permission' => null,
                'active' => true,
                'items' => [
                    [
                        'slug' => 'notification.view_all',
                        'label' => 'Voir toutes',
                        'icon' => 'fas fa-list',
                        'route' => 'notifications.index',
                        'active' => true,
                    ],
                ]
            ],
            [
                'slug' => 'user',
                'label' => auth()->user()->name,
                'icon' => 'fas fa-user-circle',
                'type' => 'dropdown',
                'order' => 4,
                'permission' => null,
                'active' => true,
                'items' => [
                    [
                        'slug' => 'profile',
                        'label' => 'Profil',
                        'icon' => 'fas fa-id-card',
                        'route' => 'profile.edit',
                        'active' => true,
                    ],
                    [
                        'slug' => 'logout',
                        'label' => 'Déconnexion',
                        'icon' => 'fas fa-sign-out-alt',
                        'route' => 'logout',
                        'type' => 'logout',
                        'active' => true,
                    ],
                ]
            ],
            [
                'slug' => 'experimental',
                'label' => 'Fonctionnalité test',
                'icon' => 'fas fa-flask',
                'type' => 'link',
                'route' => 'experimental',
                'order' => 5,
                'permission' => null,
                'active' => false, // Désactivé
            ],
        ];

        return self::filter($menus, $user);
    }

    // ===== FILTRE =====
    private static function filter(array $menus, $user): array
    {
        $filtered = [];

        foreach ($menus as $menu) {
            // Vérifie si le menu est actif
            if (isset($menu['active']) && $menu['active'] === false) {
                continue;
            }

            // Vérifie la permission
            if (isset($menu['permission']) && $menu['permission'] && !$user->can($menu['permission'])) {
                continue;
            }

            // Filtre les enfants
            if (!empty($menu['children'])) {
                $menu['children'] = self::filter($menu['children'], $user);
                if (empty($menu['children']) && !isset($menu['route'])) {
                    continue; // Ne garde pas un parent vide
                }
            }

            // Filtre les items (dropdown)
            if (!empty($menu['items'])) {
                $menu['items'] = self::filter($menu['items'], $user);
                if (empty($menu['items'])) {
                    continue; // Ne garde pas un dropdown vide
                }
            }

            $filtered[] = $menu;
        }

        return $filtered;
    }

    // ===== MÉTHODES DE CONTRÔLE =====
    
    /**
     * Active ou désactive un menu
     */
    public static function setActive(string $menuSlug, bool $active): void
    {
        cache()->forever("menu.{$menuSlug}.active", $active);
    }

    /**
     * Vérifie si un menu est actif (avec cache)
     */
    private static function isActive(array $menu): bool
    {
        // Priorité à la valeur en cache
        $cached = cache()->get("menu.{$menu['slug']}.active");
        if ($cached !== null) {
            return $cached;
        }
        
        // Sinon retourne la valeur par défaut
        return $menu['active'] ?? true;
    }

    /**
     * Désactive un menu (raccourci)
     */
    public static function disable(string $menuSlug): void
    {
        self::setActive($menuSlug, false);
    }

    /**
     * Active un menu (raccourci)
     */
    public static function enable(string $menuSlug): void
    {
        self::setActive($menuSlug, true);
    }

    /**
     * Réinitialise un menu à sa valeur par défaut
     */
    public static function reset(string $menuSlug): void
    {
        cache()->forget("menu.{$menuSlug}.active");
    }
}