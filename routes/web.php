<?php

use App\Http\Controllers\Api\CupomController;
use App\Http\Controllers\Api\PlanoController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\VendasController;
use App\Http\Controllers\ConviteController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RankingController;

Route::middleware('api')->group(function () {
    Route::get('/api/test', function () {
        $apiVersion = env('APP_VERSION');  // 'default_version' é o valor padrão caso a variável não exista

        return response()->json(['message' => 'API funcional na versão: ' . $apiVersion]);
    });
    //Rotas user
    Route::post('/api/usuarios/autenticar', [UsuarioController::class, 'autenticar']);
    Route::post('/api/usuarios/cadastrar', [UsuarioController::class, 'cadastrarUsuario']);
    Route::get('/api/usuarios/listar', [UsuarioController::class, 'index']);
    Route::post('/api/usuarios/recuperar', [UsuarioController::class, 'getUser']);
    Route::post('/api/usuarios/atualizar-status', [UsuarioController::class, 'atualizarStatus']);
    Route::post('/api/usuarios/atualizar-dados', [UsuarioController::class, 'atualizarDados']);
    Route::post('/api/usuarios/valida-existencia-usuario', [UsuarioController::class, 'validaExistenciaUsuario']);
    Route::post('/api/usuarios/atualizar-senha', [UsuarioController::class, 'atualizarSenha']);
    //Rotas planos
    Route::post('/api/planos/cadastrar', [PlanoController::class, 'createPlan']);
    Route::get('/api/planos/listar', [PlanoController::class, 'index']);
    Route::post('/api/planos/recuperar', [PlanoController::class, 'getPlano']);
    Route::post('/api/planos/atualizar-status', [PlanoController::class, 'atualizarStatus']);
    Route::post('/api/planos/atualizar-dados', [PlanoController::class, 'atualizarDados']);
    Route::post('/api/planos/atualizar-funcionalidades', [PlanoController::class, 'adicionarFuncionalidades']);
    //Rotas cupons
    Route::post('/api/cupons/cadastrar', [CupomController::class, 'saveTicket']);
    Route::get('/api/cupons/listar', [CupomController::class, 'index']);
    Route::post('/api/cupons/recuperar', [CupomController::class, 'getTicketDiscount']);
    Route::post('/api/cupons/atualizar-status', [CupomController::class, 'atualizarStatus']);
    Route::post('/api/cupons/atualizar-dados', [CupomController::class, 'atualizarDados']);
    Route::post('/api/cupons/verificar-status', [CupomController::class, 'checkStatusTicket']);


    //Tags
    Route::post('/api/tags/cadastrar', [TagController::class, 'cadastrarTag']);
    Route::get('/api/tags/listar', [TagController::class, 'index']);
    Route::post('/api/tags/recuperar', [TagController::class, 'getTag']);
    Route::post('/api/tags/atualizar-status', [TagController::class, 'atualizarStatus']);
    Route::post('/api/tags/atualizar-dados', [TagController::class, 'atualizarDados']);
    Route::post('/api/tags/recuperar-tags-usuario', [TagController::class, 'getTagsByUsuario']);

    Route::post('/api/vendas/criar-assinatura', [VendasController::class, 'createSubscription']);
    Route::post('/api/ranking/atualizar', [RankingController::class, 'updatePoints']);
    Route::get('/api/ranking/classificacao', [RankingController::class, 'index']);

    Route::post('/api/notification', [VendasController::class, 'updatePayment']);

    //Convites

    Route::post('api/convites/enviar', [ConviteController::class, 'create']);

});
