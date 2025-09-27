# CompPare API Documentation

## 📚 Documentação Interativa

Acesse a documentação completa da API CompPare através dos links abaixo:

### 🔗 Links Principais

- **[📖 Swagger UI - Documentação Interativa](https://petstore.swagger.io/?url=https://raw.githubusercontent.com/pimentaLuiz/api-comppare/main/swagger.yaml)**
- **[📄 Redoc - Documentação Redoc](https://redocly.github.io/redoc/?url=https://raw.githubusercontent.com/pimentaLuiz/api-comppare/main/swagger.yaml)**
- **[📁 Arquivo YAML Original](https://raw.githubusercontent.com/pimentaLuiz/api-comppare/main/swagger.yaml)**

### 🚀 Como Usar

1. **Swagger UI**: Interface interativa onde você pode testar os endpoints diretamente
2. **Redoc**: Documentação limpa e organizada para leitura
3. **YAML**: Arquivo de especificação OpenAPI 3.0 original

### 📝 Testes da API

- **[🔥 Coleção HTTP](https://github.com/pimentaLuiz/api-comppare/blob/main/app/Http/Util/requests.http)** - Para uso com REST Client
- **[🌐 Interface de Teste](https://api.comppare.com.br/test-auth.php)** - Página visual de autenticação

### 🏠 Repositório Principal

**[🔙 Voltar ao Repositório](https://github.com/pimentaLuiz/api-comppare)**

---

## Principais Endpoints

### Autenticação
- `POST /usuarios/cadastrar` - Cadastro de usuário
- `POST /usuarios/autenticar` - Login
- `GET /usuarios/dados` - Dados do usuário

### Pastas e Arquivos
- `GET /pastas` - Listar pastas
- `POST /pastas` - Criar pasta
- `POST /photos/upload` - Upload de imagens

### Planos e Pagamentos
- `GET /planos` - Listar planos
- `POST /cupons/aplicar` - Aplicar cupom
- `POST /pagamentos/processar` - Processar pagamento

---

💡 **Dica**: Use os links do Swagger UI para testar a API diretamente no navegador!
