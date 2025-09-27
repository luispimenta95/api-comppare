<?php
// Configurações da API
$apiUrl = 'https://api.comppare.com.br/api/usuarios/autenticar'; // Ajuste conforme sua configuração
$response = null;
$error = null;

// Processar formulário quando enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpf = $_POST['cpf'] ?? '';
    $senha = $_POST['senha'] ?? '';
    
    // Dados para enviar para a API
    $data = [
        'cpf' => $cpf,
        'senha' => $senha
    ];
    
    // Configurar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Executar requisição
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = 'Erro cURL: ' . curl_error($ch);
    } else {
        $response = json_decode($result, true);
    }
    
    curl_close($ch);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Autenticação - API CompPare</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        button {
            background-color: #007bff;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #0056b3;
        }
        .response {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
            white-space: pre-wrap;
            font-family: monospace;
            font-size: 14px;
            overflow-x: auto;
        }
        .success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .info {
            background-color: #e7f3ff;
            border: 1px solid #b8daff;
            color: #004085;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .json-container {
            max-height: 500px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 15px;
            background-color: #f8f9fa;
        }
        .folder-item {
            margin: 10px 0;
            padding: 10px;
            background-color: #f0f8ff;
            border-left: 4px solid #007bff;
        }
        .subfolder-item {
            margin: 5px 0 5px 20px;
            padding: 8px;
            background-color: #fff8dc;
            border-left: 3px solid #ffc107;
        }
        .image-item {
            margin: 3px 0 3px 40px;
            padding: 5px;
            background-color: #f0fff0;
            border-left: 2px solid #28a745;
        }
        .url-link {
            color: #007bff;
            text-decoration: none;
            word-break: break-all;
        }
        .url-link:hover {
            text-decoration: underline;
        }
        .folder-icon {
            display: inline-block;
            font-size: 24px;
            cursor: pointer;
            margin-right: 10px;
            transition: transform 0.2s;
        }
        .folder-icon:hover {
            transform: scale(1.2);
        }
        .image-icon {
            display: inline-block;
            font-size: 20px;
            cursor: pointer;
            margin-right: 8px;
            transition: transform 0.2s;
        }
        .image-icon:hover {
            transform: scale(1.1);
        }
        .clickable-item {
            display: flex;
            align-items: center;
            margin: 8px 0;
            padding: 8px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .clickable-item:hover {
            background-color: rgba(0, 123, 255, 0.1);
        }
        .item-info {
            flex: 1;
        }
        .item-name {
            font-weight: bold;
            margin-bottom: 2px;
        }
        .item-details {
            font-size: 12px;
            color: #666;
        }
        .folder-structure {
            margin-left: 20px;
            border-left: 2px dashed #ddd;
            padding-left: 15px;
        }
        .image-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .image-thumbnail {
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 5px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .image-thumbnail:hover {
            border-color: #007bff;
            box-shadow: 0 2px 8px rgba(0, 123, 255, 0.2);
            transform: translateY(-2px);
        }
        .image-thumbnail img {
            max-width: 100%;
            max-height: 80px;
            object-fit: cover;
            border-radius: 2px;
        }
        .toggle-button {
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            margin-left: 5px;
            color: #666;
        }
        .toggle-button:hover {
            color: #007bff;
        }
        .collapsible-content {
            margin-top: 10px;
        }
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Teste de Autenticação - API CompPare</h1>
        
        <form method="POST">
            <div class="form-group">
                <label for="cpf">CPF:</label>
                <input type="text" id="cpf" name="cpf" value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>" 
                       placeholder="Digite o CPF (apenas números)" maxlength="11" required>
            </div>
            
            <div class="form-group">
                <label for="senha">Senha:</label>
                <input type="password" id="senha" name="senha" placeholder="Digite a senha" required>
            </div>
            
            <button type="submit">Autenticar</button>
        </form>
    </div>

  

    <?php if ($response): ?>
        <div class="container">                
            <?php if (isset($response['pastas']) && is_array($response['pastas'])): ?>
                <h3>Pastas do Usuário (<?= count($response['pastas']) ?> pasta(s) principal(is)):</h3>
                <div class="json-container">
                    <?php foreach ($response['pastas'] as $pastaIndex => $pasta): ?>
                        <div class="folder-item">
                            <div class="clickable-item">
                                <span class="folder-icon">📁</span>
                                <div class="item-info">
                                    <div class="item-name"><?= htmlspecialchars($pasta['nome'] ?? 'Nome não disponível') ?></div>
                                    <div class="item-details">
                                        ID: <?= htmlspecialchars($pasta['id'] ?? 'N/A') ?> | 
                                        <?= count($pasta['subpastas'] ?? []) ?> subpasta(s)
                                    </div>
                                </div>
                                <?php if ((isset($pasta['subpastas']) && count($pasta['subpastas']) > 0) || (isset($pasta['imagens']) && count($pasta['imagens']) > 0)): ?>
                                    <button class="toggle-button" onclick="event.stopPropagation(); toggleContent('pasta-<?= $pastaIndex ?>')">▼</button>
                                <?php endif; ?>
                            </div>
                            
                         
                            
                            <div id="pasta-<?= $pastaIndex ?>" class="collapsible-content">
                                <?php if (isset($pasta['imagens']) && count($pasta['imagens']) > 0): ?>
                                    <div style="margin-left: 20px; margin-top: 15px;">
                                        <strong>🖼️ Imagens (<?= count($pasta['imagens']) ?>):</strong>
                                        <div class="image-gallery">
                                            <?php foreach ($pasta['imagens'] as $imagem): ?>
                                                <div class="image-thumbnail" onclick="openImage('<?= htmlspecialchars($imagem['url']) ?>')">
                                                    <div class="image-icon">🖼️</div>
                                                    <div style="font-size: 10px;">ID: <?= htmlspecialchars($imagem['id']) ?></div>
                                                    <div style="font-size: 9px; color: #666;">Clique para abrir</div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($pasta['subpastas']) && count($pasta['subpastas']) > 0): ?>
                                    <div class="folder-structure">
                                        <strong>📂 Subpastas (<?= count($pasta['subpastas']) ?>):</strong>
                                        <?php foreach ($pasta['subpastas'] as $subpastaIndex => $subpasta): ?>
                                            <div class="subfolder-item" style="margin-top: 10px;">
                                                <div class="clickable-item">
                                                    <span class="folder-icon" style="font-size: 20px;">📁</span>
                                                    <div class="item-info">
                                                        <div class="item-name"><?= htmlspecialchars($subpasta['nome']) ?></div>
                                                        <div class="item-details">
                                                            ID: <?= htmlspecialchars($subpasta['id']) ?> | 
                                                            <?= count($subpasta['imagens'] ?? []) ?> imagem(ns)
                                                        </div>
                                                    </div>
                                                    <?php if (isset($subpasta['imagens']) && count($subpasta['imagens']) > 0): ?>
                                                        <button class="toggle-button" onclick="event.stopPropagation(); toggleContent('subpasta-<?= $pastaIndex ?>-<?= $subpastaIndex ?>')">▼</button>
                                                    <?php endif; ?>
                                                </div>
                                            
                                                
                                                <?php if (isset($subpasta['imagens']) && count($subpasta['imagens']) > 0): ?>
                                                    <div id="subpasta-<?= $pastaIndex ?>-<?= $subpastaIndex ?>" class="collapsible-content">
                                                        <div style="margin-left: 20px; margin-top: 10px;">
                                                            <strong>🖼️ Imagens (<?= count($subpasta['imagens']) ?>):</strong>
                                                            <div class="image-gallery">
                                                                <?php foreach ($subpasta['imagens'] as $imagem): ?>
                                                                    <div class="image-thumbnail" onclick="openImage('<?= htmlspecialchars($imagem['url'] ?? $imagem['path']) ?>')">
                                                                        <div class="image-icon">🖼️</div>
                                                                        <div style="font-size: 10px;">ID: <?= htmlspecialchars($imagem['id']) ?></div>
                                                                        <div style="font-size: 9px; color: #666;">Clique para abrir</div>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <script>
        document.getElementById('cpf').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            e.target.value = value;
        });
        
        function openPath(url) {
            if (url && url !== '#') {
                window.open(url, '_blank');
            } else {
                alert('URL da pasta não disponível');
            }
        }
        
        function openImage(url) {
            if (url) {
                window.open(url, '_blank');
            } else {
                alert('URL da imagem não disponível');
            }
        }
        
        function toggleContent(elementId) {
            const element = document.getElementById(elementId);
            const button = event.target;
            
            if (element) {
                if (element.classList.contains('hidden')) {
                    element.classList.remove('hidden');
                    button.textContent = '▼';
                } else {
                    element.classList.add('hidden');
                    button.textContent = '▶';
                }
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const collapsibleElements = document.querySelectorAll('.collapsible-content');
            collapsibleElements.forEach(function(element) {
                element.classList.add('hidden');
            });
            
            const toggleButtons = document.querySelectorAll('.toggle-button');
            toggleButtons.forEach(function(button) {
                button.textContent = '▶';
            });
        });
    </script>
</body>
</html>