<?php

/**
 * Exemplo de como usar os métodos PIX da classe ApiEfi
 * 
 * Baseado no JSON fornecido:
 * {
 *   "vinculo": {
 *     "contrato": "63100862",
 *     "devedor": {
 *       "cpf": "45164632481",
 *       "nome": "Fulano de Tal"
 *     },
 *     "objeto": "Serviço de Streamming de Música."
 *   },
 *   "calendario": {
 *     "dataFinal": "2025-04-01",
 *     "dataInicial": "2024-04-01",
 *     "periodicidade": "MENSAL"
 *   },
 *   "valor": {
 *     "valorRec": "35.00"
 *   },
 *   "politicaRetentativa": "NAO_PERMITE",
 *   "loc": 108,
 *   "ativacao": {
 *     "dadosJornada": {
 *       "txid": "33beb661beda44a8928fef47dbeb2dc5"
 *     }
 *   }
 * }
 */

require_once __DIR__ . '/app/Http/Util/Payments/ApiEfi.php';

use App\Http\Util\Payments\ApiEfi;

// Exemplo 1: Cobrança PIX Recorrente
$apiEfi = new ApiEfi();

$dadosRecorrente = [
    'contrato' => '63100862',
    'devedor' => [
        'cpf' => '45164632481',
        'nome' => 'Fulano de Tal'
    ],
    'objeto' => 'Serviço de Streamming de Música.',
    'dataFinal' => '2025-04-01',
    'dataInicial' => '2024-04-01',
    'periodicidade' => 'MENSAL',
    'valor' => '35.00',
    'politicaRetentativa' => 'NAO_PERMITE',
    'loc' => 108,
    'txid' => '33beb661beda44a8928fef47dbeb2dc5'
];

$resultadoRecorrente = $apiEfi->createPixRecurrentCharge($dadosRecorrente);
echo "Resultado PIX Recorrente:\n";
echo $resultadoRecorrente . "\n\n";

// Exemplo 2: Cobrança PIX Dinâmica (QR Code)
$dadosDinamico = [
    'devedor' => [
        'cpf' => '45164632481',
        'nome' => 'Fulano de Tal'
    ],
    'valor' => 35.00,
    'descricao' => 'Serviço de Streamming de Música',
    'expiracao' => 3600, // 1 hora
    'chave_pix' => 'sua_chave_pix@email.com'
];

$resultadoDinamico = $apiEfi->createPixDynamicCharge($dadosDinamico);
echo "Resultado PIX Dinâmico:\n";
echo $resultadoDinamico . "\n\n";

// Exemplo 3: Gerar QR Code para uma cobrança existente
$txid = '33beb661beda44a8928fef47dbeb2dc5';
$qrCode = $apiEfi->generatePixQRCode($txid);
echo "QR Code gerado:\n";
echo $qrCode . "\n\n";

/**
 * EXEMPLO DE REQUISIÇÃO CURL para testar via API:
 * 
 * 1. PIX Recorrente:
 * curl -X POST http://localhost:8000/api/pix/recorrente \
 *   -H "Content-Type: application/json" \
 *   -d '{
 *     "contrato": "63100862",
 *     "devedor": {
 *       "cpf": "45164632481",
 *       "nome": "Fulano de Tal"
 *     },
 *     "objeto": "Serviço de Streamming de Música",
 *     "dataFinal": "2025-04-01",
 *     "dataInicial": "2024-04-01",
 *     "periodicidade": "MENSAL",
 *     "valor": "35.00",
 *     "politicaRetentativa": "NAO_PERMITE",
 *     "loc": 108,
 *     "txid": "33beb661beda44a8928fef47dbeb2dc5"
 *   }'
 * 
 * 2. PIX Dinâmico:
 * curl -X POST http://localhost:8000/api/pix/dinamico \
 *   -H "Content-Type: application/json" \
 *   -d '{
 *     "devedor": {
 *       "cpf": "45164632481",
 *       "nome": "Fulano de Tal"
 *     },
 *     "valor": 35.00,
 *     "descricao": "Serviço de Streamming de Música",
 *     "expiracao": 3600,
 *     "chave_pix": "sua_chave_pix@email.com"
 *   }'
 * 
 * 3. Gerar QR Code:
 * curl -X GET http://localhost:8000/api/pix/qrcode/33beb661beda44a8928fef47dbeb2dc5
 */
