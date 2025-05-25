<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PlanoController;

Route::get('/test', function () {
    return response()->json(['message' => 'API funcional na versão: ' . env('APP_VERSION')]);
});

Route::get('/planos/listar', [PlanoController::class, 'index']);
foreach (glob(__DIR__ . '/api/*.php') as $routeFile) {
    require $routeFile;
}
