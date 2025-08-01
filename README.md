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

## ⚡ Início Rápido

### 1. Autenticação
```bash
# Login
POST /api/usuarios/login
{
  "email": "usuario@email.com",
  "senha": "senha123"
}

# Resposta
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "dados": { ... }
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
|--------|----------|-----------|
| `POST` | `/api/usuarios/cadastrar` | Cadastro de usuário |
| `POST` | `/api/usuarios/login` | Autenticação |
| `POST` | `/api/pastas` | Criar pasta |
| `POST` | `/api/imagens/salvar` | Upload de imagem |
| `POST` | `/api/pix/enviar` | Criação de cobrança recorrente PIX |
| `POST` | `/api/vendas/criar-assinatura` | Criação de cobrança via Cartão de Crédito |
| `POST` | `/api/vendas/cancelar-assinatura` | Cancelamento de plano pago via Cartão de Crédito |
| `GET` | `/api/admin/planos/listar` | Listar planos |
| `GET` | `/api/admin/usuarios/listar` | Listar usuários |
| `GET` | `/api/pasta/recuperar?idPasta=123` | Recuperar pasta especifica |


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