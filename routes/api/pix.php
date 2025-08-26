<?php

use App\Http\Controllers\Api\VendasController;
use Illuminate\Support\Facades\Route;

// Rotas para PIX
Route::prefix('pix')->group(function () {
    Route::post('/enviar', [VendasController::class, 'criarCobranca']);
    Route::get('/cadastrar', [VendasController::class, 'registrarWebhook']);
    Route::get('/ver-webhook', [VendasController::class, 'consultarWebhookRecorrente']);
    Route::post('/atualizar', [VendasController::class, 'atualizarCobrancaPix']);
});
