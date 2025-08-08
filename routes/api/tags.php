<?php


use App\Http\Controllers\Api\TagController;
use Illuminate\Support\Facades\Route;

Route::prefix('tags')->group(
    function () {
            Route::middleware(['jwt.auth'])->group(function () {

            Route::post('/cadastrar', [TagController::class, 'cadastrarTag']);
            Route::get('/listar', [TagController::class, 'index']);
            Route::post('/recuperar', [TagController::class, 'getTag']);
            Route::post('/atualizar-status', [TagController::class, 'atualizarStatus']);
            Route::post('/atualizar-dados', [TagController::class, 'atualizarDados']);
            Route::post('/recuperar-tags-usuario', [TagController::class, 'getTagsByUsuario']);
            Route::delete('/excluir', [TagController::class, 'excluirTag']);
        });
    });


