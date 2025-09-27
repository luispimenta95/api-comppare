"""
Cliente Python para API CompPare

Este exemplo demonstra como consumir a API CompPare usando Python.

Dependências:
    pip install requests

Uso:
    python client.py
"""

import requests
import json
from typing import Optional, Dict, Any
import os
from datetime import datetime


class CompPareAPIClient:
    def __init__(self, base_url: str = 'https://api.comppare.com.br/api'):
        """
        Inicializa o cliente da API CompPare
        
        Args:
            base_url: URL base da API
        """
        self.base_url = base_url.rstrip('/')
        self.token = None
        self.session = requests.Session()
        
    def login(self, email: str, senha: str) -> Dict[str, Any]:
        """
        Realiza login e obtém o token JWT
        
        Args:
            email: Email do usuário
            senha: Senha do usuário
            
        Returns:
            Dados da resposta do login
            
        Raises:
            Exception: Em caso de falha na autenticação
        """
        data = {
            'email': email,
            'senha': senha
        }
        
        response = self._make_request('POST', '/usuarios/autenticar', data)
        
        if 'token' in response:
            self.token = response['token']
            self.session.headers.update({'Authorization': f'Bearer {self.token}'})
            return response
        
        raise Exception(f'Falha na autenticação: {response}')
    
    def listar_pastas(self) -> Dict[str, Any]:
        """
        Lista todas as pastas do usuário autenticado
        
        Returns:
            Lista de pastas e subpastas
        """
        self._check_authentication()
        return self._make_request('GET', '/pastas')
    
    def criar_pasta(self, nome_pasta: str, id_pasta_pai: Optional[int] = None) -> Dict[str, Any]:
        """
        Cria uma nova pasta
        
        Args:
            nome_pasta: Nome da pasta a ser criada
            id_pasta_pai: ID da pasta pai (opcional)
            
        Returns:
            Dados da pasta criada
        """
        self._check_authentication()
        
        data = {'nomePasta': nome_pasta}
        if id_pasta_pai:
            data['idPastaPai'] = id_pasta_pai
        
        return self._make_request('POST', '/pastas', data)
    
    def upload_imagem(self, id_pasta: int, caminho_arquivo: str) -> Dict[str, Any]:
        """
        Faz upload de uma imagem
        
        Args:
            id_pasta: ID da pasta de destino
            caminho_arquivo: Caminho para o arquivo de imagem
            
        Returns:
            Dados da imagem enviada
        """
        self._check_authentication()
        
        if not os.path.exists(caminho_arquivo):
            raise FileNotFoundError(f"Arquivo não encontrado: {caminho_arquivo}")
        
        with open(caminho_arquivo, 'rb') as file:
            files = {'image': file}
            data = {'idPasta': id_pasta}
            
            return self._make_request('POST', '/photos/upload', data, files=files)
    
    def dados_usuario(self) -> Dict[str, Any]:
        """
        Obtém dados do usuário autenticado
        
        Returns:
            Dados do usuário
        """
        self._check_authentication()
        return self._make_request('GET', '/usuarios/dados')
    
    def listar_planos(self) -> list:
        """
        Lista todos os planos disponíveis
        
        Returns:
            Lista de planos
        """
        return self._make_request('GET', '/planos')
    
    def aplicar_cupom(self, codigo: str, id_plano: int) -> Dict[str, Any]:
        """
        Aplica um cupom de desconto
        
        Args:
            codigo: Código do cupom
            id_plano: ID do plano
            
        Returns:
            Resultado da aplicação do cupom
        """
        data = {
            'codigo': codigo,
            'idPlano': id_plano
        }
        
        return self._make_request('POST', '/cupons/aplicar', data)
    
    def _check_authentication(self):
        """Verifica se o usuário está autenticado"""
        if not self.token:
            raise Exception('Usuário não autenticado. Faça login primeiro.')
    
    def _make_request(self, method: str, endpoint: str, data: Optional[Dict] = None, 
                     files: Optional[Dict] = None) -> Dict[str, Any]:
        """
        Realiza requisições HTTP para a API
        
        Args:
            method: Método HTTP (GET, POST, etc.)
            endpoint: Endpoint da API
            data: Dados a serem enviados
            files: Arquivos para upload
            
        Returns:
            Resposta da API decodificada
            
        Raises:
            Exception: Em caso de erro HTTP
        """
        url = self.base_url + endpoint
        
        try:
            if files:
                # Para upload de arquivos
                response = self.session.request(method, url, data=data, files=files)
            elif data:
                # Para dados JSON
                response = self.session.request(method, url, json=data)
            else:
                # Para requisições sem dados
                response = self.session.request(method, url)
            
            response.raise_for_status()
            return response.json()
            
        except requests.exceptions.HTTPError as e:
            try:
                error_data = response.json()
                raise Exception(f"Erro HTTP {response.status_code}: {error_data}")
            except:
                raise Exception(f"Erro HTTP {response.status_code}: {response.text}")
        except requests.exceptions.RequestException as e:
            raise Exception(f"Erro de rede: {e}")


def exemplo_uso():
    """Exemplo de uso do cliente da API"""
    try:
        # Inicializar cliente
        api = CompPareAPIClient()
        
        # 1. Fazer login
        print("🔐 Fazendo login...")
        login_response = api.login('usuario@email.com', 'senha123')
        print("✅ Login realizado com sucesso!")
        print(f"Token: {login_response['token'][:20]}...\n")
        
        # 2. Obter dados do usuário
        print("👤 Obtendo dados do usuário...")
        user_data = api.dados_usuario()
        user = user_data['user']
        print(f"Nome: {user['primeiroNome']} {user['sobrenome']}")
        print(f"Email: {user['email']}\n")
        
        # 3. Listar pastas
        print("📁 Listando pastas...")
        pastas = api.listar_pastas()
        print(f"Total de pastas: {len(pastas['pastas'])}")
        
        for pasta in pastas['pastas']:
            print(f"- {pasta['nomePasta']} (ID: {pasta['id']})")
            if pasta.get('subpastas'):
                for subpasta in pasta['subpastas']:
                    print(f"  └── {subpasta['nomePasta']} (ID: {subpasta['id']})")
        print()
        
        # 4. Criar nova pasta
        print("📁 Criando nova pasta...")
        timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        nova_pasta = api.criar_pasta(f'Pasta Exemplo {timestamp}')
        print(f"✅ Pasta criada: {nova_pasta['pasta']['nomePasta']} (ID: {nova_pasta['pasta']['id']})\n")
        
        # 5. Listar planos
        print("💼 Listando planos...")
        planos = api.listar_planos()
        for plano in planos:
            print(f"- {plano['nome']}: R$ {plano['valor']} ({plano['limitePastas']} pastas)")
        print()
        
        # 6. Upload de imagem (descomente e ajuste o caminho)
        """
        print("🖼️ Fazendo upload de imagem...")
        upload = api.upload_imagem(nova_pasta['pasta']['id'], '/caminho/para/imagem.jpg')
        print(f"✅ Upload realizado com sucesso!")
        print(f"URL da imagem: {upload['photo']['url']}")
        """
        
        print("🎉 Exemplo concluído com sucesso!")
        
    except Exception as e:
        print(f"❌ Erro: {e}")


def exemplo_interativo():
    """Exemplo interativo para testar a API"""
    api = CompPareAPIClient()
    
    print("🚀 CompPare API - Cliente Python Interativo")
    print("=" * 50)
    
    while True:
        print("\nOpções disponíveis:")
        print("1. 🔐 Fazer login")
        print("2. 📁 Listar pastas")
        print("3. 📁 Criar pasta")
        print("4. 👤 Dados do usuário")
        print("5. 💼 Listar planos")
        print("6. 🖼️ Upload de imagem")
        print("0. 🚪 Sair")
        
        escolha = input("\nEscolha uma opção: ").strip()
        
        try:
            if escolha == "1":
                email = input("Email: ")
                senha = input("Senha: ")
                response = api.login(email, senha)
                print(f"✅ Login realizado! Token: {response['token'][:20]}...")
                
            elif escolha == "2":
                pastas = api.listar_pastas()
                print(f"\n📁 Total de pastas: {len(pastas['pastas'])}")
                for pasta in pastas['pastas']:
                    print(f"- {pasta['nomePasta']} (ID: {pasta['id']})")
                    
            elif escolha == "3":
                nome = input("Nome da pasta: ")
                id_pai = input("ID da pasta pai (opcional): ").strip()
                id_pai = int(id_pai) if id_pai else None
                
                response = api.criar_pasta(nome, id_pai)
                print(f"✅ Pasta criada: {response['pasta']['nomePasta']}")
                
            elif escolha == "4":
                user_data = api.dados_usuario()
                user = user_data['user']
                print(f"\n👤 Dados do usuário:")
                print(f"Nome: {user['primeiroNome']} {user['sobrenome']}")
                print(f"Email: {user['email']}")
                print(f"CPF: {user['cpf']}")
                
            elif escolha == "5":
                planos = api.listar_planos()
                print(f"\n💼 Planos disponíveis:")
                for plano in planos:
                    print(f"- {plano['nome']}: R$ {plano['valor']}")
                    
            elif escolha == "6":
                id_pasta = int(input("ID da pasta: "))
                caminho = input("Caminho para a imagem: ")
                
                response = api.upload_imagem(id_pasta, caminho)
                print(f"✅ Upload realizado! URL: {response['photo']['url']}")
                
            elif escolha == "0":
                print("👋 Até mais!")
                break
                
            else:
                print("❌ Opção inválida!")
                
        except Exception as e:
            print(f"❌ Erro: {e}")


if __name__ == "__main__":
    import sys
    
    if len(sys.argv) > 1 and sys.argv[1] == "--interactive":
        exemplo_interativo()
    else:
        exemplo_uso()
