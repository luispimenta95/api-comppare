<?php
/**
 * Teste Simples da API de Autenticação
 * 
 * Este arquivo faz uma requisição direta para o endpoint de autenticação
 * e exibe a resposta formatada.
 */

// Configurações da API
$apiUrl = 'http://localhost:8000/api/usuario/autenticar';

// Dados de teste (altere conforme necessário)
$dadosTeste = [
    'cpf' => '12345678901', // Substitua por um CPF válido
    'senha' => 'senha123'    // Substitua por uma senha válida
];

echo "<h1>Teste da API de Autenticação - CompPare</h1>";
echo "<p><strong>URL:</strong> {$apiUrl}</p>";
echo "<p><strong>Dados enviados:</strong></p>";
echo "<pre>" . json_encode($dadosTeste, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

// Função para fazer requisição à API
function fazerRequisicao($url, $dados) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Para desenvolvimento local
    
    $resultado = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = curl_error($ch);
    
    curl_close($ch);
    
    return [
        'resultado' => $resultado,
        'http_code' => $httpCode,
        'erro' => $erro
    ];
}

// Fazer a requisição
echo "<h2>Executando requisição...</h2>";

$resposta = fazerRequisicao($apiUrl, $dadosTeste);

if ($resposta['erro']) {
    echo "<p style='color: red;'><strong>Erro cURL:</strong> {$resposta['erro']}</p>";
} else {
    echo "<p><strong>Código HTTP:</strong> {$resposta['http_code']}</p>";
    
    $dadosJson = json_decode($resposta['resultado'], true);
    
    if ($dadosJson) {
        echo "<h3>Resposta formatada:</h3>";
        
        if (isset($dadosJson['codRetorno']) && $dadosJson['codRetorno'] == 200) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4 style='color: #155724;'>✅ Autenticação realizada com sucesso!</h4>";
            
            if (isset($dadosJson['dados'])) {
                echo "<p><strong>Usuário:</strong> {$dadosJson['dados']['primeiroNome']} {$dadosJson['dados']['sobrenome']}</p>";
                echo "<p><strong>Email:</strong> {$dadosJson['dados']['email']}</p>";
                echo "<p><strong>CPF:</strong> {$dadosJson['dados']['cpf']}</p>";
            }
            
            if (isset($dadosJson['token'])) {
                echo "<p><strong>Token JWT:</strong></p>";
                echo "<textarea style='width: 100%; height: 100px; font-family: monospace;' readonly>{$dadosJson['token']}</textarea>";
            }
            
            if (isset($dadosJson['pastas'])) {
                echo "<p><strong>Pastas encontradas:</strong> " . count($dadosJson['pastas']) . "</p>";
                
                foreach ($dadosJson['pastas'] as $index => $pasta) {
                    echo "<div style='margin: 10px 0; padding: 10px; background: #f8f9fa; border-left: 4px solid #007bff;'>";
                    echo "<strong>Pasta " . ($index + 1) . ":</strong> " . ($pasta['nome'] ?? 'Nome não disponível') . "<br>";
                    
                    if (isset($pasta['url_pasta'])) {
                        echo "<strong>URL da Pasta:</strong> <a href='{$pasta['url_pasta']}' target='_blank'>{$pasta['url_pasta']}</a><br>";
                    }
                    
                    $totalImagens = count($pasta['imagens'] ?? []);
                    $totalSubpastas = count($pasta['subpastas'] ?? []);
                    
                    echo "<strong>Imagens:</strong> {$totalImagens}<br>";
                    echo "<strong>Subpastas:</strong> {$totalSubpastas}<br>";
                    
                    // Mostrar algumas imagens se existirem
                    if ($totalImagens > 0) {
                        echo "<strong>Exemplos de imagens:</strong><br>";
                        foreach (array_slice($pasta['imagens'], 0, 3) as $imagem) {
                            $url = $imagem['url'] ?? $imagem['path'];
                            echo "• <a href='{$url}' target='_blank'>{$url}</a><br>";
                        }
                        if ($totalImagens > 3) {
                            echo "... e mais " . ($totalImagens - 3) . " imagem(ns)<br>";
                        }
                    }
                    
                    // Mostrar subpastas se existirem
                    if ($totalSubpastas > 0) {
                        echo "<strong>Subpastas:</strong><br>";
                        foreach ($pasta['subpastas'] as $subpasta) {
                            echo "• {$subpasta['nome']} (" . count($subpasta['imagens'] ?? []) . " imagens)";
                            if (isset($subpasta['url_pasta'])) {
                                echo " - <a href='{$subpasta['url_pasta']}' target='_blank'>Acessar</a>";
                            }
                            echo "<br>";
                        }
                    }
                    
                    echo "</div>";
                }
            }
            
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4 style='color: #721c24;'>❌ Erro na autenticação</h4>";
            echo "<p><strong>Código:</strong> {$dadosJson['codRetorno']}</p>";
            echo "<p><strong>Mensagem:</strong> {$dadosJson['message']}</p>";
            echo "</div>";
        }
        
        echo "<h3>JSON Completo:</h3>";
        echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
        echo htmlspecialchars(json_encode($dadosJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "</pre>";
        
    } else {
        echo "<p style='color: red;'><strong>Erro:</strong> Resposta não é um JSON válido</p>";
        echo "<h3>Resposta bruta:</h3>";
        echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
        echo htmlspecialchars($resposta['resultado']);
        echo "</pre>";
    }
}

echo "<hr>";
echo "<p><em>Para testar com outros dados, edite as variáveis \$dadosTeste no início do arquivo.</em></p>";
echo "<p><a href='test-auth.php'>← Voltar para o formulário interativo</a></p>";
?>
