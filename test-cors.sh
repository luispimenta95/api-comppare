#!/bin/bash

# Teste de CORS para a API Laravel
# Execute este script para testar se o CORS está funcionando

echo "=== Testando CORS da API Laravel ==="
echo ""

# URL da API (ajuste conforme necessário)
API_URL="http://localhost:8000/api"

# Teste 1: Requisição OPTIONS (preflight)
echo "1. Testando requisição OPTIONS (preflight):"
curl -X OPTIONS \
  -H "Origin: http://localhost:3000" \
  -H "Access-Control-Request-Method: GET" \
  -H "Access-Control-Request-Headers: Content-Type" \
  -v \
  $API_URL/test 2>&1 | grep -E "(Access-Control|HTTP|Origin)"

echo ""
echo "---"

# Teste 2: Requisição GET simples
echo "2. Testando requisição GET com Origin:"
curl -X GET \
  -H "Origin: http://localhost:3000" \
  -H "Content-Type: application/json" \
  -v \
  $API_URL/test 2>&1 | grep -E "(Access-Control|HTTP|Origin)"

echo ""
echo "---"

# Teste 3: Teste com domínio em produção
echo "3. Testando com domínio de produção:"
curl -X OPTIONS \
  -H "Origin: https://app.comppare.com.br" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type,Authorization" \
  -v \
  $API_URL/test 2>&1 | grep -E "(Access-Control|HTTP|Origin)"

echo ""
echo "=== Teste concluído ==="
