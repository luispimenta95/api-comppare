# 🚨 SOLUÇÃO RÁPIDA: "SSL certificate problem: self-signed certificate in certificate chain"

## ✅ PASSOS PARA RESOLVER AGORA:

### 1. **Configure o ambiente para desenvolvimento** (MAIS IMPORTANTE)
Adicione estas linhas no seu arquivo `.env`:

```bash
# SSL Configuration for Development
SSL_VERIFY_DISABLED=true
WEBHOOK_PIX_URL=http://127.0.0.1:8000/api/pix/atualizar
```

### 2. **Limpe o cache do Laravel**
```bash
php artisan config:cache
php artisan route:cache
```

### 3. **Teste a configuração**
```bash
# Verificar status SSL
curl http://127.0.0.1:8000/api/pix/ssl-status

# Ou use um cliente HTTP como Postman/Insomnia:
GET http://127.0.0.1:8000/api/pix/ssl-status
```

### 4. **Teste a configuração do webhook**
```bash
# Com requests.http ou Postman:
PUT http://127.0.0.1:8000/api/pix/webhook
Content-Type: application/json

{}
```

### 5. **Se ainda der erro, use HTTP em vez de HTTPS** (apenas para teste)
```bash
PUT http://127.0.0.1:8000/api/pix/webhook
Content-Type: application/json

{
    "webhookUrl": "http://127.0.0.1:8000/api/pix/atualizar"
}
```

## 🔧 O QUE ISSO FAZ:

- **SSL_VERIFY_DISABLED=true**: Desabilita verificação de certificados SSL apenas em desenvolvimento
- **APP_ENV=local**: Já configurado, informa ao sistema que é ambiente local
- O código agora detecta automaticamente e permite certificados auto-assinados

## ⚠️ IMPORTANTE:

- **NUNCA** use `SSL_VERIFY_DISABLED=true` em produção
- Isso é apenas para desenvolvimento local
- Em produção, sempre use certificados SSL válidos

## 🌐 PARA USAR HTTPS VÁLIDO EM DESENVOLVIMENTO:

Se quiser testar com HTTPS real, use **ngrok**:

```bash
# Instale ngrok: https://ngrok.com/download
ngrok http 8000

# Use a URL HTTPS gerada:
# https://abc123.ngrok.io
```

Depois configure:
```bash
WEBHOOK_PIX_URL=https://abc123.ngrok.io/api/pix/atualizar
```

## 🧪 VERIFICAÇÃO:

Após aplicar as configurações, você deve ver:

```json
{
  "codRetorno": 200,
  "message": "Webhook configurado com sucesso",
  "dados": {
    "ambiente": "local",
    "ssl_verification": "disabled"
  }
}
```

## 📞 SE AINDA TIVER PROBLEMAS:

1. Verifique se o arquivo `.env` foi realmente atualizado
2. Reinicie o servidor Laravel: `php artisan serve`
3. Verifique os logs: `tail -f storage/logs/laravel.log`
4. Use o endpoint de diagnóstico: `GET /api/pix/ssl-status`

## ✅ RESUMO DA SOLUÇÃO:

O erro acontece porque você está tentando conectar com certificados auto-assinados em desenvolvimento. A solução é configurar `SSL_VERIFY_DISABLED=true` no `.env` para ambiente local. O código já foi atualizado para detectar isso automaticamente e desabilitar a verificação SSL apenas em desenvolvimento.
