<?php

use App\Http\Controllers\Api\UsuarioController;
use Illuminate\Support\Facades\Route;

Route::prefix('usuarios')->group(
    function () {

        Route::post('/autenticar', [UsuarioController::class, 'autenticar']);
        Route::post('/cadastrar', [UsuarioController::class, 'cadastrarUsuario']);
        Route::get('/listar', [UsuarioController::class, 'index']);
        Route::post('/recuperar', [UsuarioController::class, 'getUser']);
        Route::post('/atualizar-status', [UsuarioController::class, 'atualizarStatus']);
        Route::post('/valida-existencia-usuario', [UsuarioController::class, 'validaExistenciaUsuario']);

        Route::middleware(['jwt.auth'])->group(function () {
            Route::post('/atualizar-senha', [UsuarioController::class, 'atualizarSenha']);
            Route::post('/atualizar-plano', [UsuarioController::class, 'atualizarPlanoUsuario']);
            Route::post('/atualizar-dados', [UsuarioController::class, 'atualizarDados']);
        });
    }
);
