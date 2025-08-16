<?php

use App\Http\Controllers\Api\ComparacaoImagemController;
use Illuminate\Support\Facades\Route;

Route::prefix('comparacao')->group(function () {
    Route::middleware(['jwt.auth'])->group(function () {
        Route::post('/salvar', [ComparacaoImagemController::class, 'store']);
        Route::get('/{id}', [ComparacaoImagemController::class, 'show']);
    });
});
