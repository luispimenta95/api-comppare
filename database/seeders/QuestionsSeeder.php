<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionsSeeder extends Seeder
{
    public function run()
    {

        DB::table('questions')->insert([
            ['id' => 1, 'pergunta' => 'O que são os álbuns e subálbuns?', 'resposta' => 'Álbum: É a pasta principal onde você irá organizar os subálbuns.Subálbum: É a pasta secundária onde serão armazenadas as fotos de um tema específico do álbum. Exemplo: Álbum: casa | Subálbuns: sala, quarto, banheiro, área externa, piscina etc', 'created_at' => '2025-04-30 20:08:16', 'updated_at' => '2025-04-30 20:08:16'],
            ['id' => 2, 'pergunta' => 'O que são as categorias?', 'resposta' => 'As categorias são campos para você inserir informações referente a foto selecionada. Os campos são livres para atender a sua necessidade. Exemplos: Data, peso, altura, medida, profundidade, porcentagem, quantidade etc', 'created_at' => '2025-04-30 20:12:18', 'updated_at' => '2025-04-30 20:12:18'],
            ['id' => 3, 'pergunta' => 'Quais são os planos existentes?', 'resposta' => 'Hoje, os planos existentes são: gratuito, básico e avançado.', 'created_at' => '2025-04-30 20:13:10', 'updated_at' => '2025-04-30 20:13:10'],
            ['id' => 4, 'pergunta' => 'Qual a diferença do mensal para o anual?', 'resposta' => 'A diferença entre os planos, é que no plano anual, você possui um desconto de 20% no valor total.', 'created_at' => '2025-04-30 20:16:48', 'updated_at' => '2025-04-30 20:16:48'],
            ['id' => 5, 'pergunta' => 'Existe taxa de cancelamento?', 'resposta' => 'Não existe taxa de cancelamento. Em caso de cancelamento, não há devolução de valores pagos anteriormente ou do período em curso.', 'created_at' => '2025-04-30 20:17:24', 'updated_at' => '2025-04-30 20:17:24'],
            ['id' => 6, 'pergunta' => 'O que acontece ao fim dos 07 dias gratuitos?', 'resposta' => 'O valor será cobrado na modalidade de pagamento e plano escolhido anteriormente.', 'created_at' => '2025-04-30 20:17:54', 'updated_at' => '2025-04-30 20:17:54'],
            ['id' => 7, 'pergunta' => 'Quais as formas de pagamento?', 'resposta' => 'Cartão de crédito e pix recorrente.', 'created_at' => '2025-04-30 20:18:24', 'updated_at' => '2025-04-30 20:18:24'],
            ['id' => 8, 'pergunta' => 'Existe aplicativo ou apenas a versão web?', 'resposta' => 'Apenas a versão web no momento.', 'created_at' => '2025-04-30 20:19:44', 'updated_at' => '2025-04-30 20:19:44'],
            ['id' => 9, 'pergunta' => 'Consigo salvar e/ou compartilhar as imagens comparadas?', 'resposta' => 'Sim. Após realizar a comparação de imagens, ficarão disponíveis os botões “salvar” e “compartilhar”.', 'created_at' => '2025-04-30 20:20:11', 'updated_at' => '2025-04-30 20:20:11'],
            ['id' => 10, 'pergunta' => 'Como posso entrar em contato?', 'resposta' => 'Através do e-mail: contato@comppare.com.br ou das nossas redes sociais: Instagram: comppare.br | Linkedin: linkedin.com/comppare', 'created_at' => '2025-04-30 20:20:56', 'updated_at' => '2025-04-30 20:20:56'],
            ['id' => 11, 'pergunta' => 'O que são os dados de uso?', 'resposta' => 'É um resumo de uso do usuário dentro da plataforma, mostrando dados como número de álbuns, subábuns, imagens entre outros.', 'created_at' => '2025-04-30 20:21:13', 'updated_at' => '2025-04-30 20:21:13'],
            ['id' => 12, 'pergunta' => 'Quais as diferenças entre os planos?', 'resposta' => "- Gratuito: \n01 pasta*\n03 subpastas*\n03 tags*\nCom anúncios\nCompartilhamento em redes sociais\n\n- Básico:\n10 pastas*\n06 subpastas*\n06 tags*\nSem anúncios\nCompartilhamento em redes sociais\nGameficação\nDados de uso\n\n- Avançado: \n20 pastas*\n10 subpastas*\n10 tags*\nSem anúncios\nCompartilhamento em redes sociais\nGameficação\nDados de uso\nDivulgação nas redes sociais da empresa", 'created_at' => '2025-05-03 20:19:24', 'updated_at' => '2025-05-03 20:19:24'],
            ['id' => 13, 'pergunta' => 'O que é o ranking? Como funcionam as pontuações?', 'resposta' => "É a classificação dos usuários da plataforma de acordo com a tabela de pontuação abaixo:\n- Criação de álbum ou subálbum: 1 pto\n- Anexou foto com tag/categoria: 2 ptos\n- Usou o botão “comppare”: 2 ptos\n- Baixou imagem: 5 ptos\n- Compartilhou imagem com redes sociais: 20 ptos\n- bônus: resposta de forms de sugestões/melhorias em suporte: 5 ptos", 'created_at' => '2025-05-03 20:22:20', 'updated_at' => '2025-05-03 20:22:20'],
        ]);
    }
}
