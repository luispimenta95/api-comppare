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
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Em ambiente de desenvolvimento com SSL desabilitado, permitir acesso
        if (config('app.env') === 'local' && env('SSL_VERIFY_DISABLED', false)) {
            Log::info('TLS mútuo bypass - ambiente desenvolvimento', [
                'path' => $request->path(),
                'ip' => $request->ip()
            ]);
            return $next($request);
        }

        // Verificar se a requisição possui certificado cliente válido
        if (!$this->hasValidClientCertificate()) {
            Log::warning('Acesso negado - TLS mútuo inválido', [
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'ssl_client_verify' => $_SERVER['SSL_CLIENT_VERIFY'] ?? 'não presente'
            ]);

            return response()->json([
                'codRetorno' => 403,
                'message' => 'Acesso negado. Este endpoint requer autenticação TLS mútuo válida.',
                'error' => 'CLIENT_CERTIFICATE_REQUIRED',
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
        // Verificar variáveis SSL do servidor web
        $clientCert = $_SERVER['SSL_CLIENT_CERT'] ?? null;
        $clientCertVerify = $_SERVER['SSL_CLIENT_VERIFY'] ?? null;
        $clientCertSubject = $_SERVER['SSL_CLIENT_S_DN'] ?? null;

        // Certificado deve estar presente
        if (!$clientCert) {
            Log::debug('Certificado cliente não encontrado');
            return false;
        }

        // Certificado deve ter sido verificado com sucesso pelo servidor web
        if ($clientCertVerify !== 'SUCCESS') {
            Log::warning('Certificado cliente falhou na verificação', [
                'ssl_client_verify' => $clientCertVerify,
                'ssl_client_subject' => $clientCertSubject
            ]);
            return false;
        }

        // Validar se é um certificado da EFI
        return $this->isEfiCertificate($clientCert, $clientCertSubject);
    }

    /**
     * Verifica se o certificado é da EFI Pay
     */
    private function isEfiCertificate(string $clientCert, ?string $subject): bool
    {
        try {
            // Parse do certificado para validação adicional
            $certInfo = openssl_x509_parse($clientCert);
            
            if (!$certInfo) {
                Log::error('Erro ao fazer parse do certificado cliente');
                return false;
            }

            // Domínios e organizações confiáveis da EFI
            $trustedDomains = [
                'efipay.com.br',
                'gerencianet.com.br',
                'efi.com.br',
                'api.efipay.com.br'
            ];

            $trustedOrganizations = [
                'EFI Pay',
                'Gerencianet',
                'EFI S.A.',
                'EFI Pay S.A.'
            ];

            $certSubject = $certInfo['subject'] ?? [];
            $commonName = $certSubject['CN'] ?? '';
            $organization = $certSubject['O'] ?? '';

            // Verificar Common Name contra domínios confiáveis
            foreach ($trustedDomains as $domain) {
                if (strpos($commonName, $domain) !== false) {
                    Log::info('Certificado EFI válido por domínio', [
                        'common_name' => $commonName,
                        'matched_domain' => $domain
                    ]);
                    return true;
                }
            }

            // Verificar organização
            foreach ($trustedOrganizations as $org) {
                if (strpos($organization, $org) !== false) {
                    Log::info('Certificado EFI válido por organização', [
                        'organization' => $organization,
                        'matched_org' => $org
                    ]);
                    return true;
                }
            }

            // Verificar Subject DN completo se disponível
            if ($subject) {
                foreach ($trustedDomains as $domain) {
                    if (strpos($subject, $domain) !== false) {
                        Log::info('Certificado EFI válido por subject DN', [
                            'subject_dn' => $subject,
                            'matched_domain' => $domain
                        ]);
                        return true;
                    }
                }
            }

            // Log detalhado para debug (apenas em desenvolvimento)
            if (config('app.env') === 'local') {
                Log::debug('Certificado não reconhecido como EFI', [
                    'cert_subject' => $certSubject,
                    'cert_issuer' => $certInfo['issuer'] ?? [],
                    'subject_dn' => $subject,
                    'valid_from' => date('Y-m-d H:i:s', $certInfo['validFrom_time_t'] ?? 0),
                    'valid_to' => date('Y-m-d H:i:s', $certInfo['validTo_time_t'] ?? 0)
                ]);
            }

            return false;

        } catch (\Exception $e) {
            Log::error('Erro ao validar certificado EFI no middleware', [
                'error' => $e->getMessage(),
                'subject_dn' => $subject
            ]);
            return false;
        }
    }
}
