# Teste de Webhook PIX com Certificados SSL

## 🔐 Certificados Criados

Os certificados SSL foram criados com sucesso em `storage/app/certificates/`:

- **server.pem** - Certificado do servidor (para API)
- **cliente-efi.pem** - Certificado cliente EFI (para webhook)
- **cliente-efi.key** - Chave privada do cliente EFI
- **ca.crt** - Certificado da Autoridade Certificadora

## 🧪 Como Testar

### 1. Testar status SSL
```bash
GET http://127.0.0.1:8000/api/pix/ssl-status
```

### 2. Configurar webhook PIX
```bash
PUT http://127.0.0.1:8000/api/pix/webhook
Content-Type: application/json

{
    "webhookUrl": "https://api.comppare.com.br/api/pix/atualizar"
}
```

### 3. Testar servidor HTTPS com TLS mútuo
```bash
# Terminal 1 - Iniciar servidor
php serve-https-tls.php

# Terminal 2 - Testar com certificado
curl -X POST https://127.0.0.1:8443/api/pix/atualizar \
     --cert storage/app/certificates/cliente-efi.pem \
     --key storage/app/certificates/cliente-efi.key \
     --cacert storage/app/certificates/ca.crt \
     -H 'Content-Type: application/json' \
     -d '{"recs":[{"idRec":"test","status":"APROVADA"}]}' \
     -k
```

## ✅ O que foi simplificado

- Removido lógicas de verificação SSL desnecessárias
- Simplificado o middleware TLS mútuo
- Configurado certificados SSL automaticamente
- Removido arquivos de debug e troubleshooting
- Limpeza do PixController com foco apenas no essencial

## 📝 Configuração no .env

```env
# Certificados SSL configurados automaticamente
WEBHOOK_PIX_URL=https://api.comppare.com.br/api/pix/atualizar
EFI_CERTIFICADO_HML=/path/to/server.pem
EFI_CERTIFICADO_PRD=/path/to/server.pem
```

Os certificados estão prontos para uso tanto em desenvolvimento quanto em produção!
