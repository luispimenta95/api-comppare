<?php

use App\Http\Controllers\Api\PastasController;
use Illuminate\Support\Facades\Route;

Route::prefix('imagens')->group(
    function () {
        Route::middleware(['jwt.auth'])->group(function () {
            Route::post('/salvar', [PastasController::class, 'saveImageInFolder']);
            Route::delete('/excluir', [PastasController::class, 'deleteImageFromFolder']);
        });
    }
);
