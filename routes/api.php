<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PlanoController;
use App\Http\Controllers\Api\QuestoesController;

Route::get('/test', function () {
    return response()->json(['message' => 'API funcional na versão: ' . env('APP_VERSION')]);
});

Route::get('/planos/listar', [PlanoController::class, 'index']);
Route::get('/questoes/listar', [QuestoesController::class, 'listar']);

foreach (glob(__DIR__ . '/api/*.php') as $routeFile) {
    require $routeFile;
}
