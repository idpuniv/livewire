<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Paramètres par défaut
    |--------------------------------------------------------------------------
    */
    'site_name' => 'MonSuperApp',
    'site_logo' => '/images/logo.png',
    'site_url' => 'https://www.monsuperapp.com',
    'theme' => 'light',
    'items_per_page' => 20,
    'show_tutorial' => true,
    
    'notifications' => [
        'email' => true,
        'sms' => false,
        'push' => true,
    ],
    
    'default_language' => 'fr',
    'timezone' => 'Europe/Paris',
    
    'password' => [
        'min_length' => 8,
        'require_special' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuration des paramètres
    |--------------------------------------------------------------------------
    | Chaque paramètre peut avoir :
    | - type : text, number, boolean, select, email, etc.
    | - label : libellé affiché
    | - description : texte d'aide
    | - options : pour les selects
    | - min/max : pour les nombres
    | - placeholder : texte indicatif
    */
    'fields' => [
        'site_name' => [
            'type' => 'text',
            'label' => 'Nom du site',
            'placeholder' => 'Entrez le nom du site',
        ],
        'site_logo' => [
            'type' => 'image',
            'label' => 'Logo',
            'description' => 'Format recommandé : 200x200px',
        ],
        'site_url' => [
            'type' => 'url',
            'label' => 'URL du site',
            'placeholder' => 'https://...',
        ],
        'theme' => [
            'type' => 'select',
            'options' => [
                'light' => 'Clair',
                'dark' => 'Sombre',
            ],
            'label' => 'Thème',
            'description' => "Thème de l'application",
        ],
        'items_per_page' => [
            'type' => 'select',
            'options' => [
                10 => '10 éléments',
                20 => '20 éléments',
                50 => '50 éléments',
                100 => '100 éléments',
            ],
            'label' => 'Éléments par page',
        ],
        'show_tutorial' => [
            'type' => 'boolean',
            'label' => 'Tutoriel',
            'description' => 'Afficher le tutoriel au premier login',
        ],
        'notifications.email' => [
            'type' => 'boolean',
            'label' => 'Notifications email',
        ],
        'notifications.sms' => [
            'type' => 'boolean',
            'label' => 'Notifications SMS',
        ],
        'notifications.push' => [
            'type' => 'boolean',
            'label' => 'Notifications push',
        ],
        'default_language' => [
            'type' => 'select',
            'options' => [
                'fr' => 'Français',
                'en' => 'English',
                'es' => 'Español',
                'de' => 'Deutsch',
            ],
            'label' => 'Langue par défaut',
        ],
        'timezone' => [
            'type' => 'select',
            'options' => [
                'Europe/Paris' => '🇫🇷 Paris',
                'Europe/London' => '🇬🇧 Londres',
                'Europe/Berlin' => '🇩🇪 Berlin',
                'America/New_York' => '🇺🇸 New York',
            ],
            'label' => 'Fuseau horaire',
        ],
        'password.min_length' => [
            'type' => 'number',
            'min' => 6,
            'max' => 20,
            'label' => 'Longueur minimale',
            'description' => 'Longueur minimale du mot de passe',
        ],
        'password.require_special' => [
            'type' => 'boolean',
            'label' => 'Caractères spéciaux',
            'description' => 'Exiger des caractères spéciaux',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Organisation par groupes
    |--------------------------------------------------------------------------
    | Chaque groupe contient la liste des clés de paramètres
    */
    'groups' => [
        'général' => [
            'label' => 'Général',
            'icon' => '⚙️',
            'fields' => ['site_name', 'site_logo', 'site_url']
        ],
        'apparence' => [
            'label' => 'Apparence',
            'icon' => '🎨',
            'fields' => ['theme', 'items_per_page', 'show_tutorial']
        ],
        'notifications' => [
            'label' => 'Notifications',
            'icon' => '🔔',
            'fields' => ['notifications.email', 'notifications.sms', 'notifications.push']
        ],
        'localisation' => [
            'label' => 'Localisation',
            'icon' => '🌍',
            'fields' => ['default_language', 'timezone']
        ],
        'sécurité' => [
            'label' => 'Sécurité',
            'icon' => '🔒',
            'fields' => ['password.min_length', 'password.require_special']
        ],
    ],
];