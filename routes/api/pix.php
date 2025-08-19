<?php

use App\Http\Controllers\Api\PixController;
use Illuminate\Support\Facades\Route;

// Rotas para PIX
Route::prefix('pix')->group(function () {
    Route::post('/enviar', [PixController::class, 'criarCobranca']);
    Route::get('/cadastrar', [PixController::class, 'registrarWebhook']);
    Route::get('/ver-webhook', [PixController::class, 'consultarWebhookRecorrente']);
    Route::post('/atualizar', [PixController::class, 'atualizarCobranca']);
});
