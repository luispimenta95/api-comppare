# CompPare API Documentation

## 📚 Documentação Interativa

Acesse a documentação completa da API CompPare através dos links abaixo:

### 🔗 Links Principais

-   **[📖 Swagger UI - Documentação Interativa](https://petstore.swagger.io/?url=https://raw.githubusercontent.com/pimentaLuiz/api-comppare/main/swagger.yaml)**
-   **[📄 Redoc - Documentação Redoc](https://redocly.github.io/redoc/?url=https://raw.githubusercontent.com/pimentaLuiz/api-comppare/main/swagger.yaml)**
-   **[📁 Arquivo YAML Original](https://raw.githubusercontent.com/pimentaLuiz/api-comppare/main/swagger.yaml)**

### 🚀 Como Usar

1. **Swagger UI**: Interface interativa onde você pode testar os endpoints diretamente
2. **Redoc**: Documentação limpa e organizada para leitura
3. **YAML**: Arquivo de especificação OpenAPI 3.0 original

### 📝 Testes da API

-   **[🔥 Coleção HTTP](https://github.com/pimentaLuiz/api-comppare/blob/main/app/Http/Util/requests.http)** - Para uso com REST Client
-   **[🌐 Interface de Teste](https://api.comppare.com.br/test-auth.php)** - Página visual de autenticação

### 🏠 Repositório Principal

**[🔙 Voltar ao Repositório](https://github.com/pimentaLuiz/api-comppare)**

---

## Principais Endpoints

### Autenticação e Usuários

-   `POST /usuarios/autenticar` - Login
-   `POST /usuarios/cadastrar` - Cadastro de usuário
-   `POST /usuarios/recuperar` - Recuperar dados do usuário
-   `POST /usuarios/atualizar-status` - Atualizar status do usuário
-   `POST /usuarios/valida-existencia-usuario` - Validar existência de usuário
-   `POST /usuarios/atualizar-senha` - Atualizar senha
-   `POST /usuarios/esqueceu-senha` - Recuperação de senha
-   `POST /usuarios/atualizar-plano` - Atualizar plano do usuário (JWT)
-   `POST /usuarios/atualizar-dados` - Atualizar dados do usuário (JWT)
-   `GET /usuarios/ranking/classificacao` - Ranking de usuários (JWT)
-   `GET /usuarios/pastas/{id}` - Pastas estruturadas do usuário (JWT)

### Pastas

-   `POST /pasta/create` - Criar pasta (JWT)
-   `POST /pasta/atualizar` - Editar pasta (JWT)
-   `GET /pasta/recuperar` - Recuperar pasta (JWT)
-   `POST /pasta/associar-tags` - Associar tags à pasta (JWT)
-   `DELETE /pasta/excluir` - Excluir pasta (JWT)
-   `POST /pasta/remover-tags` - Remover tags da pasta (JWT)
-   `GET /pasta/recuperar-imagens` - Recuperar imagens de subpasta (JWT)

### Imagens

-   `POST /imagens/salvar` - Salvar imagem em pasta (JWT)
-   `DELETE /imagens/excluir` - Excluir imagem de pasta (JWT)

### Tags

-   `POST /tags/cadastrar` - Cadastrar tag (JWT)
-   `GET /tags/listar` - Listar tags (JWT)
-   `POST /tags/recuperar` - Recuperar tag (JWT)
-   `POST /tags/atualizar-status` - Atualizar status da tag (JWT)
-   `POST /tags/atualizar-dados` - Atualizar dados da tag (JWT)
-   `POST /tags/recuperar-tags-usuario` - Listar tags do usuário (JWT)
-   `DELETE /tags/excluir` - Excluir tag (JWT)

### PIX e Pagamentos

-   `POST /pix/enviar` - Criar cobrança PIX
-   `GET /pix/cadastrar` - Registrar webhook PIX
-   `GET /pix/ver-webhook` - Consultar webhook PIX
-   `POST /pix/atualizar` - Atualizar cobrança PIX

### Comparação de Imagens

-   `POST /comparacao/salvar` - Salvar comparação de imagem (JWT)
-   `GET /comparacao/{id}` - Recuperar comparação de imagem (JWT)

### Planos

-   `GET /planos/listar` - Listar planos

### Vendas

-   `POST /vendas/criar-assinatura` - Criar assinatura (JWT)
-   `POST /vendas/cancelar-assinatura` - Cancelar assinatura (JWT)

### Admin

-   `POST /admin/planos/cadastrar` - Cadastrar plano
-   `GET /admin/planos/listar` - Listar planos
-   `GET /admin/usuarios/listar` - Listar usuários
-   `GET /admin/planos/recuperar/{id}` - Recuperar plano
-   `POST /admin/planos/atualizar-status` - Atualizar status do plano
-   `POST /admin/planos/atualizar-dados` - Atualizar dados do plano
-   `POST /admin/planos/atualizar-funcionalidades` - Adicionar funcionalidades ao plano
-   `POST /admin/cupons/cadastrar` - Cadastrar cupom
-   `GET /admin/cupons/listar` - Listar cupons
-   `POST /admin/cupons/recuperar` - Recuperar cupom
-   `POST /admin/cupons/atualizar-status` - Atualizar status do cupom
-   `POST /admin/cupons/atualizar-dados` - Atualizar dados do cupom
-   `POST /admin/cupons/verificar-status` - Verificar status do cupom
-   `POST /admin/vendas/cancelar-assinatura` - Cancelar assinatura
-   `POST /admin/ranking/atualizar` - Atualizar pontos do ranking
-   `POST /admin/api/notification` - Atualizar pagamento
-   `POST /admin/api/token/salvar` - Salvar token de assinatura
-   `POST /admin/api/questoes/salvar` - Salvar questão

💡 **Dica**: Use os links do Swagger UI para testar a API diretamente no navegador!
