# Soluções para "SSL certificate problem: self-signed certificate in certificate chain"

## 🚨 Problema
O erro `SSL certificate problem: self-signed certificate in certificate chain` ocorre quando o sistema tenta conectar com a API EFI usando certificados auto-assinados ou em ambientes de desenvolvimento local.

## ✅ Soluções Implementadas

### 1. **Configuração Automática por Ambiente**
O sistema agora detecta automaticamente o ambiente e ajusta as configurações SSL:

```php
// Em PixController.php
$sslVerifyDisabled = config('app.ssl_verify_disabled', false) || env('SSL_VERIFY_DISABLED', false);

if ($this->enviroment === 'local' || $sslVerifyDisabled) {
    // Desabilita verificação SSL para desenvolvimento
    $curlOptions[CURLOPT_SSL_VERIFYPEER] = false;
    $curlOptions[CURLOPT_SSL_VERIFYHOST] = false;
}
```

### 2. **Configuração no .env**
Para resolver o problema em desenvolvimento, configure:

```bash
# .env
APP_ENV=local
SSL_VERIFY_DISABLED=true
WEBHOOK_PIX_URL=https://localhost:8000/api/pix/atualizar
```

### 3. **Mensagens de Erro Melhoradas**
O sistema agora fornece instruções específicas baseadas no ambiente:

- **Desenvolvimento**: Instruções para desabilitar SSL verification
- **Produção**: Instruções para usar certificados válidos

### 4. **Endpoint de Diagnóstico**
Novo endpoint `GET /api/pix/ssl-status` para verificar:
- Status da verificação SSL
- Presença de certificados
- Configurações do ambiente

### 5. **Documentação Detalhada**
- README.md com seção de troubleshooting
- requests.http com exemplos de erros e soluções
- Swagger com documentação completa

## 🔧 Como Usar

### Para Desenvolvimento Local:
1. Configure no `.env`:
   ```bash
   APP_ENV=local
   SSL_VERIFY_DISABLED=true
   ```

2. Teste o webhook:
   ```http
   PUT /api/pix/webhook
   Content-Type: application/json
   
   {
     "webhookUrl": "https://localhost:8000/api/pix/atualizar"
   }
   ```

### Para Produção:
1. Configure certificados válidos em `storage/app/certificates/`
2. Configure no `.env`:
   ```bash
   APP_ENV=production
   SSL_VERIFY_DISABLED=false
   ```

### Para Testes com HTTPS Válido:
Use ngrok para obter certificado SSL válido:
```bash
ngrok http 8000
# Use a URL HTTPS gerada como WEBHOOK_PIX_URL
```

## 📋 Verificações

### 1. Verificar Status SSL:
```http
GET /api/pix/ssl-status
```

### 2. Verificar Certificados:
```bash
ls -la storage/app/certificates/
# Deve conter: cliente.pem, cliente.key (homologação)
#             cliente_prd.pem, cliente_prd.key (produção)
```

### 3. Verificar Logs:
```bash
tail -f storage/logs/laravel.log
# Procurar por: "SSL verification desabilitada" ou "Erro SSL detectado"
```

## ⚠️ Segurança

- **NUNCA** desabilite verificação SSL em produção
- Use `SSL_VERIFY_DISABLED=true` APENAS em desenvolvimento
- Em produção, sempre use certificados SSL válidos
- Configure TLS mútuo corretamente para webhooks PIX

## 🎯 Arquivos Modificados

1. `app/Http/Controllers/Api/PixController.php` - Lógica SSL e TLS mútuo
2. `app/Http/Util/requests.http` - Exemplos e troubleshooting
3. `app/Http/Util/pix-ssl-tests.http` - Testes específicos de SSL
4. `routes/api/pix.php` - Nova rota ssl-status
5. `swagger.yaml` - Documentação do endpoint
6. `README.md` - Seção de troubleshooting
7. `.env.example` - Configurações SSL
