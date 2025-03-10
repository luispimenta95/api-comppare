<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoPagoController extends Controller
{
    private string $accessToken;
    public function __construct(){
        $this->accessToken = env('ACCESS_TOKEN_TST');

    }
    /**
     * Webhook para capturar notificações do Mercado Pago.
     */
    public function handleWebhook(Request $request)
    {
        try {
            // Captura os dados enviados pelo Mercado Pago no webhook
            $data = $request->all();
            Log::info("Notificação recebida do Mercado Pago.", $data);

            // Verifica o tipo de notificação
            $type = $data['type'] ?? null;

            // Processar notificações de assinatura
            if ($type === 'preapproval') {
                $preapprovalId = $data['id'] ?? null;

                if ($preapprovalId) {
                    Log::info("Assinatura recebida no Webhook. ID: {$preapprovalId}");
                    $preapprovalDetails = $this->getPreapprovalDetails($preapprovalId);

                    if ($preapprovalDetails) {
                        Log::info("Detalhes da assinatura: ", $preapprovalDetails);

                        // Se houver `external_reference`, processe algo adicional
                        if (!empty($preapprovalDetails['external_reference'])) {
                            $externalData = json_decode($preapprovalDetails['external_reference'], true);
                            if (isset($externalData['custom_webhook'])) {
                                // Redireciona as informações para uma URL personalizada
                                $this->sendToCustomWebhook($externalData['custom_webhook'], $preapprovalDetails);
                            }
                        }
                    }
                }
            }

            // Processar notificações de pagamento
            elseif ($type === 'payment') {
                $paymentId = $data['data']['id'] ?? null;

                if ($paymentId) {
                    Log::info("Pagamento recebido no Webhook. ID: {$paymentId}");
                    $paymentDetails = $this->getPaymentDetails($paymentId);

                    if ($paymentDetails) {
                        Log::info("Detalhes do pagamento: ", $paymentDetails);

                        // Processar o status do pagamento
                        $status = $paymentDetails['status'] ?? null;
                        if ($status === 'approved') {
                            Log::info("Pagamento aprovado: {$paymentId}");
                            // Aqui você pode salvar no banco, enviar e-mail, etc.
                        } elseif ($status === 'rejected') {
                            Log::info("Pagamento rejeitado: {$paymentId}");
                            // Tratar pagamento rejeitado
                        }
                    }
                }
            }

            // Retorna sucesso para evitar novas tentativas de envio do Mercado Pago
            return response()->json(['status' => 'success'], 200);

        } catch (\Exception $e) {
            Log::error("Erro ao processar notificação do Mercado Pago: " . $e->getMessage());
            return response()->json(['error' => 'Erro ao processar notificação'], 500);
        }
    }

    /**
     * Obtem os detalhes de uma assinatura (preapproval) na API do Mercado Pago.
     */
    private function getPreapprovalDetails($preapprovalId)
    {
         // Substitua pelo token do ambiente correto
        $url = "https://api.mercadopago.com/preapproval/{$preapprovalId}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->accessToken}",
            "Content-Type: application/json"
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return json_decode($response, true);
        }

        Log::error("Erro ao buscar detalhes da assinatura (preapproval). Código HTTP: {$httpCode}");
        return null;
    }

    /**
     * Obtem os detalhes de um pagamento na API do Mercado Pago.
     */
    private function getPaymentDetails($paymentId)
    {
        $url = "https://api.mercadopago.com/v1/payments/{$paymentId}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->accessToken}",
            "Content-Type: application/json"
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            return json_decode($response, true);
        }

        Log::error("Erro ao buscar detalhes do pagamento. Código HTTP: {$httpCode}");
        return null;
    }

    /**
     * Envia os dados da notificação para um webhook personalizado.
     */
    private function sendToCustomWebhook($url, $data)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                Log::info("Webhook personalizado enviado com sucesso para {$url}");
            } else {
                Log::error("Erro ao enviar webhook personalizado para {$url}. Código HTTP: {$httpCode}");
            }
        } catch (\Exception $e) {
            Log::error("Erro ao enviar webhook personalizado: " . $e->getMessage());
        }
    }
}
