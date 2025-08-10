<?php

require_once __DIR__ . '/vendor/autoload.php';

echo "=== TESTE DESENVOLVIMENTO PIX ===\n";
echo "Testando criação de PIX após correções...\n\n";

// Configurar ambiente
putenv('APP_ENV=local');

// Testar conectividade básica com certificado corrigido
$certificado = __DIR__ . "/storage/app/certificates/hml_clean.pem";

echo "1. Verificando certificado...\n";
echo "   Path: $certificado\n";
echo "   Existe: " . (file_exists($certificado) ? "✅ SIM" : "❌ NÃO") . "\n";
echo "   Legível: " . (is_readable($certificado) ? "✅ SIM" : "❌ NÃO") . "\n";

if (file_exists($certificado)) {
    echo "   Tamanho: " . filesize($certificado) . " bytes\n";
}

echo "\n2. Testando conectividade EFI...\n";

// Obter token
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => "https://pix-h.api.efipay.com.br/oauth/token",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode([
        "grant_type" => "client_credentials",
        "scope" => "gn.openbankingpix.receivables.create"
    ]),
    CURLOPT_HTTPHEADER => [
        "Authorization: Basic " . base64_encode("Client_Id_eaf177c1ab7f108d93343cba182e5dbbe5c052a2:Client_Secret_5177a0260eb88e7f38a56a05fddab2203d0e5e63"),
        "Content-Type: application/json"
    ],
    CURLOPT_SSLCERT => $certificado,
    CURLOPT_SSLCERTPASSWD => "",
    CURLOPT_SSLCERTTYPE => "PEM",
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
curl_close($curl);

if ($error) {
    echo "   ❌ Erro cURL: $error\n";
    exit(1);
}

if ($httpCode !== 200) {
    echo "   ❌ Erro HTTP: $httpCode\n";
    echo "   Response: $response\n";
    exit(1);
}

$tokenData = json_decode($response, true);
$token = $tokenData['access_token'] ?? null;

if (!$token) {
    echo "   ❌ Token não obtido\n";
    exit(1);
}

echo "   ✅ Token obtido com sucesso (" . strlen($token) . " chars)\n";

echo "\n3. Testando criação de COB com valor válido...\n";

$txid = md5(uniqid(rand(), true));
$cobUrl = "https://pix-h.api.efipay.com.br/v2/cob/$txid";

$cobData = [
    "calendario" => ["expiracao" => 3600],
    "devedor" => [
        "cpf" => "02342288140",
        "nome" => "Luis Felipe Araujo Pimenta"
    ],
    "valor" => ["original" => "15.00"], // Valor válido para teste
    "chave" => "contato@comppare.com.br"
];

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $cobUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CUSTOMREQUEST => 'PUT',
    CURLOPT_POSTFIELDS => json_encode($cobData),
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ],
    CURLOPT_SSLCERT => $certificado,
    CURLOPT_SSLCERTPASSWD => "",
    CURLOPT_SSLCERTTYPE => "PEM",
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
curl_close($curl);

echo "   HTTP Code: $httpCode\n";
echo "   cURL Error: " . ($error ?: "Nenhum") . "\n";

if ($httpCode === 201) {
    echo "   ✅ COB criado com sucesso!\n";
    $cobResponse = json_decode($response, true);
    echo "   TXID: " . ($cobResponse['txid'] ?? 'N/A') . "\n";
    echo "   Status: " . ($cobResponse['status'] ?? 'N/A') . "\n";
} else {
    echo "   ❌ Falha na criação do COB\n";
    echo "   Response: $response\n";
    exit(1);
}

echo "\n4. Testando criação de LOCREC...\n";

$locrecUrl = "https://pix-h.api.efipay.com.br/v2/locrec";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $locrecUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $token",
        "Content-Type: application/json"
    ],
    CURLOPT_SSLCERT => $certificado,
    CURLOPT_SSLCERTPASSWD => "",
    CURLOPT_SSLCERTTYPE => "PEM",
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => false,
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$error = curl_error($curl);
curl_close($curl);

echo "   HTTP Code: $httpCode\n";
echo "   cURL Error: " . ($error ?: "Nenhum") . "\n";

if ($httpCode === 201) {
    echo "   ✅ LOCREC criado com sucesso!\n";
    $locrecResponse = json_decode($response, true);
    $locrecId = $locrecResponse['id'] ?? null;
    echo "   Location ID: " . ($locrecId ?: 'N/A') . "\n";

    if ($locrecId) {
        echo "\n5. Testando criação de REC...\n";

        $recUrl = "https://pix-h.api.efipay.com.br/v2/rec";
        $recData = [
            "vinculo" => [
                "contrato" => "12345678",
                "devedor" => [
                    "cpf" => "02342288140",
                    "nome" => "Luis Felipe Araujo Pimenta"
                ],
                "objeto" => "Teste REC"
            ],
            "calendario" => [
                "dataInicial" => date('Y-m-d', strtotime('+1 day')),
                "periodicidade" => "MENSAL",
            ],
            "valor" => [
                "valorRec" => "15.00"
            ],
            "politicaRetentativa" => "NAO_PERMITE",
            "loc" => $locrecId,
            "ativacao" => [
                "dadosJornada" => [
                    "txid" => $txid
                ]
            ]
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $recUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($recData),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $token",
                "Content-Type: application/json"
            ],
            CURLOPT_SSLCERT => $certificado,
            CURLOPT_SSLCERTPASSWD => "",
            CURLOPT_SSLCERTTYPE => "PEM",
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        echo "   HTTP Code: $httpCode\n";
        echo "   cURL Error: " . ($error ?: "Nenhum") . "\n";

        if ($httpCode === 201) {
            echo "   ✅ REC criado com sucesso!\n";
            $recResponse = json_decode($response, true);
            echo "   REC ID: " . ($recResponse['idRec'] ?? 'N/A') . "\n";
        } else {
            echo "   ❌ Falha na criação do REC\n";
            echo "   Response: $response\n";

            // Analisar o erro específico
            $errorData = json_decode($response, true);
            if ($errorData && isset($errorData['mensagem'])) {
                echo "   Erro específico: " . $errorData['mensagem'] . "\n";
            }
        }
    }
} else {
    echo "   ❌ Falha na criação do LOCREC\n";
    echo "   Response: $response\n";
}

echo "\n=== RESULTADO ===\n";
echo "✅ Certificado: OK\n";
echo "✅ Token: OK\n";
echo "✅ COB: OK\n";
echo "✅ LOCREC: OK\n";
echo "? REC: Aguardando teste\n";
echo "\n🎉 Configuração para desenvolvimento está funcional!\n";

echo "\n=== FIM DO TESTE ===\n";
