# CompPare API - Documentação Completa de Endpoints

## 📋 Sumário

-   [Autenticação](#autenticação)
-   [Usuários](#usuários)
-   [Tags](#tags)
-   [Pastas](#pastas)
-   [Imagens](#imagens)
-   [Planos](#planos)
-   [Vendas/Assinaturas](#vendasassinaturas)
-   [PIX](#pix)
-   [Cupons](#cupons)
-   [Ranking](#ranking)
-   [Questões](#questões)
-   [Admin](#admin)
-   [Convites](#convites)

---

## 🔐 Autenticação

### Base URL

```
Produção: https://api.comppare.com.br/api
Desenvolvimento: http://127.0.0.1:8000/api
```

### Headers Padrão

```
Content-Type: application/json
Authorization: Bearer {JWT_TOKEN}  // Para endpoints protegidos
```

---

## 👤 Usuários

### 1. Cadastrar Usuário

```http
POST /usuarios/cadastrar
```

**Body:**

```json
{
    "primeiroNome": "string (required, max:255)",
    "sobrenome": "string (required, max:255)",
    "apelido": "string (optional, max:255)",
    "cpf": "string (required, 11 chars)",
    "senha": "string (required, min:8, regex: deve conter maiúscula, minúscula, número e símbolo)",
    "telefone": "string (required, max:20)",
    "email": "string (required, email, max:255)",
    "idPlano": "integer (required, exists:planos,id)",
    "nascimento": "string (required, format: dd/mm/yyyy)"
}
```

**Exemplo:**

```json
{
    "primeiroNome": "nome",
    "sobrenome": "sobrenome",
    "apelido": "apelido",
    "cpf": "12345678909",
    "senha": "abc12345678M@",
    "telefone": "61999999999",
    "email": "email@teste.com",
    "idPlano": 1,
    "nascimento": "11/08/2025"
}
```

### 2. Autenticar Usuário (Login)

```http
POST /usuarios/autenticar
```

**Body:**

```json
{
    "cpf": "string (required)",
    "senha": "string (required, min:8)"
}
```

**Response Success:**

```json
{
    "codRetorno": 200,
    "message": "Usuário autenticado com sucesso",
    "token": "jwt_token_here",
    "user": {
        "id": 1,
        "nome": "Luis Pimenta",
        "email": "luispimenta.contato@gmail.com",
        "plano": {
            "id": 1,
            "nome": "Plano Básico"
        }
    },
    "tags": [...],
    "pastas": [...],
    "regras": {
        "pode_criar_nova_pasta": true,
        "pode_criar_subpastas": true
    }
}
```

### 3. Recuperar Dados do Usuário

```http
POST /usuarios/recuperar
Authorization: Bearer {token}
```

**Body:**

```json
{
    "idUsuario": "integer (required, exists:usuarios,id)"
}
```

### 4. Atualizar Dados do Usuário

```http
POST /usuarios/atualizar-dados
Authorization: Bearer {token}
```

**Body:**

```json
{
    "nome": "string (required, max:255)",
    "email": "string (required, email, unique:usuarios,email)",
    "cpf": "string (required, cpf, unique:usuarios,cpf)",
    "telefone": "string (required, 11 chars)",
    "nascimento": "date (required, before:today)",
    "senha": "string (required, min:8)",
    "idUsuario": "integer (required, exists:usuarios,id)"
}
```

### 5. Atualizar Plano do Usuário

```http
POST /usuarios/atualizar-plano
Authorization: Bearer {token}
```

**Body:**

```json
{
    "cpf": "string (required, exists:usuarios,cpf)",
    "plano": "integer (required, exists:planos,id)"
}
```

### 6. Atualizar Senha

```http
POST /usuarios/atualizar-senha
```

**Body:**

```json
{
    "cpf": "string (required, exists:usuarios,cpf)",
    "senha": "string (required, min:8)"
}
```

### 7. Esqueceu Senha

```http
POST /usuarios/esqueceu-senha
```

**Body:**

```json
{
    "email": "string (required, email)"
}
```

`

### 8. Atualizar Status do Usuário

```http
POST /usuarios/atualizar-status
Authorization: Bearer {token}
```

**Body:**

```json
{
    "idUsuario": "integer (required, exists:usuarios,id)",
    "status": "integer (required, 0 ou 1)"
}
```

### 9. Listar Usuários (Admin)

```http
GET /admin/usuarios/listar
```

### 10. Ranking - Classificação

```http
GET /usuarios/ranking/classificacao
Authorization: Bearer {token}
```

---

## 🏷️ Tags

### 1. Cadastrar Tag

```http
POST /tags/cadastrar
Authorization: Bearer {token}
```

**Body:**

```json
{
    "nomeTag": "string (required, max:255)",
    "usuario": "integer (required, exists:usuarios,id)"
}
```

**Response Success:**

```json
{
    "codRetorno": 201,
    "message": "Tag criada com sucesso.",
    "tag": {
        "id": 1,
        "nome": "Família",
        "tipo": "pessoal",
        "criada_em": "2024-01-15 10:30:00"
    },
    "limites": {
        "usado": 5,
        "limite": 10,
        "restante": 5
    }
}
```

### 2. Listar Tags

```http
GET /tags/listar
Authorization: Bearer {token}
```

### 3. Recuperar Tag Específica

```http
POST /tags/recuperar
Authorization: Bearer {token}
```

**Body:**

```json
{
    "idTag": "integer (required)"
}
```

### 4. Atualizar Status da Tag

```http
POST /tags/atualizar-status
Authorization: Bearer {token}
```

**Body:**

```json
{
    "idTag": "integer (required)",
    "status": "integer (required, 0 ou 1)"
}
```

### 5. Atualizar Dados da Tag

```http
POST /tags/atualizar-dados
Authorization: Bearer {token}
```

**Body:**

```json
{
    "nome": "string (required)",
    "descricao": "string (required)",
    "idTag": "integer (required)"
}
```

### 6. Recuperar Tags por Usuário

```http
POST /tags/recuperar-tags-usuario
Authorization: Bearer {token}
```

**Body:**

```json
{
    "usuario": "integer (required)"
}
```

### 7. Excluir Tag

```http
DELETE /tags/excluir
Authorization: Bearer {token}
```

**Body:**

```json
{
    "idTag": "integer (required, exists:tags,id)",
    "usuario": "integer (required, exists:usuarios,id)"
}
```

**Response Success:**

```json
{
    "codRetorno": 200,
    "message": "Tag excluída com sucesso.",
    "tag_excluida": {
        "id": 1,
        "nome": "Família",
        "excluida_em": "2024-01-16 14:20:00"
    },
    "limites": {
        "usado": 4,
        "limite": 10,
        "restante": 6
    }
}
```

---

## 📁 Pastas

### 1. Criar Pasta

```http
POST /pasta/create
Authorization: Bearer {token}
```

**Body:**

```json
{
    "idUsuario": "integer (required, exists:usuarios,id)",
    "nomePasta": "string (required)"
}
```

**Exemplos de estrutura:**

-   Pasta principal: `"nomePasta": "MinhasPastasPrincipais"`
-   Subpasta: `"nomePasta": "PastaPrincipal/Subpasta"`

### 2. Recuperar Pasta

```http
GET /pasta/recuperar?idPasta={id}
Authorization: Bearer {token}
```

**Query Parameters:**

-   `idPasta`: integer (required)

### 3. Associar Tags à Pasta

```http
POST /pasta/associar-tags
Authorization: Bearer {token}
```

**Body:**

```json
{
    "pasta": "integer (required, exists:pastas,id)",
    "tags": ["array of integers (required, exists:tags,id)"]
}
```

**Exemplo:**

```json
{
    "pasta": 1,
    "tags": [1, 2, 3]
}
```

### 4. Excluir Pasta

```http
DELETE /pasta/excluir
Authorization: Bearer {token}
```

**Body:**

```json
{
    "idUsuario": "integer (required)",
    "idPasta": "integer (required)"
}
```

---

## 🖼️ Imagens

### 1. Salvar Imagem na Pasta

```http
POST /imagens/salvar
Authorization: Bearer {token}
```

**Body:**

```json
{
    "idUsuario": "integer (required)",
    "idPasta": "integer (required)",
    "images": "file array (required)"
}
```

### 2. Excluir Imagem da Pasta

```http
DELETE /imagens/excluir
Authorization: Bearer {token}
```

**Body:**

```json
{
    "idUsuario": "integer (required)",
    "idPhoto": "integer (required)"
}
```

---

## 💳 Planos

### 1. Listar Planos

```http
GET /planos/listar
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "Success",
    "totalPlanos": 5,
    "planosAtivos": 3,
    "data": [
        {
            "id": 1,
            "nome": "Plano Básico",
            "descricao": "Plano para iniciantes",
            "valor": 29.9,
            "quantidadeTags": 10,
            "quantidadePastas": 5
        }
    ]
}
```

### 2. Cadastrar Plano (Admin)

```http
POST /admin/planos/cadastrar
```

**Body:**

```json
{
    "nome": "string (required)",
    "descricao": "string (required)",
    "valor": "numeric (required)",
    "quantidadeTags": "integer (required)",
    "quantidadePastas": "integer (required)",
    "online": "boolean (required)",
    "frequenciaCobranca": "integer (required)"
}
```

### 3. Recuperar Plano (Admin)

```http
GET /admin/planos/recuperar/{id}
```

### 4. Atualizar Status do Plano (Admin)

```http
POST /admin/planos/atualizar-status
```

**Body:**

```json
{
    "idPlano": "integer (required)",
    "status": "integer (required, 0 ou 1)"
}
```

### 5. Atualizar Dados do Plano (Admin)

```http
POST /admin/planos/atualizar-dados
```

**Body:**

```json
{
    "nome": "string (required)",
    "descricao": "string (required)",
    "valor": "numeric (required)",
    "quantidadeTags": "integer (required)",
    "idPlano": "integer (required)"
}
```

### 6. Atualizar Funcionalidades do Plano (Admin)

```http
POST /admin/planos/atualizar-funcionalidades
```

**Body:**

```json
{
    "idPlano": "integer (required)",
    "funcionalidades": "array (required)"
}
```

---

## 💰 Vendas/Assinaturas

### 1. Criar Assinatura

```http
POST /vendas/criar-assinatura
Authorization: Bearer {token}
```

**Body:**

```json
{
    "usuario": "integer (required)",
    "plano": "integer (required)",
    "token": "string (required)"
}
```

### 2. Cancelar Assinatura (Admin)

```http
POST /admin/vendas/cancelar-assinatura
```

**Body:**

```json
{
    "usuario": "integer (required)"
}
```

---

## 💎 PIX

### 1. Criar Cobrança PIX Recorrente

```http
POST /pix/enviar
```

**Body:**

```json
{
    "usuario": "integer (required)",
    "plano": "integer (required)"
}
```

## 🎫 Cupons (Admin)

### 1. Cadastrar Cupom

```http
POST /admin/cupons/cadastrar
```

**Body:**

```json
{
    "cupom": "string (required)",
    "percentualDesconto": "numeric (required)",
    "quantidadeDias": "integer (required)"
}
```

### 2. Listar Cupons

```http
GET /admin/cupons/listar
```

### 3. Recuperar Cupom

```http
POST /admin/cupons/recuperar
```

**Body:**

```json
{
    "idCupom": "integer (required)"
}
```

### 4. Atualizar Status do Cupom

```http
POST /admin/cupons/atualizar-status
```

**Body:**

```json
{
    "idCupom": "integer (required)",
    "status": "integer (required, 0 ou 1)"
}
```

### 5. Atualizar Dados do Cupom

```http
POST /admin/cupons/atualizar-dados
```

### 6. Verificar Status do Cupom

```http
POST /admin/cupons/verificar-status
```

---

## 🏆 Ranking (Admin)

### 1. Atualizar Pontos

```http
POST /admin/ranking/atualizar
```

**Body:**

```json
{
    "pontos": "integer (required)",
    "usuario": "integer (required)"
}
```

---

## ❓ Questões (Admin)

### 1. Listar Questões

```http
GET /questoes/listar
```

### 2. Salvar Questão

```http
POST /admin/api/questoes/salvar
```

---

## 🔧 Admin (Outros)

### 1. Notificação de Pagamento

```http
POST /admin/api/notification
```

### 2. Salvar Token de Assinatura

```http
POST /admin/api/token/salvar
```

---

## 🔍 Endpoint de Teste

### 1. Teste da API

```http
GET /test
```

**Response:**

```json
{
    "message": "API funcional na versão: {APP_VERSION}"
}
```

---

## 📝 Códigos de Resposta HTTP

| Código | Descrição                                    |
| ------ | -------------------------------------------- |
| 200    | OK - Sucesso                                 |
| 201    | Created - Criado com sucesso                 |
| 400    | Bad Request - Dados inválidos                |
| 401    | Unauthorized - Não autorizado                |
| 403    | Forbidden - Proibido                         |
| 404    | Not Found - Não encontrado                   |
| 409    | Conflict - Conflito (ex: registro duplicado) |
| 500    | Internal Server Error - Erro interno         |

---

## 🛡️ Middleware de Autenticação

A maioria dos endpoints requer autenticação via JWT Token:

```http
Authorization: Bearer {JWT_TOKEN}
```

O token é obtido através do endpoint `/usuarios/autenticar` e tem validade limitada.

---

## 📤 Exemplos de Response Padrão

### Sucesso:

```json
{
    "codRetorno": 200,
    "message": "Operação realizada com sucesso",
    "data": {...}
}
```

### Erro de Validação:

```json
{
    "codRetorno": 400,
    "message": "Campos obrigatórios não informados",
    "campos": ["campo1", "campo2"]
}
```

### Erro de Autenticação:

```json
{
    "codRetorno": 401,
    "message": "Token inválido ou expirado"
}
```

---

## ✉️ Convites

### 1. Cadastrar Convite

```http
POST /convite/cadastrar
Authorization: Bearer {token}
```

**Body:**

```json
{
    "email": "string (required, email)",
    "usuario": "integer (required, exists:usuarios,id)",
    "pasta": "integer (required, exists:pastas,id)"
}
```

**Response Success:**

```json
{
    "codRetorno": 200,
    "message": "Compartilhamento de pasta criado com sucesso."
}
```

---

### 2. Vincular Convite ao Usuário

```http
POST /convite/vincular
Authorization: Bearer {token}
```

**Body:**

```json
{
    "email": "string (required, email)"
}
```

**Response Success:**

```json
{
    "codRetorno": 200,
    "message": "Convites processados e pastas vinculadas.",
    "pastas_vinculadas": [1,2,3]
}
```

---

### 3. Excluir Convite

```http
POST /convite/excluir
Authorization: Bearer {token}
```

**Body:**

```json
{
    "idPasta": "integer (required, exists:convites,idPasta)"
}
```

**Response Success:**

```json
{
    "codRetorno": 200,
    "message": "Convite excluído com sucesso."
}
```

---
