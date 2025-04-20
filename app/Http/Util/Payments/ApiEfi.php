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
    private string $url;
    public function __construct()
    {
        $this->enviroment =   env('APP_ENV');
        $this->url = $this->enviroment == "local" ?
            env("APP_URL") . 'api/notification?sandbox=true' :
            env("APP_URL") . "api/notification";

        $this->options = [
            "clientId" =>  $this->enviroment == "local" ? env('ID_EFI_HML') : env('ID_EFI_PRD'),
            "clientSecret" => $this->enviroment == "local" ? env('SECRET_EFI_HML') : env('SECRET_EFI_PRD'),
            "sandbox" => $this->enviroment == "local" ? true : false,  // False = PRD | TRUE = DEV
            "debug" => false, // Opcional | Padrão = false | Ativa/desativa os logs de requisições do Guzzle
            "timeout" => 30, // Opcional | Padrão = 30 | Define o tempo máximo de resposta das requisições
            "responseHeaders" => false,
        ];


        $this->efiPay = new EfiPay($this->options);
    }

    public function createPlan(string $name, int $frequencia): mixed
    {
        try {
            $body = [
                "name" => $name,
                "interval" => $frequencia,
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
    public function createSubscription(array $dados): mixed
    {
        $params = [
            "id" => $dados["idPlano"],
        ];
        //dd($dados['cardToken']);

        $body = [
            "items" =>  [$dados['produto']],
            "metadata" =>  ["notification_url" =>  $this->url],
            "payment" => [
                "credit_card" => [
                    // "trial_days" =>  Helper::TEMPO_GRATUIDADE,
                    "payment_token" =>  $dados['cardToken'],
                    "customer" =>  $dados['usuario']
                ]
            ]
        ];
        try {
            return json_encode($this->efiPay->createOneStepSubscription($params, $body));
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
    public function getSubscriptionDetail(string $token): mixed
    {
        try {
            $params = [
                "token" => $token
            ];
            //Erro ao recuperar dados
            return json_encode($this->efiPay->getNotification($params));
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
}
