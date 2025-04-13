<?php

namespace App\Http\Controllers\Api;

use App\Http\Util\Payments\ApiEfi;
use Carbon\Carbon;
use App\Models\Planos;
use App\Models\Usuarios;
use App\Http\Util\Helper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use App\Http\Util\MailHelper;
use Illuminate\Support\Facades\Log;
use App\Models\TransacaoFinanceira;
use App\Http\Util\Payments\ApiMercadoPago;
use Illuminate\Http\Request;

class VendasController extends Controller
{
    //update server
    private array $codes = [];
    private ApiEfi $apiEfi;
    private const CARTAO = 'Cartão de crédito';


    public function __construct()
    {
        $this->codes = Helper::getHttpCodes();
        $this->apiEfi = new ApiEfi();
    }


    public function createSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'usuario' => 'required|exists:usuarios,id', // O usuario deve existir na tabela usuarios
            'plano' => 'required|exists:planos,id', // O plano deve existir na tabela planos
            'token' => 'required|string', // cardToken é obrigatório e deve ser uma string
            'valor' => 'required|float'
        ]);

        $usuario = Usuarios::find($request->usuario);
        $plano = Planos::find($request->plano);

        $data = [
            "cardToken" => $request->token,
            "idPlano" => $plano->idHost,
            "usuario" => [
                "name" => $usuario->nome,
                "cpf" => $usuario->cpf,
                "phone_number" => $usuario->telefone,
                "email" => $usuario->email,
                "birth" => Carbon::parse($usuario->dataNascimento)->format('Y-m-d')
            ],

            "produto" => [
                "name" => $plano->nome,
                "amount" => Helper::QUANTIDADE,
                "value" => $request->valor * 100 // Valor = Valor plano * 100
            ]

        ];

        $responseApi = json_decode($this->apiEfi->createSubscription($data), true);
        if ($responseApi['code'] == 200) {
            $usuario->idUltimaCobranca = $responseApi['data']['charge']['id'];
            $usuario->dataLimiteCompra = Carbon::parse($responseApi['data']['first_execution'])->format('Y-m-d');
            $usuario->save();
            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200]
            ];
            $dadosEmail = [
                'nome' => $usuario->nome,
            ];

            MailHelper::confirmacaoAssinatura($dadosEmail, $usuario->email);
        } else {
            $response = [
                'codRetorno' => 400,
                'message' => $responseApi['description']
            ];
        }
        return response()->json($response);
    }

    public function updatePayment(Request $request)
    {


        $chargeNotification = json_decode($this->apiEfi->getSubscriptionDetail($request->notification), true);

        foreach ($chargeNotification['data'] as $item) {
            if ($item['type'] === 'subscription_charge' && $item['status']['current'] === Helper::STATUS_APROVADO) {
                $usuario = Usuarios::where('idUltimaCobranca', $item['identifiers']['charge_id'])->first();
                if ($usuario) {
                    $plano = Planos::find($usuario->idPlano);
                    $usuario->dataUltimoPagamento = Carbon::parse($item['received_by_bank_at'])->format('Y-m-d');
                    $usuario->dataLimiteCompra = $usuario->dataUltimoPagamento->addDays($plano->frequenciaCobranca == 1 ? Helper::TEMPO_RENOVACAO_MENSAL : Helper::TEMPO_RENOVACAO_ANUAL)->setTimezone('America/Recife');
                    $usuario->status = 1;
                    $usuario->save();

                    TransacaoFinanceira::create([
                        'idPlano' => $plano->idPlano,
                        'idUsuario' => $request->idUsuario,
                        'formaPagamento' => self::CARTAO,
                        'valorPagamento' => ($item['value'] / 100),
                        'idPagamento' => $item['identifiers']['charge_id'],
                        'pagamentoEfetuado' => 1
                    ]);
                }
            }
        }
    }
}
