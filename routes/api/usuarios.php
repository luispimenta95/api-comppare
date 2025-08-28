<?php

use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\RankingController;

use Illuminate\Support\Facades\Route;

Route::prefix('usuarios')->group(
    function () {

        Route::post('/autenticar', [UsuarioController::class, 'autenticar']);
        Route::post('/cadastrar', [UsuarioController::class, 'cadastrarUsuario']);
        Route::post('/recuperar', [UsuarioController::class, 'getUser']);
        Route::post('/atualizar-status', [UsuarioController::class, 'atualizarStatus']);
        Route::post('/valida-existencia-usuario', [UsuarioController::class, 'validaExistenciaUsuario']);
        Route::post('/atualizar-senha', [UsuarioController::class, 'atualizarSenha']);
        Route::post('/esqueceu-senha', [UsuarioController::class, 'forgotPassword']);

        Route::middleware(['jwt.auth'])->group(function () {
            Route::post('/atualizar-plano', [UsuarioController::class, 'atualizarPlanoUsuario']);
            Route::post('/atualizar-dados', [UsuarioController::class, 'atualizarDados']);
            Route::get('/ranking/classificacao', [RankingController::class, 'index']);
            Route::get('/pastas/{id}', [UsuarioController::class, 'getPastasEstruturadas']);
        });
    }
);
