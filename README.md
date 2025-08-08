# Comppare API

## 📋 Visão Geral

Comppare é uma API completa para gerenciamento de usuários, pastas, imagens, planos e pagamentos. Desenvolvida com Laravel 11, oferece autenticação JWT, upload de imagens, controle de planos e integração com gateways de pagamento.

## 📚 Documentação da API

### 🔗 Visualizar Documentação Swagger

[![Swagger UI](https://img.shields.io/badge/Swagger%20UI-View%20API%20Docs-brightgreen?style=for-the-badge&logo=swagger)](https://petstore.swagger.io/?url=https://raw.githubusercontent.com/pimentaLuiz/api-comppare/main/swagger.yaml)

- **[📖 Documentação Interativa (Swagger UI)](https://petstore.swagger.io/?url=https://raw.githubusercontent.com/pimentaLuiz/api-comppare/main/swagger.yaml)**
- **[📄 Documentação Redoc](https://redocly.github.io/redoc/?url=https://raw.githubusercontent.com/pimentaLuiz/api-comppare/main/swagger.yaml)**
- **[🌐 GitHub Pages (Auto-Deploy)](https://pimentaLuiz.github.io/api-comppare/)**
- **[📁 Arquivo Swagger YAML](./swagger.yaml)**

### 🧪 Testes da API

- **[🔥 Coleção de Requests HTTP](./app/Http/Util/requests.http)** - Para testes com extensões como REST Client
- **[🌐 Página de Teste PHP](./public/test-auth.php)** - Interface visual para autenticação e navegação

## 🚀 Principais Funcionalidades

- **👥 Gerenciamento de Usuários**: Cadastro, autenticação JWT, perfis
- **📁 Sistema de Pastas**: Criação hierárquica com limite por plano
- **🖼️ Upload de Imagens**: Gestão completa de fotos por pasta
- **💼 Planos de Assinatura**: Controle de recursos e limitações
- **🎫 Sistema de Cupons**: Descontos e promoções
- **💳 Processamento de Pagamentos**: Integração com gateways
- **🏷️ Sistema de Tags**: Tags pessoais e globais para organização

## ⚡ Início Rápido

### 1. Autenticação
```bash
# Login
POST /api/usuarios/autenticar
{
  "cpf": "02049035055",
  "senha": "senha123"
}

# Resposta (inclui tags do usuário)
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "dados": { ... },
  "pastas": [...],
  "tags": {
    "total": 15,
    "pessoais": 10,
    "globais": 5,
    "lista": [
      {
        "id": 1,
        "nome": "Família",
        "tipo": "pessoal",
        "criada_em": "2024-01-15 10:30:00"
      }
    ]
  },
  "regras": { ... }
}
```



### 2. Upload de Imagem
```bash
# Upload de foto para uma pasta
POST /api/imagens/salvar
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "idPasta": 1,
  "image": [arquivo]
}
```

## 🛠️ Instalação e Configuração

### Pré-requisitos
- PHP 8.2+
- Composer
- MySQL/PostgreSQL
- Node.js (para assets front-end)

### 1. Clone e Instale Dependências
```bash
git clone https://github.com/pimentaLuiz/api-comppare.git
cd api-comppare
composer install
npm install
```

### 2. Configuração do Ambiente
```bash
# Copie o arquivo de ambiente
cp .env.example .env

# Gere a chave da aplicação
php artisan key:generate

# Configure o JWT
php artisan jwt:secret
```

### 3. Configuração do Banco de Dados
```bash
# Configure suas credenciais no .env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=api_comppare
DB_USERNAME=seu_usuario
DB_PASSWORD=sua_senha

# Execute as migrações
php artisan migrate

# Execute os seeders (opcional)
php artisan db:seed
```

### 4. Inicie o Servidor
```bash
# Desenvolvimento
php artisan serve

# Build dos assets
npm run dev
```

## 📂 Estrutura da API

### Endpoints Principais

| Método | Endpoint | Descrição |
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `POST` | `/api/usuarios/cadastrar` | Cadastro de usuário |
| `POST` | `/api/usuarios/autenticar` | Autenticação (inclui tags) |
| `GET` | `/api/usuarios/dados` | Dados do usuário autenticado |
| `GET` | `/api/pastas` | Listar pastas |
| `POST` | `/api/pastas` | Criar pasta |
| `POST` | `/api/photos/upload` | Upload de imagem |
| `GET` | `/api/planos` | Listar planos |
| `POST` | `/api/cupons/aplicar` | Aplicar cupom |
| `POST` | `/api/pix/enviar` | Criar cobrança PIX recorrente |
| `POST` | `/api/pix/atualizar` | Atualizar status cobrança (webhook) |
| `PUT` | `/api/pix/webhook` | Configurar webhook de notificações |

## 💳 Sistema de Pagamentos PIX

### Configuração de Webhook
Configure a URL que receberá notificações sobre mudanças de status:

⚠️ **IMPORTANTE**: A URL do webhook deve ter:
- HTTPS obrigatoriamente
- Autenticação TLS mútuo configurada
- Certificado SSL válido e acessível externamente

📁 **Certificados Necessários**:
```bash
# Estrutura de certificados no storage/app/certificates/
storage/
  app/
    certificates/
      # Certificados principais EFI
      hml.pem          # Certificado homologação
      prd.pem          # Certificado produção
      
      # Certificados TLS mútuo para webhook
      cliente.pem      # Certificado cliente (homologação)
      cliente.key      # Chave privada cliente (homologação)
      cliente_prd.pem  # Certificado cliente (produção)
      cliente_prd.key  # Chave privada cliente (produção)
```

```bash
# Configure no .env
WEBHOOK_PIX_URL=https://seu-dominio-com-tls-mutuo.com/api/pix/atualizar

# Ou envie na requisição
PUT /api/pix/webhook
{
  "webhookUrl": "https://seu-dominio.com/api/webhookcobr/"
}

# Response de sucesso
{
  "codRetorno": 200,
  "message": "Webhook configurado com sucesso",
  "data": {
    "webhookUrl": "https://seu-dominio.com/api/webhookcobr/",
    "configurado_em": "2024-08-08 15:30:00",
    "observacao": "Webhook configurado com autenticação TLS mútuo"
  }
}

# Response de erro (TLS não configurado)
{
  "codRetorno": 500,
  "message": "Erro ao configurar webhook",
  "error": "Autenticação TLS mútuo não está configurada na URL informada",
  "sugestoes": [
    "Verifique se a URL possui certificado SSL válido",
    "Confirme se a autenticação TLS mútuo está configurada",
    "Consulte a documentação da EFI sobre configuração de webhooks"
  ]
}
```

### Criação de Cobrança PIX
A API oferece integração completa com PIX recorrente da EFI:

```bash
POST /api/pix/enviar
{
  "usuario": 2,
  "plano": 3
}

# Response
{
  "codRetorno": 200,
  "message": "Cobrança PIX criada com sucesso",
  "data": {
    "pix": "00020101021226580014br.gov.bcb.pix..."
  }
}
```

### Webhook de Atualização
Endpoint para receber notificações da EFI sobre mudanças de status:

```bash
POST /api/pix/atualizar
{
  "recs": [
    {
      "idRec": "RR1026652320240821lab77511abf",
      "status": "APROVADA"
    }
  ]
}

# Response
{
  "codRetorno": 200,
  "message": "Atualização de cobranças processada",
  "total_processados": 1,
  "resultados": [
    {
      "idRec": "RR1026652320240821lab77511abf",
      "status": "APROVADA",
      "status_anterior": "ATIVA",
      "atualizado": true
    }
  ]
}
```

## 🏷️ Sistema de Tags

### Tags no Login
Ao realizar autenticação, o usuário recebe automaticamente suas tags:

```json
{
  "tags": {
    "total": 15,
    "pessoais": 10,
    "globais": 5,
    "lista": [
      {
        "id": 1,
        "nome": "Família",
        "tipo": "pessoal",
        "criada_em": "2024-01-15 10:30:00"
      },
      {
        "id": 2,
        "nome": "Trabalho",
        "tipo": "global",
        "criada_em": "2024-01-10 09:00:00"
      }
    ]
  }
}
```

### Tipos de Tags
- **Tags Pessoais**: Criadas pelo próprio usuário
- **Tags Globais**: Criadas por administradores, disponíveis para todos

### Endpoints de Tags
| Método | Endpoint | Descrição |
|--------|----------|-----------|
| `GET` | `/api/tags/usuario?usuario={id}` | Lista tags do usuário |
| `POST` | `/api/tags/cadastrar` | Cria nova tag (com validação de limite) |
| `DELETE` | `/api/tags/excluir` | Exclui tag pessoal (apenas criador) |
| `PUT` | `/api/tags/atualizar-status` | Atualiza status da tag |

### Validações e Limites
- **Limite por Plano**: Cada plano possui um limite de tags pessoais
- **Validação de Duplicatas**: Não permite tags com nomes iguais para o mesmo usuário
- **Controle de Status**: Apenas tags ativas são consideradas no limite
- **Exclusão Segura**: Apenas o criador pode excluir tags pessoais
- **Soft Delete**: Tags excluídas são mantidas no banco com status inativo
- **Decremento Automático**: Contador de tags é atualizado automaticamente na exclusão
- **Mensagens Detalhadas**: Retorna informações específicas sobre limites e sugestões

### Exemplo de Criação com Limite
```bash
POST /api/tags/cadastrar
{
  "nomeTag": "Família",
  "usuario": 1
}

# Sucesso (201)
{
  "message": "Tag criada com sucesso.",
  "tag": { ... },
  "limites": {
    "usado": 5,
    "limite": 10,
    "restante": 5
  }
}

# Erro - Limite atingido (403)
{
  "message": "Limite de tags do plano atingido.",
  "detalhes": {
    "limite_plano": 10,
    "tags_criadas": 10,
    "plano_atual": "Plano Básico",
    "sugestao": "Faça upgrade do seu plano para criar mais tags."
  }
}
```

### Exemplo de Exclusão
```bash
DELETE /api/tags/excluir
{
  "idTag": 1,
  "usuario": 1
}

# Sucesso (200)
{
  "message": "Tag excluída com sucesso.",
  "tag_excluida": {
    "id": 1,
    "nome": "Família",
    "criada_em": "2024-01-15 10:30:00",
    "excluida_em": "2024-01-16 14:20:00"
  },
  "limites_atualizados": {
    "tags_antes": 5,
    "tags_depois": 4,
    "limite_plano": 10,
    "disponivel_criar": 6
  }
}

# Erro - Não é o criador (403)
{
  "message": "Você só pode excluir suas próprias tags.",
  "detalhes": {
    "criador_tag": 2,
    "usuario_solicitante": 1
  }
}
```


### Autenticação
Todos os endpoints protegidos requerem o header:
```
Authorization: Bearer {jwt_token}
```

## 🔧 Configurações Importantes

### JWT Token
Configure no `.env`:
```env
JWT_SECRET=seu_jwt_secret_aqui
JWT_TTL=60 # Tempo de vida em minutos
```

### Upload de Arquivos
```env
FILESYSTEM_DISK=public
# ou para S3:
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=sua_chave
AWS_SECRET_ACCESS_KEY=sua_chave_secreta
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=seu_bucket
```

### Gateways de Pagamento
```env
# EFI Pay (antigo Gerencianet)
EFI_CLIENT_ID=seu_client_id
EFI_CLIENT_SECRET=seu_client_secret
EFI_SANDBOX=true # false para produção
```

## 📋 Testes

### Executar Testes
```bash
# Todos os testes
php artisan test

# Testes específicos
php artisan test --filter UsuarioTest
```

### Testes Manuais
1. **REST Client**: Use o arquivo `requests.http` com extensões como REST Client (VS Code)
2. **Interface Web**: Acesse `public/test-auth.php` para testes visuais
3. **Swagger UI**: Use a documentação interativa para testes online

## 🤝 Contribuição

1. Fork o projeto
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 📄 Licença

Este projeto está sob a licença MIT. Veja o arquivo [LICENSE](LICENSE) para mais detalhes.

## 📞 Suporte

- **Email**: luisfelipearaujopimenta@gmail.com