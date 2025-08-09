<?php

use App\Http\Controllers\Api\PixController;
use Illuminate\Support\Facades\Route;

// Rotas para PIX
Route::prefix('pix')->group(function () {
    // Cria cobrança PIX recorrente
    Route::post('/recorrente', [PixController::class, 'createRecurrentCharge']);
    
    // Cria cobrança PIX dinâmica
    Route::post('/dinamico', [PixController::class, 'createDynamicCharge']);
    
    // Gera QR Code para cobrança existente
    Route::get('/qrcode/{txid}', [PixController::class, 'generateQRCode']);

    // Cria cobrança PIX usando o método criarCobranca
    Route::post('/enviar', [PixController::class, 'criarCobranca']);

    // Atualiza cobrança PIX existente (webhook da EFI - requer TLS mútuo)
    Route::post('/atualizar', [PixController::class, 'atualizarCobranca'])->middleware('tls.mutual');
    
    // Configura webhook PIX
    Route::put('/webhook', [PixController::class, 'configurarWebhook']);
    
    // Verifica status SSL e certificados (endpoint de diagnóstico)
    Route::get('/ssl-status', [PixController::class, 'sslStatus']);
    
    // Teste de validação TLS mútuo
    Route::get('/test-tls', [PixController::class, 'testTlsMutual']);
});
