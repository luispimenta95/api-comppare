<?php

use App\Http\Controllers\Api\ConviteController;
use Illuminate\Support\Facades\Route;

// Rotas para Convite
Route::prefix('convite')->group(function () {
    Route::post('/cadastrar', [ConviteController::class, 'create']);
    Route::post('/vincular', [ConviteController::class, 'processarConvitesPendentes']);
  
});
