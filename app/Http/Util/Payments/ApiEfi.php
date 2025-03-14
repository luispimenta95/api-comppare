<?php

namespace App\Http\Util\Payments;
use App\Http\Util\Helper;
use Efi\Exception\EfiException;
use Efi\EfiPay;
use Illuminate\Http\JsonResponse;

class ApiEfi
{

    private array $options = [];
    private array $params = [];
    private EfiPay $efiPay;

    public function __construct()
    {
        $this->options = [
            "clientId" => env('ID_EFI_PRD'),
            "clientSecret" => env('SECRET_EFI_PRD'),
            "sandbox" => false, // Opcional | Padrão = false | Define o ambiente de desenvolvimento entre Produção e Homologação
            "debug" => false, // Opcional | Padrão = false | Ativa/desativa os logs de requisições do Guzzle
            "timeout" => 30, // Opcional | Padrão = 30 | Define o tempo máximo de resposta das requisições
            "responseHeaders" => false
        ];

        $this->efiPay = new EfiPay($this->options);


    }

    public function createPlan(string $name):mixed
    {
        try {
            $body = [
                "name" => $name,
                "interval" => Helper::INTERVALO_MENSAL,
                "repeats" => null
            ];

            $response = $this->efiPay->createPlan($this->params, $body);
            return json_encode($response);
        } catch (EfiException $e) {
            return json_encode(
                [
                    "code" => $e->code,
                    "Erro" => $e->error,
                    "description" => $e->errorDescription
                ]
            );
        }
    }
    public function createSubscription(array $dados): mixed{
        $params = [
            "id" => 122656
        ];
        //dd($dados['produto']);

        $body = [
            "items" =>  [ $dados['produto']],
            "payment" => [
                "credit_card" => [
                    "payment_token" =>  $dados['cardToken'],
                    "customer" =>  $dados['usuario']
                ]
            ]
        ];
        try {
            return json_encode($this->efiPay->createOneStepSubscription($params, $body));
        }catch (EfiException $e) {
            return json_encode(
                [
                    "code" => $e->code,
                    "Erro" => $e->error,
                    "description" => $e->errorDescription
                ]
            );
        }

    }
}
