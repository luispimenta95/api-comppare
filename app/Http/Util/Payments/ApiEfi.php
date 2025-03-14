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
    private string $enviroment;
    private EfiPay $efiPay;

    public function __construct()
    {
        $this->enviroment =   env('APP_ENV');

        $this->options = [
            "clientId" =>  $this->enviroment == "local" ? env('ID_EFI_HML') : env('ID_EFI_PRD'),
            "clientSecret" => $this->enviroment == "local" ? env('SECRET_EFI_HML') : env('SECRET_EFI_PRD'),
            "sandbox" => $this->enviroment == "local" ? true : false,  // False = PRD | TRUE = DEV
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
            "id" => $dados["idPlano"],
        ];
        //dd($dados['cardToken']);

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
