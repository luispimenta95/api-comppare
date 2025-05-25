<?php

use App\Http\Controllers\Api\PastasController;
use Illuminate\Support\Facades\Route;

Route::prefix('pasta')->group(
    function () {
        Route::middleware(['jwt.auth'])->group(function () {
            Route::post('/create', [PastasController::class, 'create']);
        });
    }
);
