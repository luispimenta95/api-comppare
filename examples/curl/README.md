# Exemplos cURL para API CompPare

## 📋 Descrição

Coleção de comandos cURL para testar todos os endpoints da API CompPare.

## 🚀 Uso Rápido

### Script Automatizado
```bash
chmod +x test_api.sh
./test_api.sh
```

### Comandos Individuais

#### 1. Teste de Conectividade
```bash
curl -X GET "https://api.comppare.com.br/api/test"
```

#### 2. Login
```bash
curl -X POST "https://api.comppare.com.br/api/usuarios/autenticar" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "usuario@email.com",
    "senha": "senha123"
  }'
```

#### 3. Listar Pastas (com token)
```bash
curl -X GET "https://api.comppare.com.br/api/pastas" \
  -H "Authorization: Bearer SEU_TOKEN_JWT"
```

#### 4. Criar Pasta
```bash
curl -X POST "https://api.comppare.com.br/api/pastas" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer SEU_TOKEN_JWT" \
  -d '{
    "nomePasta": "Minha Nova Pasta"
  }'
```

#### 5. Upload de Imagem
```bash
curl -X POST "https://api.comppare.com.br/api/photos/upload" \
  -H "Authorization: Bearer SEU_TOKEN_JWT" \
  -F "idPasta=1" \
  -F "image=@caminho/para/imagem.jpg"
```

#### 6. Dados do Usuário
```bash
curl -X GET "https://api.comppare.com.br/api/usuarios/dados" \
  -H "Authorization: Bearer SEU_TOKEN_JWT"
```

#### 7. Listar Planos
```bash
curl -X GET "https://api.comppare.com.br/api/planos"
```

## 🔧 Configuração

### URLs
- **Produção**: `https://api.comppare.com.br/api`
- **Desenvolvimento**: `http://127.0.0.1:8000/api`

### Headers Obrigatórios
- `Content-Type: application/json` (para requisições JSON)
- `Authorization: Bearer TOKEN` (para endpoints protegidos)

## 📝 Fluxo Completo

```bash
# 1. Fazer login e capturar token
TOKEN=$(curl -s -X POST "https://api.comppare.com.br/api/usuarios/autenticar" \
  -H "Content-Type: application/json" \
  -d '{"email":"usuario@email.com","senha":"senha123"}' | \
  jq -r '.token')

# 2. Usar token nas próximas requisições
curl -X GET "https://api.comppare.com.br/api/pastas" \
  -H "Authorization: Bearer $TOKEN"
```

## 🧪 Testes com jq

Para formatar a saída JSON, use `jq`:

```bash
curl -s -X GET "https://api.comppare.com.br/api/test" | jq '.'
```

## 📚 Endpoints Principais

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/test` | Teste de conectividade |
| POST | `/usuarios/cadastrar` | Cadastro de usuário |
| POST | `/usuarios/autenticar` | Login |
| GET | `/usuarios/dados` | Dados do usuário |
| GET | `/pastas` | Listar pastas |
| POST | `/pastas` | Criar pasta |
| POST | `/photos/upload` | Upload de imagem |
| GET | `/planos` | Listar planos |

## ⚠️ Notas Importantes

1. **Token JWT**: Expira em 60 minutos por padrão
2. **Uploads**: Use `-F` para multipart/form-data
3. **JSON**: Use `-d` com Content-Type application/json
4. **HTTPS**: Certificado válido em produção

## 🔍 Debug

Para ver headers da resposta:
```bash
curl -v -X GET "https://api.comppare.com.br/api/test"
```

Para salvar resposta em arquivo:
```bash
curl -X GET "https://api.comppare.com.br/api/test" -o resposta.json
```
