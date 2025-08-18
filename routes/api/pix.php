<?php

use App\Http\Controllers\Api\PixController;
use Illuminate\Support\Facades\Route;

// Rotas para PIX
Route::prefix('pix')->group(function () {
    Route::post('/enviar', [PixController::class, 'criarCobranca']);
    Route::post('/cadastrar', [PixController::class, 'cadastrarWebhook']);
});
