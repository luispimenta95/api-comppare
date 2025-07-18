#!/bin/bash

# DEMONSTRAÇÃO: Como chamar os endpoints PIX no servidor
# Servidor rodando em: http://localhost:8000

echo "=== TESTANDO ENDPOINTS PIX DA API ==="
echo ""
echo "🚀 Servidor rodando em: http://localhost:8000"
echo ""

# 1. TESTE DE CONECTIVIDADE
echo "1️⃣ Testando conectividade da API..."
curl -s -X GET http://localhost:8000/api/test | jq '.' || echo "❌ Erro: jq não instalado. Resultado sem formatação:"
curl -s -X GET http://localhost:8000/api/test
echo ""
echo "---"

# 2. PIX RECORRENTE
echo ""
echo "2️⃣ Criando cobrança PIX RECORRENTE..."
echo "Endpoint: POST /api/pix/recorrente"
echo ""

curl -X POST http://localhost:8000/api/pix/recorrente \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "contrato": "63100862",
    "devedor": {
      "cpf": "45164632481",
      "nome": "Fulano de Tal"
    },
    "objeto": "Serviço de Streamming de Música",
    "dataFinal": "2025-04-01",
    "dataInicial": "2024-04-01",
    "periodicidade": "MENSAL",
    "valor": "35.00",
    "politicaRetentativa": "NAO_PERMITE",
    "loc": 108,
    "txid": "33beb661beda44a8928fef47dbeb2dc5"
  }' | jq '.' || echo "Resultado sem formatação:"

curl -X POST http://localhost:8000/api/pix/recorrente \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "contrato": "63100862",
    "devedor": {
      "cpf": "45164632481",
      "nome": "Fulano de Tal"
    },
    "objeto": "Serviço de Streamming de Música",
    "dataFinal": "2025-04-01",
    "dataInicial": "2024-04-01",
    "periodicidade": "MENSAL",
    "valor": "35.00",
    "politicaRetentativa": "NAO_PERMITE",
    "loc": 108,
    "txid": "33beb661beda44a8928fef47dbeb2dc5"
  }'

echo ""
echo "---"

# 3. PIX DINÂMICO
echo ""
echo "3️⃣ Criando cobrança PIX DINÂMICO (com QR Code)..."
echo "Endpoint: POST /api/pix/dinamico"
echo ""

curl -X POST http://localhost:8000/api/pix/dinamico \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "devedor": {
      "cpf": "45164632481",
      "nome": "Fulano de Tal"
    },
    "valor": 35.00,
    "descricao": "Serviço de Streamming de Música",
    "expiracao": 3600,
    "chave_pix": "sua_chave_pix@email.com"
  }' | jq '.' || echo "Resultado sem formatação:"

curl -X POST http://localhost:8000/api/pix/dinamico \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "devedor": {
      "cpf": "45164632481",
      "nome": "Fulano de Tal"
    },
    "valor": 35.00,
    "descricao": "Serviço de Streamming de Música",
    "expiracao": 3600,
    "chave_pix": "sua_chave_pix@email.com"
  }'

echo ""
echo "---"

# 4. GERAR QR CODE
echo ""
echo "4️⃣ Gerando QR Code para cobrança existente..."
echo "Endpoint: GET /api/pix/qrcode/{txid}"
echo ""

curl -X GET http://localhost:8000/api/pix/qrcode/33beb661beda44a8928fef47dbeb2dc5 \
  -H "Accept: application/json" | jq '.' || echo "Resultado sem formatação:"

curl -X GET http://localhost:8000/api/pix/qrcode/33beb661beda44a8928fef47dbeb2dc5 \
  -H "Accept: application/json"

echo ""
echo ""
echo "=== TESTE CONCLUÍDO ==="
echo ""
echo "📋 RESUMO DOS ENDPOINTS:"
echo "• POST /api/pix/recorrente - Criar cobrança recorrente"
echo "• POST /api/pix/dinamico - Criar cobrança com QR Code"
echo "• GET /api/pix/qrcode/{txid} - Gerar QR Code"
echo ""
echo "🔧 Para usar em produção, substitua 'localhost:8000' pelo domínio real"
