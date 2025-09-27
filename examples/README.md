# Exemplos de Consumo da API CompPare

Este diretório contém exemplos práticos de como consumir a API CompPare em diferentes linguagens de programação.

## 📁 Estrutura

- `php/` - Exemplos em PHP
- `javascript/` - Exemplos em JavaScript/Node.js
- `python/` - Exemplos em Python
- `curl/` - Exemplos usando cURL

## 🚀 Início Rápido

### 1. Autenticação
Todos os exemplos seguem o mesmo padrão:
1. Fazer login para obter o token JWT
2. Usar o token nas requisições subsequentes

### 2. Fluxo Básico
```
Login → Listar Pastas → Criar Pasta → Upload de Imagem
```

### 3. URL Base
- **Produção**: `https://api.comppare.com.br/api`
- **Desenvolvimento**: `http://127.0.0.1:8000/api`

## 📋 Requisitos

Antes de usar os exemplos, certifique-se de ter:
- Credenciais válidas (email/CPF e senha)
- Token JWT ativo
- Permissões adequadas no plano do usuário

## 📚 Documentação

Para detalhes completos da API, consulte:
- [Documentação Swagger](https://petstore.swagger.io/?url=https://raw.githubusercontent.com/pimentaLuiz/api-comppare/main/swagger.yaml)
- [Arquivo requests.http](../app/Http/Util/requests.http)
