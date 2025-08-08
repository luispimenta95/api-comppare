<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PlanoController;
use App\Http\Controllers\Api\QuestoesController;
use App\Http\Controllers\Api\PixController;

Route::get('/test', function () {
    return response()->json(['message' => 'API funcional na versão: ' . env('APP_VERSION')]);
});

Route::get('/webhookcobr', [PixController::class, 'configurarWebhook']);



Route::get('/planos/listar', [PlanoController::class, 'index']);
Route::get('/questoes/listar', [QuestoesController::class, 'listar']);

foreach (glob(__DIR__ . '/api/*.php') as $routeFile) {
    require $routeFile;
}
