<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransacaoFinanceiraRequest;
use App\Http\Requests\UpdateTransacaoFinanceiraRequest;
use App\Models\Cupom;
use App\Models\Planos;
use App\Models\TransacaoFinanceira;
use http\Client\Request;

class TransacaoFinanceiraController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    public function realizarVenda(Request $request)
    {
        $cupom = null;
        $codigoPix = null;
        $numeroCartao = null;
        $nomeTitular = null;
        $cpfTitular = null;
        $codigoVerificador = null;
        //logica para validar campos em caso de pix ou cartao de credito

            $valor = Planos::find($request->idPlano)->valor;
            if(isset($request->cupom)){
                $cupom = $request->cupom;
                $percentual = Cupom::find($request->cupom)->percentualDesconto;
                $valor = $valor - (($valor * $percentual) / 100);
            }

        $transacao = TransacaoFinanceira::create([
            'idPlano' => $request->idPlano,
            'valor' => $valor,
            'idUsuario' => $request->idUsuario,
            'cupom' => $cupom,
            'formaPagamento' => $request->formaPagamento,
            'codigoPix' => $codigoPix,
            'numeroCartao' => $numeroCartao,
            'nomeTitular' => $nomeTitular,
            'cpfTitular' => $cpfTitular,
            'codigoVerificador' => $codigoVerificador
        ]);

    }
}
