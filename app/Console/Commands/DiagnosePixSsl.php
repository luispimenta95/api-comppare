<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DiagnosePixSsl extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pix:diagnose-ssl {--fix : Tentar corrigir automaticamente problemas de SSL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnostica problemas de SSL na integração PIX EFI';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Diagnóstico SSL PIX EFI');
        $this->line('');

        // Verificar ambiente
        $this->checkEnvironment();
        
        // Verificar certificados
        $this->checkCertificates();
        
        // Verificar configurações
        $this->checkConfiguration();
        
        // Testar conectividade
        $this->testConnectivity();
        
        // Oferecer correções se solicitado
        if ($this->option('fix')) {
            $this->attemptFixes();
        }
        
        $this->line('');
        $this->info('✅ Diagnóstico concluído!');
        
        return 0;
    }

    private function checkEnvironment()
    {
        $this->info('🏗️  Verificando ambiente...');
        
        $env = config('app.env');
        $sslDisabled = env('SSL_VERIFY_DISABLED', false);
        
        $this->line("Ambiente: <comment>{$env}</comment>");
        $this->line("SSL Verification Disabled: <comment>" . ($sslDisabled ? 'true' : 'false') . "</comment>");
        
        if ($env === 'production' && $sslDisabled) {
            $this->error('⚠️  ATENÇÃO: SSL verification está desabilitada em produção!');
            $this->warn('Isso é um risco de segurança. Configure certificados SSL válidos.');
        } elseif ($env === 'local' && !$sslDisabled) {
            $this->warn('💡 Dica: Para desenvolvimento, considere SSL_VERIFY_DISABLED=true');
        } else {
            $this->info('✅ Configuração de ambiente adequada');
        }
        
        $this->line('');
    }

    private function checkCertificates()
    {
        $this->info('📁 Verificando certificados...');
        
        $certificates = [
            'hml.pem' => 'Certificado EFI Homologação',
            'prd.pem' => 'Certificado EFI Produção',
            'cliente.pem' => 'Certificado Cliente Homologação (TLS mútuo)',
            'cliente.key' => 'Chave Cliente Homologação (TLS mútuo)',
            'cliente_prd.pem' => 'Certificado Cliente Produção (TLS mútuo)',
            'cliente_prd.key' => 'Chave Cliente Produção (TLS mútuo)'
        ];
        
        $certDir = storage_path('app/certificates');
        $missing = [];
        
        foreach ($certificates as $file => $description) {
            $path = "{$certDir}/{$file}";
            if (file_exists($path)) {
                $this->line("✅ {$description}: <info>{$file}</info>");
            } else {
                $this->line("❌ {$description}: <error>{$file} (FALTANDO)</error>");
                $missing[] = $file;
            }
        }
        
        if (!empty($missing)) {
            $this->line('');
            $this->warn('📥 Certificados em falta:');
            foreach ($missing as $file) {
                $this->line("   - {$file}");
            }
            $this->line('');
            $this->line('💡 Baixe os certificados da área do desenvolvedor EFI:');
            $this->line('   https://dev.efipay.com.br/');
        }
        
        $this->line('');
    }

    private function checkConfiguration()
    {
        $this->info('⚙️  Verificando configurações...');
        
        $configs = [
            'APP_URL' => env('APP_URL'),
            'WEBHOOK_PIX_URL' => env('WEBHOOK_PIX_URL'),
            'EFI_CLIENT_ID' => env('EFI_CLIENT_ID') ? 'Configurado' : 'Não configurado',
            'EFI_CLIENT_SECRET' => env('EFI_CLIENT_SECRET') ? 'Configurado' : 'Não configurado',
            'CHAVE_PIX' => env('CHAVE_PIX') ? 'Configurada' : 'Não configurada'
        ];
        
        foreach ($configs as $key => $value) {
            if ($value) {
                $this->line("✅ {$key}: <info>{$value}</info>");
            } else {
                $this->line("❌ {$key}: <error>Não configurado</error>");
            }
        }
        
        $this->line('');
    }

    private function testConnectivity()
    {
        $this->info('🌐 Testando conectividade...');
        
        // Testar se consegue resolver DNS
        $testUrl = 'api.efipay.com.br';
        $ip = gethostbyname($testUrl);
        
        if ($ip !== $testUrl) {
            $this->line("✅ DNS: <info>{$testUrl} → {$ip}</info>");
        } else {
            $this->line("❌ DNS: <error>Não foi possível resolver {$testUrl}</error>");
        }
        
        // Testar conectividade HTTPS básica
        $this->testHttpsConnectivity();
        
        $this->line('');
    }

    private function testHttpsConnectivity()
    {
        $this->line('🔐 Testando HTTPS...');
        
        $testUrl = 'https://api.efipay.com.br';
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'method' => 'GET'
            ],
            'ssl' => [
                'verify_peer' => !env('SSL_VERIFY_DISABLED', false),
                'verify_peer_name' => !env('SSL_VERIFY_DISABLED', false)
            ]
        ]);
        
        $result = @file_get_contents($testUrl, false, $context);
        
        if ($result !== false) {
            $this->line("✅ HTTPS: <info>Conectividade OK</info>");
        } else {
            $error = error_get_last();
            $this->line("❌ HTTPS: <error>Erro de conectividade</error>");
            if ($error) {
                $this->line("   Detalhes: <comment>{$error['message']}</comment>");
            }
        }
    }

    private function attemptFixes()
    {
        $this->line('');
        $this->info('🔧 Tentando correções automáticas...');
        
        $env = config('app.env');
        $envPath = base_path('.env');
        
        if ($env === 'local') {
            // Para ambiente local, sugerir SSL_VERIFY_DISABLED=true
            if (!env('SSL_VERIFY_DISABLED', false)) {
                if ($this->confirm('Desabilitar verificação SSL para desenvolvimento?')) {
                    $this->updateEnvFile($envPath, 'SSL_VERIFY_DISABLED', 'true');
                    $this->info('✅ SSL_VERIFY_DISABLED definido como true');
                }
            }
            
            // Verificar se webhook URL está configurada
            if (!env('WEBHOOK_PIX_URL')) {
                $appUrl = env('APP_URL', 'http://localhost:8000');
                $webhookUrl = $appUrl . '/api/pix/atualizar';
                
                if ($this->confirm("Configurar WEBHOOK_PIX_URL como {$webhookUrl}?")) {
                    $this->updateEnvFile($envPath, 'WEBHOOK_PIX_URL', $webhookUrl);
                    $this->info('✅ WEBHOOK_PIX_URL configurada');
                }
            }
        }
        
        // Criar diretório de certificados se não existir
        $certDir = storage_path('app/certificates');
        if (!is_dir($certDir)) {
            mkdir($certDir, 0755, true);
            $this->info("✅ Diretório criado: {$certDir}");
        }
    }

    private function updateEnvFile($envPath, $key, $value)
    {
        $envContent = file_get_contents($envPath);
        
        if (strpos($envContent, "{$key}=") !== false) {
            // Atualizar valor existente
            $envContent = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $envContent);
        } else {
            // Adicionar nova variável
            $envContent .= "\n{$key}={$value}\n";
        }
        
        file_put_contents($envPath, $envContent);
    }
}
