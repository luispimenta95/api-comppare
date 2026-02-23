# CompPare API Endpoints

## Autenticação e Usuários

### POST /usuarios/autenticar

**Body:**

```json
{
    "email": "user@email.com",
    "senha": "minhasenha"
}
```

**Response:**

```json
{
	"codRetorno": 200,
	"message": "Login realizado com sucesso",
	"token": "...jwt...",
	"usuario": { ... }
}
```

### POST /usuarios/cadastrar

**Body:**

```json
{
    "nome": "Nome Completo",
    "email": "user@email.com",
    "senha": "minhasenha"
}
```

**Response:**

```json
{
	"codRetorno": 201,
	"message": "Usuário cadastrado com sucesso",
	"usuario": { ... }
}
```

### POST /usuarios/recuperar

**Body:**

```json
{
    "idUsuario": 1
}
```

**Response:**

```json
{
	"codRetorno": 200,
	"usuario": { ... }
}
```

### POST /usuarios/atualizar-status

**Body:**

```json
{
    "idUsuario": 1,
    "status": 1
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "Status atualizado"
}
```

### POST /usuarios/valida-existencia-usuario

**Body:**

```json
{
    "email": "user@email.com"
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "existe": true
}
```

### POST /usuarios/atualizar-senha

**Body:**

```json
{
    "idUsuario": 1,
    "senha_atual": "oldpass",
    "nova_senha": "newpass"
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "Senha atualizada"
}
```

### POST /usuarios/esqueceu-senha

**Body:**

```json
{
    "email": "user@email.com"
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "Email de recuperação enviado"
}
```

### POST /usuarios/atualizar-plano

**Body:**

```json
{
    "idUsuario": 1,
    "idPlano": 2
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "Plano atualizado"
}
```

### POST /usuarios/atualizar-dados

**Body:**

```json
{
    "idUsuario": 1,
    "nome": "Novo Nome"
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "Dados atualizados"
}
```

### GET /usuarios/ranking/classificacao

**Response:**

```json
{
	"codRetorno": 200,
	"ranking": [ ... ]
}
```

### GET /usuarios/pastas/{id}

**Response:**

```json
{
	"codRetorno": 200,
	"pastas": [ ... ]
}
```

## Pastas

### POST /pasta/create

**Body:**

```json
{
    "idUsuario": 1,
    "nomePasta": "Viagem2024" // ou "Viagem2024/Praia" para subpasta
}
```

**Response (sucesso):**

```json
{
    "codRetorno": 201,
    "message": "Pasta criada com sucesso!",
    "pasta_id": 15,
    "pasta_nome": "Viagem2024",
    "pasta_caminho": ".../Viagem2024",
    "tipo": "pasta_principal",
    "estrutura_completa": "Viagem2024"
}
```

**Response (erro - limite):**

```json
{
    "codRetorno": 400,
    "message": "Limite de pastas principais atingido para este mês",
    "limite_atingido": true,
    "detalhes": {
        "tipo": "pasta_principal",
        "criadas_no_mes": 5,
        "limite_plano": 5,
        "restantes": 0
    }
}
```

### POST /pasta/atualizar

**Body:**

```json
{
    "idUsuario": 1,
    "idPasta": 15,
    "novoNome": "Viagem2025",
    "tags": [1, 2, 3]
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "Nome da pasta atualizado com sucesso!",
    "pasta_id": 15,
    "novo_nome": "Viagem2025",
    "novo_caminho": ".../Viagem2025"
}
```

### GET /pasta/recuperar

**Body:**

```json
{
    "idPasta": 15
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "OK",
    "data": {
        "id": 15,
        "nome": "Viagem2024",
        "path": ".../Viagem2024",
        "subpastas": [
            {
                "id": 16,
                "nome": "Praia",
                "path": ".../Viagem2024/Praia",
                "imagens": [{ "id": 101, "path": ".../img1.jpg" }],
                "tags": [{ "id": 1, "nome": "Tag1" }]
            }
        ]
    }
}
```

### POST /pasta/associar-tags

**Body:**

```json
{
    "pasta": 15,
    "tags": [1, 2, 3]
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "Tags associadas com sucesso!",
    "id_pasta": 15,
    "tags": [1, 2, 3]
}
```

### DELETE /pasta/excluir

**Body:**

```json
{
    "idUsuario": 1,
    "idPasta": 15
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "Pasta principal 'Viagem2024' excluída com sucesso!",
    "detalhes": {
        "pasta_excluida": "Viagem2024",
        "tipo": "pasta_principal",
        "fotos_removidas": 10,
        "pastas_principais_restantes": 4,
        "subpastas_excluidas": 2,
        "observacao": "REGRA 1 APLICADA: Pasta principal excluída junto com 2 subpasta(s)"
    }
}
```

### POST /pasta/remover-tags

**Body:**

```json
{
    "folder_id": 15,
    "tag_id": 1
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "Tag removida da pasta com sucesso!",
    "folder_id": 15,
    "tag_id": 1
}
```

### GET /pasta/recuperar-imagens

**Body:**

```json
{
    "idPasta": 16
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "Imagens da subpasta recuperadas com sucesso!",
    "folder_id": 16,
    "images": [{ "id": 101, "path": ".../img1.jpg", "taken_at": "25/12/2024" }]
}
```

## Imagens

### POST /imagens/salvar

**Body:** (multipart/form-data)

-   image: arquivo(s) de imagem
-   idPasta: 15
-   dataFoto: "25/12/2024" (opcional)

**Response:**

```json
{
    "codRetorno": 200,
    "message": "Imagem(ns) carregada(s) com sucesso!",
    "image_paths": [".../img1.jpg", ".../img2.jpg"]
}
```

### DELETE /imagens/excluir

**Body:**

```json
{
    "idUsuario": 1,
    "idImagem": 101
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "Imagem excluída com sucesso!",
    "detalhes": {
        "imagem_excluida": "img1.jpg",
        "pasta": "Viagem2024",
        "total_fotos_restantes": 9
    }
}
```

## Tags

### POST /tags/cadastrar

**Body:**

```json
{
    "nomeTag": "Tag1",
    "usuario": 1
}
```

**Response (sucesso):**

```json
{
    "codRetorno": 201,
    "message": "Tag criada com sucesso.",
    "tag": {
        "id": 1,
        "nome": "Tag1",
        "tipo": "pessoal",
        "criada_em": "2025-09-20 10:00:00"
    },
    "limites": {
        "usado": 3,
        "limite": 5,
        "restante": 2
    }
}
```

**Response (erro - limite):**

```json
{
    "codRetorno": 403,
    "message": "Limite de tags do plano atingido.",
    "detalhes": {
        "limite_plano": 5,
        "tags_criadas": 5,
        "plano_atual": "Básico",
        "sugestao": "Faça upgrade do seu plano para criar mais tags."
    }
}
```

### GET /tags/listar

**Response:**

```json
{
    "codRetorno": 200,
    "message": "OK",
    "totalTags": 10,
    "tagsAtivas": 8,
    "data": [
        { "id": 1, "nomeTag": "Tag1", "status": 1 },
        { "id": 2, "nomeTag": "Tag2", "status": 1 }
    ]
}
```

### POST /tags/recuperar

**Body:**

```json
{
    "idTag": 1
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "OK",
    "data": { "id": 1, "nomeTag": "Tag1", "status": 1 }
}
```

### POST /tags/atualizar-status

**Body:**

```json
{
    "idTag": 1,
    "status": 0
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "OK"
}
```

### POST /tags/atualizar-dados

**Body:**

```json
{
    "idTag": 1,
    "nome": "Tag1Editada",
    "descricao": "Nova descrição"
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "OK"
}
```

### POST /tags/recuperar-tags-usuario

**Body:**

```json
{
    "usuario": 1
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "OK",
    "totalTags": 5,
    "data": [{ "id": 1, "nomeTag": "Tag1", "status": 1 }]
}
```

### DELETE /tags/excluir

**Body:**

```json
{
    "idTag": 1,
    "usuario": 1
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "Tag excluída com sucesso.",
    "tag_excluida": {
        "id": 1,
        "nome": "Tag1",
        "excluida_em": "2025-09-20 10:10:00"
    },
    "limites": {
        "usado": 2,
        "limite": 5,
        "restante": 3
    }
}
```

## PIX e Pagamentos

### POST /pix/enviar

**Body:**

```json
{
    "usuario": 1,
    "plano": 2
}
```

**Response (sucesso):**

```json
{
    "codRetorno": 200,
    "message": "Cobrança PIX criada com sucesso",
    "data": {
        "pix": "00020126360014BR.GOV.BCB.PIX0114+55819999999952040000530398654041.005802BR5920NOME USUARIO6009RECIFE62070503***6304B14F"
    }
}
```

**Response (erro):**

```json
{
    "codRetorno": 500,
    "message": "Erro interno no processamento PIX",
    "error": "Certificado não encontrado: ..."
}
```

### POST /pix/atualizar

**Body:**

```json
{
    "recs": [
        {
            "idRec": "rec_123",
            "status": "APROVADA",
            "ativacao": {
                "dadosJornada": {
                    "txid": "txid_abc"
                }
            }
        }
    ]
}
```

**Response:**

```json
{
    "codRetorno": 200,
    "message": "Processamento do webhook realizado com sucesso"
}
```

## Vendas

### POST /vendas/criar-assinatura

**Body:**

```json
{
    "usuario": 1,
    "plano": 2,
    "token": "tok_abc123"
}
```

**Response (sucesso):**

```json
{
    "codRetorno": 200,
    "message": "OK"
}
```

**Response (erro):**

```json
{
    "codRetorno": 400,
    "message": "Plano de destino não está disponível",
    "changePlan": false
}
```

### POST /vendas/cancelar-assinatura

**Body:**

```json
{
    "usuario": 1
}
```

**Response (sucesso):**

```json
{
    "codRetorno": 200,
    "message": "Assinatura cancelada com sucesso"
}
```

**Response (PIX):**

```json
{
    "codRetorno": 200,
    "message": "Para cancelamento de assinatura via PIX, entre em contato com o suporte do seu banco para solicitar o cancelamento."
}
```
