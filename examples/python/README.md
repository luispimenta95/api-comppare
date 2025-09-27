# Cliente Python para API CompPare

## 📋 Descrição

Cliente Python completo para consumir a API CompPare, com exemplos práticos de uso.

## 🚀 Instalação

### Dependências
```bash
pip install requests
```

### Uso Básico
```bash
python client.py
```

### Modo Interativo
```bash
python client.py --interactive
```

## 📖 Exemplos de Uso

### 1. Autenticação
```python
from client import CompPareAPIClient

api = CompPareAPIClient()
response = api.login('usuario@email.com', 'senha123')
print(f"Token: {response['token']}")
```

### 2. Listar Pastas
```python
pastas = api.listar_pastas()
for pasta in pastas['pastas']:
    print(f"- {pasta['nomePasta']} (ID: {pasta['id']})")
```

### 3. Criar Pasta
```python
nova_pasta = api.criar_pasta('Minha Nova Pasta')
print(f"Pasta criada: {nova_pasta['pasta']['nomePasta']}")
```

### 4. Upload de Imagem
```python
upload = api.upload_imagem(pasta_id=1, caminho_arquivo='imagem.jpg')
print(f"URL da imagem: {upload['photo']['url']}")
```

## 🔧 Configuração

### Ambiente de Desenvolvimento
```python
api = CompPareAPIClient('http://127.0.0.1:8000/api')
```

### Ambiente de Produção
```python
api = CompPareAPIClient('https://api.comppare.com.br/api')
```

## 🧪 Testes

Para testar o cliente:

1. Execute o modo interativo:
```bash
python client.py --interactive
```

2. Siga as instruções na tela:
   - Faça login com suas credenciais
   - Teste as funcionalidades disponíveis

## 📚 Métodos Disponíveis

| Método | Descrição |
|--------|-----------|
| `login(email, senha)` | Autenticação do usuário |
| `listar_pastas()` | Lista todas as pastas |
| `criar_pasta(nome, id_pai?)` | Cria nova pasta |
| `upload_imagem(id_pasta, arquivo)` | Upload de imagem |
| `dados_usuario()` | Dados do usuário autenticado |
| `listar_planos()` | Lista planos disponíveis |
| `aplicar_cupom(codigo, id_plano)` | Aplica cupom de desconto |

## ⚠️ Tratamento de Erros

O cliente trata automaticamente:
- Erros HTTP (4xx, 5xx)
- Erros de rede
- Arquivos não encontrados
- Falhas de autenticação

```python
try:
    api.login('email', 'senha')
except Exception as e:
    print(f"Erro: {e}")
```

## 🔒 Segurança

- Token JWT é automaticamente incluído nas requisições
- Suporte a HTTPS
- Validação de arquivos antes do upload
