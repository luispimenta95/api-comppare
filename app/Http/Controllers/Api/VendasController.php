<?php

namespace App\Http\Controllers\Api;

use App\Http\Util\Payments\ApiEfi;
use Carbon\Carbon;
use App\Models\Planos;
use App\Models\Usuarios;
use App\Http\Util\Helper;
use App\Http\Controllers\Controller;
use App\Http\Util\MailHelper;
use Illuminate\Http\JsonResponse;
use App\Models\TransacaoFinanceira;
use Illuminate\Http\Request;
use App\Enums\HttpCodesEnum;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Enums\MeioPagamentoEnum;
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
        Log::info('Iniciando criação de assinatura', [
            'request' => $request->all()
        ]);
        $response = [];


        $campos = ['usuario', 'plano', 'token'];
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

        $usuario = Usuarios::find($request->usuario);
        $plano = Planos::find($request->plano);

        $dadosEmail = [
            'to' => $usuario->email,
            'body' => [
                'nome' => $usuario->primeiroNome . " " . $usuario->sobrenome
            ]
        ];

        // Verifica se o idHost está definido no plano
        if ($plano && $plano->idHost !== null) {
            
            // Verificar se o usuário já possui uma assinatura ativa
            if (!empty($usuario->idAssinatura)) {
                Log::info('Usuário possui assinatura ativa, cancelando antes de criar nova', [
                    'usuario_id' => $usuario->id,
                    'assinatura_atual' => $usuario->idAssinatura
                ]);
                
                try {
                    // Cancelar assinatura existente
                    $cancelResponse = json_decode($this->apiEfi->cancelSubscription($usuario->idAssinatura), true);
                    
                    Log::info('Resposta do cancelamento da assinatura anterior', [
                        'response' => $cancelResponse,
                        'assinatura_cancelada' => $usuario->idAssinatura
                    ]);
                    
                    // Limpar dados da assinatura anterior independente do resultado
                    $usuario->idAssinatura = null;
                    $usuario->status = 0; // Desativar temporariamente
                    $usuario->save();
                    
                } catch (\Exception $e) {
                    Log::error('Erro ao cancelar assinatura anterior', [
                        'error' => $e->getMessage(),
                        'assinatura' => $usuario->idAssinatura
                    ]);
                    // Continuar com a criação da nova assinatura mesmo se o cancelamento falhar
                }
            }
            
            $valor = $plano->valor * 100;

            $data = [
                "cardToken" => $request->token,
                "idPlano" => $plano->idHost,
                "usuario" => [
                    "name" =>  $usuario->primeiroNome . " " . $usuario->sobrenome,
                    "cpf" => $usuario->cpf,
                    "phone_number" =>  $usuario->telefone,
                    "email" => $usuario->email,
                    "birth" => Carbon::parse($usuario->dataNascimento)->format('Y-m-d')
                ],
                "produto" => [
                    "name" => $plano->nome,
                    "amount" => Helper::QUANTIDADE,
                    "value" => $valor
                ]
            ];
            $responseApi = json_decode($this->apiEfi->createSubscription($data), true);
            Log::info('Resposta da API EFI ao criar assinatura', [
                'response' => $responseApi
            ]);

            // Verificando se o 'code' está dentro do 'body'
            if (isset($responseApi['body']['code']) && $responseApi['body']['code'] == 200) {
                // Extrair dados principais da resposta
                $subscriptionId = $responseApi['body']['data']['subscription_id'];
                $subscriptionStatus = $responseApi['body']['data']['status'];
                $planId = $responseApi['body']['data']['plan']['id'];
                $planInterval = $responseApi['body']['data']['plan']['interval'];
                $planRepeats = $responseApi['body']['data']['plan']['repeats'];
                $chargeId = $responseApi['body']['data']['charge']['id'];
                $chargeStatus = $responseApi['body']['data']['charge']['status'];
                $chargeParcel = $responseApi['body']['data']['charge']['parcel'];
                $chargeTotal = $responseApi['body']['data']['charge']['total'];
                $firstExecution = $responseApi['body']['data']['first_execution'];
                $total = $responseApi['body']['data']['total'];
                $paymentMethod = $responseApi['body']['data']['payment'];

                Log::info('Dados extraídos da resposta EFI', [
                    'subscription_id' => $subscriptionId,
                    'subscription_status' => $subscriptionStatus,
                    'plan_id' => $planId,
                    'plan_interval' => $planInterval,
                    'plan_repeats' => $planRepeats,
                    'charge_id' => $chargeId,
                    'charge_status' => $chargeStatus,
                    'charge_parcel' => $chargeParcel,
                    'charge_total' => $chargeTotal,
                    'first_execution' => $firstExecution,
                    'total' => $total,
                    'payment_method' => $paymentMethod
                ]);

            Mail::to($usuario->email)->send(new \App\Mail\EmailAssinatura($dadosEmail));

                if ($chargeStatus == Helper::STATUS_APROVADO) {
                    $usuario->idPlano = $request->plano;
                    $usuario->idAssinatura = $subscriptionId;
                    $usuario->idUltimaCobranca = $chargeId;
                    $usuario->status = 1; // Ativar usuário
                    $usuario->dataLimiteCompra = Carbon::now()->addDays($plano->frequenciaCobranca == 1 ? Helper::TEMPO_RENOVACAO_MENSAL : Helper::TEMPO_RENOVACAO_ANUAL)->setTimezone('America/Recife')->format('Y-m-d');
                    $usuario->dataUltimoPagamento = Carbon::now()->format('Y-m-d H:i:s');
                    $usuario->meioPagamento = MeioPagamentoEnum::CARTAO;
                    $usuario->save();
                    
                    //Envia email de confirmação de pagamento

                    Helper::enviarEmailPagamento($usuario, $plano, self::CARTAO);

                }

                // Atualizar dados do usuário
                TransacaoFinanceira::create([
                    'idPlano' => $plano->id,
                    'idUsuario' => $request->usuario,
                    'formaPagamento' => self::CARTAO,
                    'valorPagamento' => ($chargeTotal / 100),
                    'idPagamento' => $chargeId,
                    'pagamentoEfetuado' => 1
                ]);

                $response = [
                    'codRetorno' => 200,
                    'message' => $this->codes[200]
                ];
            } else {
                $usuario->dataLimiteCompra = Carbon::tomorrow()->format('Y-m-d');

                $response = [
                    'codRetorno' => 400,
                    'message' => $responseApi['description']
                ];
                return response()->json($response);
            }
        } else {
            // Caso idHost esteja nulo, salva dataLimiteCompra para amanhã
            $usuario->dataLimiteCompra = Carbon::tomorrow()->format('Y-m-d');
        }

        return response()->json($response);
    }


    public function updatePayment(Request $request)
    {

        Log::info('Recebendo notificação de cobrança');

        $chargeNotification = json_decode($this->apiEfi->getSubscriptionDetail($request->notification), true);

        foreach ($chargeNotification['data'] as $item) {
            if ($item['type'] === 'subscription_charge' && $item['status']['current'] === Helper::STATUS_APROVADO) {
                $usuario = Usuarios::where('idUltimaCobranca', $item['identifiers']['charge_id'])->first();
                if ($usuario) {
                    $plano = Planos::find($usuario->idPlano);
                    $usuario->dataUltimoPagamento = Carbon::parse($item['received_by_bank_at'])->format('Y-m-d');
                    $dataUltimoPagamento = Carbon::parse($item['received_by_bank_at']);
                    $usuario->dataLimiteCompra = $dataUltimoPagamento->addDays($plano->frequenciaCobranca == 1 ? Helper::TEMPO_RENOVACAO_MENSAL : Helper::TEMPO_RENOVACAO_ANUAL)->setTimezone('America/Recife')->format('Y-m-d');
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

                            Log::info('Cobrança atualizada com sucesso', [
                                'usuario_id' => $usuario->idUsuario,
                                'plano_id' => $plano->idPlano,
                                'valor' => $item['value'] / 100,
                                'data_pagamento' => $usuario->dataUltimoPagamento
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
            // Buscar informações do plano atual antes do cancelamento            
            $responseApi = json_decode($this->apiEfi->cancelSubscription($usuario->idAssinatura), true);
            Log::info('Resposta da API EFI ao cancelar assinatura', [
                'response' => $responseApi
            ]);
            if ($responseApi['code'] == 200) {
                // Cancelamento bem-sucedido
                $usuario->status = 0;
                $usuario->save();

                // Enviar email de cancelamento

                Helper::enviarEmailCancelamento($usuario);

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