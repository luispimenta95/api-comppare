<?php
/**
 * Exemplo de consumo da API CompPare em PHP
 * 
 * Este exemplo demonstra:
 * - Autenticação e obtenção do token JWT
 * - Listagem de pastas do usuário
 * - Criação de nova pasta
 * - Upload de imagem
 */

class CompPareAPIClient {
    private $baseUrl;
    private $token;
    
    public function __construct($baseUrl = 'https://api.comppare.com.br/api') {
        $this->baseUrl = rtrim($baseUrl, '/');
    }
    
    /**
     * Realiza login e obtém o token JWT
     */
    public function login($email, $senha) {
        $data = [
            'email' => $email,
            'senha' => $senha
        ];
        
        $response = $this->makeRequest('POST', '/usuarios/autenticar', $data);
        
        if ($response && isset($response['token'])) {
            $this->token = $response['token'];
            return $response;
        }
        
        throw new Exception('Falha na autenticação: ' . json_encode($response));
    }
    
    /**
     * Lista todas as pastas do usuário autenticado
     */
    public function listarPastas() {
        $this->checkAuthentication();
        return $this->makeRequest('GET', '/pastas');
    }
    
    /**
     * Cria uma nova pasta
     */
    public function criarPasta($nomePasta, $idPastaPai = null) {
        $this->checkAuthentication();
        
        $data = ['nomePasta' => $nomePasta];
        if ($idPastaPai) {
            $data['idPastaPai'] = $idPastaPai;
        }
        
        return $this->makeRequest('POST', '/pastas', $data);
    }
    
    /**
     * Faz upload de uma imagem
     */
    public function uploadImagem($idPasta, $caminhoArquivo) {
        $this->checkAuthentication();
        
        if (!file_exists($caminhoArquivo)) {
            throw new Exception("Arquivo não encontrado: $caminhoArquivo");
        }
        
        $data = [
            'idPasta' => $idPasta,
            'image' => new CURLFile($caminhoArquivo)
        ];
        
        return $this->makeRequest('POST', '/photos/upload', $data, true);
    }
    
    /**
     * Obtém dados do usuário autenticado
     */
    public function dadosUsuario() {
        $this->checkAuthentication();
        return $this->makeRequest('GET', '/usuarios/dados');
    }
    
    /**
     * Verifica se o usuário está autenticado
     */
    private function checkAuthentication() {
        if (!$this->token) {
            throw new Exception('Usuário não autenticado. Faça login primeiro.');
        }
    }
    
    /**
     * Realiza requisições HTTP para a API
     */
    private function makeRequest($method, $endpoint, $data = null, $multipart = false) {
        $url = $this->baseUrl . $endpoint;
        $headers = ['Content-Type: application/json'];
        
        if ($this->token) {
            $headers[] = "Authorization: Bearer {$this->token}";
        }
        
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false, // Para desenvolvimento
        ]);
        
        if ($data) {
            if ($multipart) {
                // Para upload de arquivos
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                // Remove Content-Type para multipart
                $headers = array_filter($headers, function($header) {
                    return !str_starts_with($header, 'Content-Type:');
                });
                curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
            } else {
                // Para JSON
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        
        if ($error) {
            throw new Exception("Erro cURL: $error");
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            throw new Exception("Erro HTTP $httpCode: " . json_encode($decodedResponse));
        }
        
        return $decodedResponse;
    }
}

// Exemplo de uso
try {
    $api = new CompPareAPIClient();
    
    // 1. Fazer login
    echo "🔐 Fazendo login...\n";
    $loginResponse = $api->login('usuario@email.com', 'senha123');
    echo "✅ Login realizado com sucesso!\n";
    echo "Token: " . substr($loginResponse['token'], 0, 20) . "...\n\n";
    
    // 2. Obter dados do usuário
    echo "👤 Obtendo dados do usuário...\n";
    $userData = $api->dadosUsuario();
    echo "Nome: {$userData['user']['primeiroNome']} {$userData['user']['sobrenome']}\n";
    echo "Email: {$userData['user']['email']}\n\n";
    
    // 3. Listar pastas
    echo "📁 Listando pastas...\n";
    $pastas = $api->listarPastas();
    echo "Total de pastas: " . count($pastas['pastas']) . "\n";
    
    foreach ($pastas['pastas'] as $pasta) {
        echo "- {$pasta['nomePasta']} (ID: {$pasta['id']})\n";
        if (!empty($pasta['subpastas'])) {
            foreach ($pasta['subpastas'] as $subpasta) {
                echo "  └── {$subpasta['nomePasta']} (ID: {$subpasta['id']})\n";
            }
        }
    }
    echo "\n";
    
    // 4. Criar nova pasta
    echo "📁 Criando nova pasta...\n";
    $novaPasta = $api->criarPasta('Pasta Exemplo ' . date('Y-m-d H:i:s'));
    echo "✅ Pasta criada: {$novaPasta['pasta']['nomePasta']} (ID: {$novaPasta['pasta']['id']})\n\n";
    
    // 5. Upload de imagem (descomente e ajuste o caminho)
    /*
    echo "🖼️ Fazendo upload de imagem...\n";
    $upload = $api->uploadImagem($novaPasta['pasta']['id'], '/caminho/para/imagem.jpg');
    echo "✅ Upload realizado com sucesso!\n";
    echo "URL da imagem: {$upload['photo']['url']}\n";
    */
    
    echo "🎉 Exemplo concluído com sucesso!\n";
    
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
}
?>
