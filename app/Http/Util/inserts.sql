INSERT INTO `planos` (`id`, `nome`, `descricao`, `valor`, `tempoGratuidade`, `quantidadeTags`, `quantidadeFotos`, `quantidadePastas`, `frequenciaCobranca`, `status`, `idHost`, `created_at`, `updated_at`, `quantidadeConvites`)
VALUES

(1, 'Plano Gratuito', 'Plano Gratuito', 0, 360, 3, 5, 4, 0, 1, NULL, NULL, NULL, 0),
(2, 'Plano de Filiados', 'Plano de Filiados', 0, 180, 10, 15, 40, 0, 1, NULL, NULL, NULL, 0),
(3, 'Plano Básico', 'Plano Básico', 24.9, 15, 6, 2, 20, 1, 1, 122812, '2025-03-18 14:45:59', '2025-03-18 14:45:59', 0),
(4, 'Plano Básico Anual', 'Plano Básico Anual', 239, 15, 6, 2, 20, 12, 1, 122813, '2025-03-18 14:48:42', '2025-03-18 14:48:42', 0),
(5, 'Plano Avançado', 'Plano Avançado', 39.9, 15, 10, 2, 40, 1, 1, 122814, '2025-03-18 14:51:14', '2025-03-18 14:51:14', 0),
(6, 'Plano Avançado Anual', 'Plano Avançado Anual', 383, 15, 10, 2, 40, 12, 1, 122815, '2025-03-18 14:53:11', '2025-03-18 14:53:11', 0),
(7, 'Plano de Convidados', 'Plano de Convidados', 0, 360, 0, 0, 0, 0, 1, NULL, '2025-03-25 19:32:44', '2025-03-25 19:33:00', 0);


INSERT INTO `perfil` (`id`, `nome_perfil`, `created_at`, `updated_at`) VALUES
(1, 'Administrativo', NULL, NULL),
(2, 'Usuário', NULL, NULL),
(3, 'Convidados', NULL, NULL);


update  planos set planos.exibicao = 0 where planos.id in (2,7);

INSERT INTO `questions` VALUES
                            (1,'O que são os álbuns e subálbuns?','Álbum: É a pasta principal onde você irá organizar os subálbuns.Subálbum: É a pasta secundária onde serão armazenadas as fotos de um tema específico do álbum. Exemplo: Álbum: casa | Subálbuns: sala, quarto, banheiro, área externa, piscina etc','2025-04-30 20:08:16','2025-04-30 20:08:16'),
                            (2,'O que são as categorias?','As categorias são campos para você inserir informações referente a foto selecionada. Os campos são livres para atender a sua necessidade. Exemplos: Data, peso, altura, medida, profundidade, porcentagem, quantidade etc','2025-04-30 20:12:18','2025-04-30 20:12:18'),
                            (3,'Quais são os planos existentes?','Hoje, os planos existentes são: gratuito, básico e avançado.','2025-04-30 20:13:10','2025-04-30 20:13:10'),
                            (4,'Qual a diferença do mensal para o anual?','A diferença entre os planos, é que no plano anual, você possui um desconto de 20% no valor total.','2025-04-30 20:16:48','2025-04-30 20:16:48'),
                            (5,'Existe taxa de cancelamento?','Não existe taxa de cancelamento. Em caso de cancelamento, não há devolução de valores pagos anteriormente ou do período em curso.','2025-04-30 20:17:24','2025-04-30 20:17:24'),
                            (6,'O que acontece ao fim dos 07 dias gratuitos?','O valor será cobrado na modalidade de pagamento e plano escolhido anteriormente.','2025-04-30 20:17:54','2025-04-30 20:17:54'),
                            (7,'Quais as formas de pagamento?','Cartão de crédito e pix recorrente.','2025-04-30 20:18:24','2025-04-30 20:18:24'),
                            (8,'Existe aplicativo ou apenas a versão web?','Apenas a versão web no momento.','2025-04-30 20:19:44','2025-04-30 20:19:44'),
                            (9,'Consigo salvar e/ou compartilhar as imagens comparadas?','Sim. Após realizar a comparação de imagens, ficarão disponíveis os botões “salvar” e “compartilhar”.','2025-04-30 20:20:11','2025-04-30 20:20:11'),
                            (10,'Como posso entrar em contato?','Através do e-mail: contato@comppare.com.br ou das nossas redes sociais: Instagram: comppare.br | Linkedin: linkedin.com/comppare','2025-04-30 20:20:56','2025-04-30 20:20:56'),
                            (11,'O que são os dados de uso?','É um resumo de uso do usuário dentro da plataforma, mostrando dados como número de álbuns, subábuns, imagens entre outros.','2025-04-30 20:21:13','2025-04-30 20:21:13'),
                            (12,'Quais as diferenças entre os planos?','- Gratuito: \n01 pasta*\n03 subpastas*\n03 tags*\nCom anúncios\nCompartilhamento em redes sociais\n\n- Básico:\n10 pastas*\n06 subpastas*\n06 tags*\nSem anúncios\nCompartilhamento em redes sociais\nGameficação\nDados de uso\n\n- Avançado: \n20 pastas*\n10 subpastas*\n10 tags*\nSem anúncios\nCompartilhamento em redes sociais\nGameficação\nDados de uso\nDivulgação nas redes sociais da empresa','2025-05-03 20:19:24','2025-05-03 20:19:24'),
                            (13,'O que é o ranking? Como funcionam as pontuações?','É a classificação dos usuários da plataforma de acordo com a tabela de pontuação abaixo:\n- Criação de álbum ou subálbum: 1 pto\n- Anexou foto com tag/categoria: 2 ptos\n- Usou o botão “comppare”: 2 ptos\n- Baixou imagem: 5 ptos\n- Compartilhou imagem com redes sociais: 20 ptos\n- bônus: resposta de forms de sugestões/melhorias em suporte: 5 ptos','2025-05-03 20:22:20','2025-05-03 20:22:20');
