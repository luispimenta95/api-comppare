<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateTlsMutual
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar se a requisição possui certificado cliente válido
        if (!$this->hasValidClientCertificate()) {
            return response()->json([
                'observacao' => 'Apresente um certificado cliente válido da EFI Pay.'
            ], 403);
        }

        return $next($request);
    }

    /**
     * Verifica se a requisição possui certificado cliente válido
     */
    private function hasValidClientCertificate(): bool
    {
        $clientCert = $_SERVER['SSL_CLIENT_CERT'] ?? null;
        $clientCertVerify = $_SERVER['SSL_CLIENT_VERIFY'] ?? null;

        if (!$clientCert || $clientCertVerify !== 'SUCCESS') {
            return false;
        }

        return $this->isEfiCertificate($clientCert);
    }

    /**
     * Verifica se o certificado é da EFI Pay
     */
    private function isEfiCertificate(string $clientCert): bool
    {
        try {
            $certInfo = openssl_x509_parse($clientCert);
            
            if (!$certInfo) {
                return false;
            }

            $subject = $certInfo['subject'] ?? [];
            $commonName = $subject['CN'] ?? '';
            $organization = $subject['O'] ?? '';

            // Domínios e organizações confiáveis da EFI
            $efiDomains = ['efipay.com.br', 'gerencianet.com.br', 'efi.com.br'];
            $efiOrganizations = ['EFI Pay', 'Gerencianet', 'EFI S.A.'];

            foreach ($efiDomains as $domain) {
                if (strpos($commonName, $domain) !== false) {
                    return true;
                }
            }

            foreach ($efiOrganizations as $org) {
                if (strpos($organization, $org) !== false) {
                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Erro ao validar certificado EFI', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
