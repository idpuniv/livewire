<?php

namespace App\Providers;
use Event;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
            Event::listen(ModelNotFoundException::class, function ($e) {
        logger()->error('MODEL NOT FOUND', [
            'model' => $e->getModel(),
            'ids' => $e->getIds(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'session_id' => session()->getId(),
            'livewire' => request()->header('X-Livewire'),
        ]);
        
        // Décommenter pour voir la stack trace complète
        // logger()->error($e->getTraceAsString());
    });
    }
}
