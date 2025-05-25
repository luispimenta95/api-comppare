<?php

use App\Http\Controllers\Api\PlanoController;
use App\Http\Controllers\Api\CupomController;
use App\Http\Controllers\Api\VendasController;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\QuestoesController;
use App\Http\Controllers\Api\UsuarioController;



use Illuminate\Support\Facades\Route;

Route::prefix('admin')->group(
    function () {

        Route::post('/planos/cadastrar', [PlanoController::class, 'createPlan']);
        Route::get('/usuarios/listar', [UsuarioController::class, 'index']);

        Route::get('/planos/recuperar/{id}', [PlanoController::class, 'getPlano']);
        Route::post('/planos/atualizar-status', [PlanoController::class, 'atualizarStatus']);
        Route::post('/planos/atualizar-dados', [PlanoController::class, 'atualizarDados']);
        Route::post('/planos/atualizar-funcionalidades', [PlanoController::class, 'adicionarFuncionalidades']);
        //Rotas cupons
        Route::post('/cupons/cadastrar', [CupomController::class, 'saveTicket']);
        Route::get('/cupons/listar', [CupomController::class, 'index']);
        Route::post('/cupons/recuperar', [CupomController::class, 'getTicketDiscount']);
        Route::post('/cupons/atualizar-status', [CupomController::class, 'atualizarStatus']);
        Route::post('/cupons/atualizar-dados', [CupomController::class, 'atualizarDados']);
        Route::post('/cupons/verificar-status', [CupomController::class, 'checkStatusTicket']);

        Route::post('/vendas/criar-assinatura', [VendasController::class, 'createSubscription']);
        Route::post('/vendas/cancelar-assinatura', [VendasController::class, 'cancelarAssinatura']);

        Route::post('/ranking/atualizar', [RankingController::class, 'updatePoints']);

        Route::post('/api/notification', [VendasController::class, 'updatePayment']);
        Route::post('/api/token/salvar', [VendasController::class, 'receberDadosAssinatura']);

        Route::post('/api/questoes/salvar', [QuestoesController::class, 'saveQuestion']);
        Route::get('/api/questoes/listar', [QuestoesController::class, 'listar']);

    }
);
