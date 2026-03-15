<?php

if (!function_exists('setting')) {
    function setting($key, $default = null)
    {
        $userId = auth()->id();
        $settings = cache()->get('settings_user_' . $userId, []);

        return $settings[$key] ?? $default;
    }
}