/**
 * Cliente JavaScript/Node.js para API CompPare
 * 
 * Este exemplo pode ser usado tanto no browser quanto no Node.js
 * Para Node.js: npm install node-fetch form-data
 */

class CompPareAPIClient {
    constructor(baseUrl = 'https://api.comppare.com.br/api') {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.token = null;
        
        // Para Node.js
        if (typeof window === 'undefined') {
            this.fetch = require('node-fetch');
            this.FormData = require('form-data');
            this.fs = require('fs');
        } else {
            // Para browser
            this.fetch = fetch;
            this.FormData = FormData;
        }
    }
    
    /**
     * Realiza login e obtém o token JWT
     */
    async login(email, senha) {
        const response = await this.makeRequest('POST', '/usuarios/autenticar', {
            email,
            senha
        });
        
        if (response.token) {
            this.token = response.token;
            return response;
        }
        
        throw new Error('Falha na autenticação: ' + JSON.stringify(response));
    }
    
    /**
     * Lista todas as pastas do usuário autenticado
     */
    async listarPastas() {
        this.checkAuthentication();
        return await this.makeRequest('GET', '/pastas');
    }
    
    /**
     * Cria uma nova pasta
     */
    async criarPasta(nomePasta, idPastaPai = null) {
        this.checkAuthentication();
        
        const data = { nomePasta };
        if (idPastaPai) {
            data.idPastaPai = idPastaPai;
        }
        
        return await this.makeRequest('POST', '/pastas', data);
    }
    
    /**
     * Faz upload de uma imagem
     * No browser: file deve ser um objeto File
     * No Node.js: filePath deve ser o caminho para o arquivo
     */
    async uploadImagem(idPasta, fileOrPath) {
        this.checkAuthentication();
        
        const formData = new this.FormData();
        formData.append('idPasta', idPasta.toString());
        
        if (typeof window === 'undefined') {
            // Node.js
            const fileStream = this.fs.createReadStream(fileOrPath);
            formData.append('image', fileStream);
        } else {
            // Browser
            formData.append('image', fileOrPath);
        }
        
        return await this.makeRequest('POST', '/photos/upload', formData, true);
    }
    
    /**
     * Obtém dados do usuário autenticado
     */
    async dadosUsuario() {
        this.checkAuthentication();
        return await this.makeRequest('GET', '/usuarios/dados');
    }
    
    /**
     * Lista planos disponíveis
     */
    async listarPlanos() {
        return await this.makeRequest('GET', '/planos');
    }
    
    /**
     * Aplica um cupom
     */
    async aplicarCupom(codigo, idPlano) {
        return await this.makeRequest('POST', '/cupons/aplicar', {
            codigo,
            idPlano
        });
    }
    
    /**
     * Verifica se o usuário está autenticado
     */
    checkAuthentication() {
        if (!this.token) {
            throw new Error('Usuário não autenticado. Faça login primeiro.');
        }
    }
    
    /**
     * Realiza requisições HTTP para a API
     */
    async makeRequest(method, endpoint, data = null, isFormData = false) {
        const url = this.baseUrl + endpoint;
        const headers = {};
        
        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }
        
        const options = {
            method,
            headers
        };
        
        if (data) {
            if (isFormData) {
                // Para FormData, não definir Content-Type
                options.body = data;
            } else {
                // Para JSON
                headers['Content-Type'] = 'application/json';
                options.body = JSON.stringify(data);
            }
        }
        
        const response = await this.fetch(url, options);
        
        if (!response.ok) {
            const error = await response.text();
            throw new Error(`HTTP ${response.status}: ${error}`);
        }
        
        return await response.json();
    }
}

// Exemplo de uso no Node.js
async function exemploNodeJs() {
    try {
        const api = new CompPareAPIClient();
        
        // 1. Fazer login
        console.log('🔐 Fazendo login...');
        const loginResponse = await api.login('usuario@email.com', 'senha123');
        console.log('✅ Login realizado com sucesso!');
        console.log('Token:', loginResponse.token.substring(0, 20) + '...\n');
        
        // 2. Obter dados do usuário
        console.log('👤 Obtendo dados do usuário...');
        const userData = await api.dadosUsuario();
        console.log(`Nome: ${userData.user.primeiroNome} ${userData.user.sobrenome}`);
        console.log(`Email: ${userData.user.email}\n`);
        
        // 3. Listar pastas
        console.log('📁 Listando pastas...');
        const pastas = await api.listarPastas();
        console.log(`Total de pastas: ${pastas.pastas.length}`);
        
        pastas.pastas.forEach(pasta => {
            console.log(`- ${pasta.nomePasta} (ID: ${pasta.id})`);
            if (pasta.subpastas && pasta.subpastas.length > 0) {
                pasta.subpastas.forEach(subpasta => {
                    console.log(`  └── ${subpasta.nomePasta} (ID: ${subpasta.id})`);
                });
            }
        });
        console.log();
        
        // 4. Criar nova pasta
        console.log('📁 Criando nova pasta...');
        const novaPasta = await api.criarPasta(`Pasta Exemplo ${new Date().toISOString()}`);
        console.log(`✅ Pasta criada: ${novaPasta.pasta.nomePasta} (ID: ${novaPasta.pasta.id})\n`);
        
        // 5. Listar planos
        console.log('💼 Listando planos...');
        const planos = await api.listarPlanos();
        planos.forEach(plano => {
            console.log(`- ${plano.nome}: R$ ${plano.valor} (${plano.limitePastas} pastas)`);
        });
        
        console.log('\n🎉 Exemplo concluído com sucesso!');
        
    } catch (error) {
        console.error('❌ Erro:', error.message);
    }
}

// Exemplo de uso no browser
function exemploBrowser() {
    const api = new CompPareAPIClient();
    
    // Função para fazer login
    window.fazerLogin = async function() {
        const email = document.getElementById('email').value;
        const senha = document.getElementById('senha').value;
        
        try {
            const response = await api.login(email, senha);
            document.getElementById('resultado').innerHTML = 
                `✅ Login realizado! Token: ${response.token.substring(0, 20)}...`;
        } catch (error) {
            document.getElementById('resultado').innerHTML = 
                `❌ Erro: ${error.message}`;
        }
    };
    
    // Função para listar pastas
    window.listarPastas = async function() {
        try {
            const pastas = await api.listarPastas();
            let html = '<h3>📁 Suas Pastas:</h3><ul>';
            
            pastas.pastas.forEach(pasta => {
                html += `<li>${pasta.nomePasta} (ID: ${pasta.id})`;
                if (pasta.subpastas && pasta.subpastas.length > 0) {
                    html += '<ul>';
                    pasta.subpastas.forEach(sub => {
                        html += `<li>${sub.nomePasta} (ID: ${sub.id})</li>`;
                    });
                    html += '</ul>';
                }
                html += '</li>';
            });
            
            html += '</ul>';
            document.getElementById('resultado').innerHTML = html;
        } catch (error) {
            document.getElementById('resultado').innerHTML = 
                `❌ Erro: ${error.message}`;
        }
    };
    
    // Função para upload de arquivo
    window.uploadArquivo = async function() {
        const fileInput = document.getElementById('arquivo');
        const idPasta = document.getElementById('idPasta').value;
        
        if (!fileInput.files[0]) {
            alert('Selecione um arquivo!');
            return;
        }
        
        try {
            const response = await api.uploadImagem(idPasta, fileInput.files[0]);
            document.getElementById('resultado').innerHTML = 
                `✅ Upload realizado! URL: ${response.photo.url}`;
        } catch (error) {
            document.getElementById('resultado').innerHTML = 
                `❌ Erro: ${error.message}`;
        }
    };
}

// Executar exemplo baseado no ambiente
if (typeof window === 'undefined') {
    // Node.js
    exemploNodeJs();
} else {
    // Browser
    exemploBrowser();
}

// Exportar para Node.js
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CompPareAPIClient;
}
