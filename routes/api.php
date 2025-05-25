<?php

use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json(['message' => 'API funcional na versão: ' . env('APP_VERSION')]);
});

foreach (glob(__DIR__ . '/api/*.php') as $routeFile) {
    require $routeFile;
}
