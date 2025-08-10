<?php
/**
 * Servidor HTTPS com TLS Mútuo para teste
 * Execute: php serve-https-tls.php
 */

$host = '127.0.0.1';
$port = 8443;
$certDir = __DIR__ . '/storage/app/certificates';

// Verificar certificados
$serverCert = $certDir . '/server.pem';
$caCert = $certDir . '/ca.crt';

if (!file_exists($serverCert)) {
    die("❌ Certificado do servidor não encontrado: $serverCert\n");
}

if (!file_exists($caCert)) {
    die("❌ Certificado CA não encontrado: $caCert\n");
}

echo "🚀 Iniciando servidor HTTPS com TLS mútuo...\n";
echo "📍 Host: $host:$port\n";
echo "🔐 Certificado servidor: $serverCert\n";

// Contexto SSL
$context = stream_context_create([
    'ssl' => [
        'local_cert' => $serverCert,
        'verify_peer' => true,
        'verify_peer_name' => false,
        'cafile' => $caCert,
        'allow_self_signed' => true,
        'verify_depth' => 3,
    ]
]);

// Criar socket SSL
$socket = stream_socket_server(
    "ssl://$host:$port",
    $errno,
    $errstr,
    STREAM_SERVER_BIND | STREAM_SERVER_LISTEN,
    $context
);

if (!$socket) {
    die("❌ Erro ao criar servidor SSL: $errstr ($errno)\n");
}

echo "✅ Servidor HTTPS rodando em https://$host:$port\n";
echo "⏹️  Pressione Ctrl+C para parar\n\n";

// Loop principal
while (true) {
    $client = stream_socket_accept($socket, -1, $peer);
    
    if (!$client) {
        continue;
    }
    
    echo "📥 Conexão de: $peer\n";
    
    // Ler requisição básica
    $request = fgets($client);
    
    // Verificar certificado cliente
    $clientCert = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
    
    echo "🔍 Requisição: $request";
    echo "🔐 Certificado cliente: " . ($clientCert ? 'PRESENTE' : 'AUSENTE') . "\n";
    
    // Resposta simples
    $response = "HTTP/1.1 200 OK\r\n";
    $response .= "Content-Type: application/json\r\n";
    $response .= "Connection: close\r\n\r\n";
    
    $responseData = [
        'message' => 'TLS mútuo funcionando',
        'certificado_cliente' => $clientCert ? 'presente' : 'ausente',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    $response .= json_encode($responseData, JSON_PRETTY_PRINT);
    
    fwrite($client, $response);
    fclose($client);
    
    echo "✅ Resposta enviada\n\n";
}

fclose($socket);
?>
