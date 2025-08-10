# Implementação PIX com mTLS - EFI Pay

## Visão Geral

Esta implementação segue as especificações do Banco Central e da EFI Pay para webhooks PIX com autenticação mútua TLS (mTLS). O sistema suporta tanto mTLS completo quanto skip-mTLS para ambientes de hospedagem compartilhada.

## Fluxo mTLS Implementado

### 1. Validação Dupla da EFI

A EFI faz 2 requisições para validar o servidor:

1. **Primeira Requisição**: Sem certificado - deve ser rejeitada
2. **Segunda Requisição**: Com certificado EFI - deve ser aceita

### 2. Endpoints PIX Configurados

#### Endpoints Principais
- `POST /api/pix` - Webhook principal (resposta simples "200")
- `POST /api/pix/atualizar` - Webhook detalhado (processamento completo)
- `POST /api/pix/webhook-simple` - Webhook simplificado

#### Endpoints de Configuração
- `PUT /api/pix/webhook` - Configurar webhook na EFI
- `GET /api/pix/webhook-status` - Status do webhook
- `GET /api/pix/ssl-status` - Status detalhado SSL/TLS
- `GET /api/pix/test-tls` - Teste de validação mTLS

### 3. Validação de Certificados

#### Métodos de Validação Implementados:

1. **Validação por IP**: IP da EFI (34.193.116.226) é automaticamente aceito
2. **Validação por Certificado**: Parse do certificado cliente para verificar:
   - Domínios EFI: `efipay.com.br`, `gerencianet.com.br`, `efi.com.br`, `pix.bcb.gov.br`
   - Organizações: `EFI Pay`, `Gerencianet`, `EFI S.A.`, `EFI`, `Banco Central do Brasil`

#### Suporte a Headers Nginx:
- `SSL-Client-Cert`
- `SSL-Client-Verify`
- `SSL-Client-Subject-DN`
- `SSL-Client-Issuer-DN`

### 4. Configuração Skip-mTLS

Para hospedagem compartilhada, use o header:
```
x-skip-mtls-checking: true
```

Neste modo, a validação é feita por:
- IP da EFI (34.193.116.226)
- HMAC na URL (recomendado)

## Configuração do Servidor

### Nginx (Recomendado)

```nginx
# Certificado CA da EFI
ssl_client_certificate /etc/ssl/certs/efi-ca.crt;

# Webhook PIX - mTLS obrigatório
location /api/pix/atualizar {
    ssl_verify_client on;
    ssl_verify_depth 2;
    
    proxy_set_header SSL-Client-Cert $ssl_client_cert;
    proxy_set_header SSL-Client-Verify $ssl_client_verify;
    proxy_set_header SSL-Client-Subject-DN $ssl_client_s_dn;
    proxy_set_header SSL-Client-Issuer-DN $ssl_client_i_dn;
    
    proxy_pass http://127.0.0.1:8000;
}
```

### Certificados Necessários

1. **Certificado do Servidor**: SSL válido para seu domínio
2. **CA EFI**:
   - Produção: https://certificados.efipay.com.br/webhooks/certificate-chain-prod.crt
   - Homologação: https://certificados.efipay.com.br/webhooks/certificate-chain-homolog.crt

## Configuração Laravel

### Middleware TLS Mútuo

O middleware `ValidateTlsMutual` valida automaticamente:
- Certificados da EFI
- IP oficial da EFI
- Headers do Nginx

### Variáveis de Ambiente (.env)

```bash
# URLs da API PIX
URL_API_PIX_LOCAL=https://api-h.efipay.com.br
URL_API_PIX_PRODUCAO=https://api.efipay.com.br

# Webhook PIX
WEBHOOK_PIX_URL=https://api.comppare.com.br/api/pix

# Certificados EFI
EFI_CERTIFICADO_PRD=/path/to/certificates/prd.pem
EFI_CERTIFICADO_HML=/path/to/certificates/hml.pem

# Chave PIX
CHAVE_PIX=sua_chave_pix_aqui
```

## Como Configurar Webhook

### 1. Via Endpoint

```bash
curl -X PUT https://api.comppare.com.br/api/pix/webhook \
  -H "Content-Type: application/json" \
  -d '{
    "webhookUrl": "https://api.comppare.com.br/api/pix",
    "skip_mtls": false
  }'
```

### 2. Skip-mTLS (Hospedagem Compartilhada)

```bash
curl -X PUT https://api.comppare.com.br/api/pix/webhook \
  -H "Content-Type: application/json" \
  -H "x-skip-mtls-checking: true" \
  -d '{
    "webhookUrl": "https://api.comppare.com.br/api/pix?hmac=sua_hash_aqui",
    "skip_mtls": true
  }'
```

## Testes

### 1. Testar mTLS

```bash
curl https://api.comppare.com.br/api/pix/test-tls
```

### 2. Verificar Status SSL

```bash
curl https://api.comppare.com.br/api/pix/ssl-status
```

### 3. Status do Webhook

```bash
curl https://api.comppare.com.br/api/pix/webhook-status
```

## Logs

Todos os eventos são logados em:
- `storage/logs/laravel.log`

Logs incluem:
- Tentativas de acesso ao webhook
- Validação de certificados
- IPs de origem
- Dados do certificado cliente

## Segurança

### mTLS Completo
- Certificado EFI é validado automaticamente
- Nginx rejeita conexões sem certificado válido
- Validação dupla: servidor + aplicação

### Skip-mTLS
- Validação por IP da EFI
- Recomendado adicionar HMAC na URL
- Logs detalhados para auditoria

## Troubleshooting

### Problemas Comuns

1. **Certificado não encontrado**
   - Verificar paths dos certificados
   - Verificar permissões de leitura

2. **mTLS falha**
   - Verificar configuração Nginx
   - Verificar CA EFI
   - Verificar headers SSL

3. **IP bloqueado**
   - Verificar se 34.193.116.226 está liberado
   - Verificar proxy/CDN

### Debug

Use os endpoints de teste para diagnósticos:
- `/api/pix/test-tls` - Informações SSL/TLS
- `/api/pix/ssl-status` - Status dos certificados
- `/api/pix/webhook-status` - Status geral do webhook

## Compliance

✅ Especificação Banco Central  
✅ Requisitos EFI Pay  
✅ TLS 1.2+ obrigatório  
✅ Validação mútua  
✅ Skip-mTLS para hospedagem compartilhada  
✅ Logs de auditoria  
✅ Resposta "200" simples conforme especificação  

## Próximos Passos

1. **Produção**: Configurar certificados reais
2. **Monitoramento**: Implementar alertas para falhas mTLS
3. **Jobs**: Implementar processamento assíncrono para webhooks
4. **Cache**: Implementar cache de validação de certificados
5. **Renovação**: Automatizar renovação de certificados
