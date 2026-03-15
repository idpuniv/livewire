```php
<?php

return [

/*
|--------------------------------------------------------------------------
| Paramètres système (admin uniquement)
|--------------------------------------------------------------------------
*/

'system' => [

    'maintenance_mode' => false,

    'maintenance_message' => 'Site en maintenance',

    'security' => [
        'password_min_length' => 8,
        'password_require_special' => true,
        'max_login_attempts' => 5,
    ],

    'registration_enabled' => true,

    'session_lifetime' => 120,

],

/*
|--------------------------------------------------------------------------
| Paramètres utilisateur (préférences utilisateur)
|--------------------------------------------------------------------------
*/

'user' => [

    'theme' => 'light',

    'notifications' => [
        'email' => true,
        'sms' => false,
        'push' => true,
    ],

    'default_language' => 'fr',

    'timezone' => 'Europe/Paris',

    'items_per_page' => 20,

    'show_tutorial' => true,

],

/*
|--------------------------------------------------------------------------
| Configuration des champs pour l'interface
|--------------------------------------------------------------------------
*/

'fields' => [

    /*
    |--------------------------------------------------------------------------
    | SYSTEM
    |--------------------------------------------------------------------------
    */

    'system.maintenance_mode' => [
        'type' => 'boolean',
        'label' => 'Mode maintenance',
        'description' => 'Activer le mode maintenance pour bloquer l’accès au site.',
    ],

    'system.maintenance_message' => [
        'type' => 'text',
        'label' => 'Message de maintenance',
        'description' => 'Message affiché aux utilisateurs pendant la maintenance.',
    ],

    'system.security.password_min_length' => [
        'type' => 'number',
        'label' => 'Longueur minimale du mot de passe',
        'min' => 6,
        'max' => 20,
        'description' => 'Nombre minimum de caractères requis pour les mots de passe.',
    ],

    'system.security.password_require_special' => [
        'type' => 'boolean',
        'label' => 'Caractère spécial obligatoire',
        'description' => 'Exiger au moins un caractère spécial dans les mots de passe.',
    ],

    'system.security.max_login_attempts' => [
        'type' => 'number',
        'label' => 'Tentatives maximum',
        'min' => 1,
        'max' => 10,
        'description' => 'Nombre maximum de tentatives de connexion avant blocage.',
    ],

    'system.registration_enabled' => [
        'type' => 'boolean',
        'label' => 'Inscriptions autorisées',
        'description' => 'Permettre aux nouveaux utilisateurs de créer un compte.',
    ],

    'system.session_lifetime' => [
        'type' => 'number',
        'label' => 'Durée de session',
        'min' => 60,
        'max' => 1440,
        'description' => 'Durée de vie d’une session utilisateur (en minutes).',
    ],

    /*
    |--------------------------------------------------------------------------
    | USER
    |--------------------------------------------------------------------------
    */

    'user.theme' => [
        'type' => 'select',
        'label' => 'Thème',
        'options' => [
            'light' => 'Clair',
            'dark' => 'Sombre',
        ],
        'description' => 'Choisissez le thème de l’interface.',
    ],

    'user.notifications.email' => [
        'type' => 'boolean',
        'label' => 'Notifications email',
        'description' => 'Recevoir les notifications par email.',
    ],

    'user.notifications.sms' => [
        'type' => 'boolean',
        'label' => 'Notifications SMS',
        'description' => 'Recevoir les notifications par SMS.',
    ],

    'user.notifications.push' => [
        'type' => 'boolean',
        'label' => 'Notifications push',
        'description' => 'Recevoir les notifications push.',
    ],

    'user.default_language' => [
        'type' => 'select',
        'label' => 'Langue',
        'options' => [
            'fr' => 'Français',
            'en' => 'English',
            'es' => 'Español',
            'de' => 'Deutsch',
        ],
        'description' => 'Langue utilisée par défaut dans l’application.',
    ],

    'user.timezone' => [
        'type' => 'select',
        'label' => 'Fuseau horaire',
        'options' => [
            'Europe/Paris' => '🇫🇷 Paris',
            'Europe/London' => '🇬🇧 Londres',
            'Europe/Berlin' => '🇩🇪 Berlin',
            'America/New_York' => '🇺🇸 New York',
        ],
        'description' => 'Fuseau horaire utilisé pour afficher les dates.',
    ],

    'user.items_per_page' => [
        'type' => 'select',
        'label' => 'Éléments par page',
        'options' => [
            10 => '10 éléments',
            20 => '20 éléments',
            50 => '50 éléments',
            100 => '100 éléments',
        ],
        'description' => 'Nombre d’éléments affichés dans les listes.',
    ],

    'user.show_tutorial' => [
        'type' => 'boolean',
        'label' => 'Afficher le tutoriel',
        'description' => 'Afficher le tutoriel lors de la première connexion.',
    ],

],

/*
|--------------------------------------------------------------------------
| Organisation des paramètres dans l'interface
|--------------------------------------------------------------------------
*/

'groups' => [

    'system' => [
        'label' => 'Système',
        'icon' => '🔧',
        'fields' => [
            'system.maintenance_mode',
            'system.maintenance_message',
            'system.security.password_min_length',
            'system.security.password_require_special',
            'system.security.max_login_attempts',
            'system.registration_enabled',
            'system.session_lifetime',
        ],
    ],

    'apparence' => [
        'label' => 'Apparence',
        'icon' => '🎨',
        'fields' => [
            'user.theme',
            'user.items_per_page',
            'user.show_tutorial',
        ],
    ],

    'notifications' => [
        'label' => 'Notifications',
        'icon' => '🔔',
        'fields' => [
            'user.notifications.email',
            'user.notifications.sms',
            'user.notifications.push',
        ],
    ],

    'localisation' => [
        'label' => 'Localisation',
        'icon' => '🌍',
        'fields' => [
            'user.default_language',
            'user.timezone',
        ],
    ],

],

];

