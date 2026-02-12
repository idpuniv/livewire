<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});


Route::livewire('/products', 'pages::products.index');
Route::livewire('/ventes', 'pages::sales.create');
Route::livewire('/caisse', 'pages::payments.create');
Route::livewire('/users', 'pages::users.create');
Route::livewire('/users2', 'pages::users.create2');



// Route::post('/checkout', [CheckoutController::class, 'create']);
// Route::post('/checkout/{checkout}/pay', [PaymentController::class, 'pay']);
// Route::post('/webhook/payment', [WebhookController::class, 'handle']);
