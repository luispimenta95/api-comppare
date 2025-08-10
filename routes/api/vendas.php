<?php
use App\Http\Controllers\Api\VendasController;
use Illuminate\Support\Facades\Route;

Route::prefix('vendas')->group(
    function () {
        Route::middleware(['jwt.auth'])->group(function () {
         Route::post('/criar-assinatura', [VendasController::class, 'createSubscription']);
        });
    }
);

