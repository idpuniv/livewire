<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\WebhookController;

Route::get('/', function () {
    return view('welcome');
});

Route::post('printer', [WebhookController::class, 'printer'])->name('printer.webhook');

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});


Route::livewire('/products', 'pages::products.index');
Route::livewire('/promotions', 'pages::promotions.index');

Route::livewire('/caisse', 'pages::payments.create');
Route::livewire('/users', 'pages::users.create');
Route::livewire('/users2', 'pages::users.create2');

Route::middleware('auth')->group(function () {
    Route::livewire('/ventes', 'pages::sales.create');
    Route::livewire('/orders', 'pages::orders.index');
});

Route::get('/products/list', function () {
    return view('products');
});

Route::middleware('auth')->group(function () {

Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
Route::post('/settings/update', [SettingController::class, 'update'])->name('settings.update');

});

require __DIR__.'/auth.php';
