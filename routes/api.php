<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PlanoController;
use App\Http\Controllers\Api\QuestoesController;
use App\Http\Controllers\Api\PixController;

Route::get('/test', function () {
    return response()->json(['message' => 'API funcional na versão: ' . env('APP_VERSION')]);
});

// Webhook PIX direto (endpoint principal para configuração na EFI)
Route::post('/pix', [PixController::class, 'webhookSimple'])->middleware('tls.mutual');

Route::get('/webhookcobr', [PixController::class, 'configurarWebhook']);



Route::get('/planos/listar', [PlanoController::class, 'index']);
Route::get('/questoes/listar', [QuestoesController::class, 'listar']);

foreach (glob(__DIR__ . '/api/*.php') as $routeFile) {
    require $routeFile;
}
