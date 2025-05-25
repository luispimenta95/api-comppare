<?php

use App\Http\Controllers\Api\CupomController;
use App\Http\Controllers\Api\PlanoController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\VendasController;
use App\Http\Controllers\ConviteController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\PastasController;
use App\Http\Controllers\Api\QuestoesController;

Route::middleware('api')->group(function () {
    Route::get('/api/test', function () {
        $apiVersion = env('APP_VERSION');  // 'default_version' é o valor padrão caso a variável não exista

        return response()->json(['message' => 'API funcional na versão: ' . $apiVersion]);
    });

    // Rotas autenticadas
    Route::middleware(['jwt.auth'])->group(function () {

        Route::post('api/convites/enviar', [ConviteController::class, 'create']);
        Route::post('/api/pasta/create', [PastasController::class, 'create']);
        Route::post('/api/imagens/salvar', [PastasController::class, 'saveImageInFolder']);
        Route::post('/api/tags/cadastrar', [TagController::class, 'cadastrarTag']);
        Route::post('/api/tags/recuperar-tags-usuario', [TagController::class, 'getTagsByUsuario']);
        Route::post('/api/usuarios/atualizar-dados', [UsuarioController::class, 'atualizarDados']);
    });
    //Fim rotas autenticadas


    //Rotas planos
    Route::post('/api/planos/cadastrar', [PlanoController::class, 'createPlan']);
    Route::get('/api/planos/listar', [PlanoController::class, 'index']);
    Route::get('/api/planos/recuperar/{id}', [PlanoController::class, 'getPlano']);
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
    Route::get('/api/tags/listar', [TagController::class, 'index']);
    Route::post('/api/tags/recuperar', [TagController::class, 'getTag']);
    Route::post('/api/tags/atualizar-status', [TagController::class, 'atualizarStatus']);
    Route::post('/api/tags/atualizar-dados', [TagController::class, 'atualizarDados']);

    Route::post('/api/vendas/criar-assinatura', [VendasController::class, 'createSubscription']);
    Route::post('/api/vendas/cancelar-assinatura', [VendasController::class, 'cancelarAssinatura']);
    Route::post('/api/ranking/atualizar', [RankingController::class, 'updatePoints']);
    Route::get('/api/ranking/classificacao', [RankingController::class, 'index']);

    Route::post('/api/notification', [VendasController::class, 'updatePayment']);
    Route::post('/api/token/salvar', [VendasController::class, 'receberDadosAssinatura']);





    Route::post('/api/questoes/salvar', [QuestoesController::class, 'saveQuestion']);
    Route::get('/api/questoes/listar', [QuestoesController::class, 'listar']);
});
