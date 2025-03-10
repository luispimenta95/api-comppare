<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\CupomController;
use App\Http\Controllers\Api\PlanoController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\UsuarioController;
use App\Http\Controllers\Api\VendasController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MercadoPagoController;

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

    //Pagamentos
    Route::post('/api/vendas/salvar-pagamento', [VendasController::class, 'realizarVenda']);
    Route::post('/api/vendas/recuperar', [VendasController::class, 'recuperarVenda']);
    Route::get('/api/vendas/listar', [VendasController::class, 'listarVendas']);
    Route::get('/api/vendas/update-payment-single', [VendasController::class, 'updatePaymentSingle'])->name('updatePaymentSingle');
    Route::get('/api/vendas/create-subscription', [VendasController::class, 'createSubscription'])->name('createSubscription');
    Route::get('/api/vendas/update-payment-subscription', [VendasController::class, 'updatePaymentSubscription'])->name('updatePaymentSubscription');

    //Tags
    Route::post('/api/tags/cadastrar', [TagController::class, 'cadastrarTag']);
    Route::get('/api/tags/listar', [TagController::class, 'index']);
    Route::post('/api/tags/recuperar', [TagController::class, 'getTag']);
    Route::post('/api/tags/atualizar-status', [TagController::class, 'atualizarStatus']);
    Route::post('/api/tags/atualizar-dados', [TagController::class, 'atualizarDados']);
    Route::post('/api/planos/cadastrar', [AdminController::class, 'criarPlanoAssinatura']);

    Route::post('/webhook/mercadopago', [MercadoPagoController::class, 'handleWebhook']);



});
