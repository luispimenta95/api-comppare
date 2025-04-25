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
use App\Enums\HttpCodesEnum;

class VendasController extends Controller
{
    //update server
    private array $codes = [];
    private ApiEfi $apiEfi;
    private const CARTAO = 'Cartão de crédito';
    private HttpCodesEnum $messages;




    public function __construct()
    {
        $this->codes = Helper::getHttpCodes();
        $this->apiEfi = new ApiEfi();
        $this->messages = HttpCodesEnum::OK;  // Usando a enum para um valor inicial
    }


    public function createSubscription(Request $request): JsonResponse
    {
        Log::info("Usuario:" .$request->usuario);
        Log::info("token:" .$request->token);
        Log::info("Plano:" .$request->plano);

        $campos = ['usuario', 'plano', 'token'];
        $campos = Helper::validarRequest($request, $campos);
        $response = [
            'codRetorno' => 200,
            'message' => $this->codes[200]
        ];
        //return response()->json($response);

        if ($campos !== true) {
            $this->messages = HttpCodesEnum::MissingRequiredFields;

            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => $this->messages->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        $usuario = Usuarios::find($request->usuario);
        $plano = Planos::find($request->plano);

        $dadosEmail = [
            'nome' => $usuario->nome,
        ];

        // Verifica se o idHost está definido no plano
        if ($plano && $plano->idHost !== null) {
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
                    "value" => $plano->valor * 100
                ]
            ];
            Log::info("Valor:" .$data['produto']['value']);
            $responseApi = json_decode($this->apiEfi->createSubscription($data), true);
            Log::info("CodRetorno:" .$responseApi['code']);
            Log::info("Description:" .$responseApi['description']);
            $response = [
                'codRetorno' => 200,
                'message' => $this->codes[200]
            ];
            return response()->json($response);

            if ($responseApi['code'] == 200) {
                $usuario->idUltimaCobranca = $responseApi['data']['charge']['id'];
                $usuario->dataLimiteCompra = Carbon::parse($responseApi['data']['first_execution'])->format('Y-m-d');
                $usuario->idAssinatura = $responseApi['data']['subscription_id'];
                $response = [
                    'codRetorno' => 200,
                    'message' => $this->codes[200]
                ];
            } else {
                $response = [
                    'codRetorno' => 400,
                    'message' => $responseApi['description']
                ];
            }
        } else {
            // Caso idHost esteja nulo, salva dataLimiteCompra para amanhã
            $usuario->dataLimiteCompra = Carbon::tomorrow()->format('Y-m-d');
        }
        $usuario->save();
        MailHelper::confirmacaoAssinatura($dadosEmail, $usuario->email);

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

    public function cancelarAssinatura(Request $request)
    {
        $campos = ['usuario'];
        $campos = Helper::validarRequest($request, $campos);

        if ($campos !== true) {
            $this->messages = HttpCodesEnum::MissingRequiredFields;

            $response = [
                'codRetorno' => HttpCodesEnum::BadRequest->value,
                'message' => $this->messages->description(),
                'campos' => $campos,
            ];
            return response()->json($response);
        }

        $usuario = Usuarios::where('id', $request->usuario)->first();

        $response = [];

        if ($usuario) {

            $responseApi = json_decode($this->apiEfi->cancelSubscription($usuario->idAssinatura), true);


            if ($responseApi['code'] == 200) {
                $usuario->status = 0;

                $response = [
                    'codRetorno' => HttpCodesEnum::OK->value,
                    'message' => HttpCodesEnum::SubscriptionCanceled->description()
                ];
            } else {
                $response =   [
                    'codRetorno' => HttpCodesEnum::BadRequest->value,
                    'message' => $responseApi['description']
                ];
            }
        } else {
            $response =   [
                'codRetorno' => HttpCodesEnum::NotFound->value,
                'message' =>  HttpCodesEnum::NotFound->description()
            ];
        }

        return response()->json($response);
    }


    public function receberDadosAssinatura(Request $request)
    {

        if($request->token !=null){
            $response =   [
                'codRetorno' => HttpCodesEnum::OK->value,
            ];

        return response()->json($response);

        }
    }
}
