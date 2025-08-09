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
        if (!$this->hasValidClientCertificate($request)) {
            Log::warning('Acesso negado ao webhook PIX - certificado inválido', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'ssl_info' => [
                    'server_ssl_client_cert' => isset($_SERVER['SSL_CLIENT_CERT']) ? 'presente' : 'ausente',
                    'nginx_ssl_client_cert' => $request->hasHeader('SSL-Client-Cert') ? 'presente' : 'ausente',
                    'ssl_client_verify' => $_SERVER['SSL_CLIENT_VERIFY'] ?? $request->header('SSL-Client-Verify') ?? 'não verificado'
                ]
            ]);
            
            return response()->json([
                'observacao' => 'Apresente um certificado cliente válido da EFI Pay.',
                'status' => 403,
                'mensagem' => 'Acesso negado - certificado mTLS inválido'
            ], 403);
        }

        return $next($request);
    }

    /**
     * Verifica se a requisição possui certificado cliente válido
     */
    private function hasValidClientCertificate(Request $request): bool
    {
        // Validar IP da EFI primeiro (para casos de skip-mTLS)
        $efiIp = '34.193.116.226';
        if ($request->ip() === $efiIp) {
            Log::info('Acesso permitido por IP da EFI', ['ip' => $request->ip()]);
            return true;
        }

        // Verificar certificado via $_SERVER ou headers Nginx
        $clientCert = $_SERVER['SSL_CLIENT_CERT'] ?? $request->header('SSL-Client-Cert');
        $clientCertVerify = $_SERVER['SSL_CLIENT_VERIFY'] ?? $request->header('SSL-Client-Verify');

        if (!$clientCert || $clientCertVerify !== 'SUCCESS') {
            return false;
        }

        return $this->isEfiCertificate($clientCert, $request->ip());
    }

    /**
     * Verifica se o certificado é da EFI Pay
     */
    private function isEfiCertificate(string $clientCert, string $clientIp = 'unknown'): bool
    {
        try {
            // Limpar e formatar certificado se necessário
            $cleanCert = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'], '', $clientCert);
            $cleanCert = str_replace(["\n", "\r", " "], '', $cleanCert);
            $cleanCert = "-----BEGIN CERTIFICATE-----\n" . chunk_split($cleanCert, 64, "\n") . "-----END CERTIFICATE-----";
            
            $certInfo = openssl_x509_parse($cleanCert);
            
            if (!$certInfo) {
                Log::warning('Falha ao fazer parse do certificado no middleware', ['client_ip' => $clientIp]);
                return false;
            }

            $subject = $certInfo['subject'] ?? [];
            $commonName = $subject['CN'] ?? '';
            $organization = $subject['O'] ?? '';

            // Domínios e organizações confiáveis da EFI
            $efiDomains = ['efipay.com.br', 'gerencianet.com.br', 'efi.com.br', 'pix.bcb.gov.br'];
            $efiOrganizations = ['EFI Pay', 'Gerencianet', 'EFI S.A.', 'EFI', 'Banco Central do Brasil'];

            foreach ($efiDomains as $domain) {
                if (strpos(strtolower($commonName), strtolower($domain)) !== false) {
                    Log::info('Certificado EFI válido no middleware', [
                        'common_name' => $commonName,
                        'domain' => $domain,
                        'client_ip' => $clientIp
                    ]);
                    return true;
                }
            }

            foreach ($efiOrganizations as $org) {
                if (strpos($organization, $org) !== false) {
                    Log::info('Certificado EFI válido por organização no middleware', [
                        'organization' => $organization,
                        'org' => $org,
                        'client_ip' => $clientIp
                    ]);
                    return true;
                }
            }

            Log::warning('Certificado não reconhecido como EFI no middleware', [
                'subject' => $subject,
                'client_ip' => $clientIp
            ]);

            return false;

        } catch (\Exception $e) {
            Log::error('Erro ao validar certificado EFI no middleware', [
                'error' => $e->getMessage(),
                'client_ip' => $clientIp
            ]);
            return false;
        }
    }
}
