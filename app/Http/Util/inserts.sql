INSERT INTO `planos` (`id`, `nome`, `descricao`, `valor`, `tempoGratuidade`, `quantidadeTags`, `quantidadeFotos`, `quantidadePastas`, `quantidadeSubpastas`, `frequenciaCobranca`, `quantidadeConvites`, `status`, `idHost`, `exibicao`, `created_at`, `updated_at`) VALUES
(1, 'Plano Gratuito', 'Plano Gratuito', 0, 360, 3, 50, 1, 3, 0, 0, 1, NULL, 1, NULL, NULL),
(2, 'Plano de Afiliados', 'Plano de Afiliados', 0, 180, 10, 200, 20, 6, 0, 20, 1, NULL, 0, NULL, NULL),
(3, 'Plano Avançado', 'Plano Avançado', 29.9, 0, 10, 200, 20, 6, 1, 20, 1, 122814, 1, '2025-03-18 14:51:14', '2025-03-18 14:51:14'),
(4, 'Plano Avançado Anual', 'Plano Avançado Anual', 287, 0, 10, 2, 20, 6, 12, 20, 1, 122815, 1, '2025-03-18 14:53:11', '2025-03-18 14:53:11'),
(5, 'Plano de Convidados', 'Plano de Convidados', 0, 360, 3, 50, 1, 3, 0, 0, 1, NULL, 0, '2025-03-25 19:32:44', '2025-03-25 19:33:00'),
(6, 'Teste plano EFI', 'Teste', 5, 0, 1, 2, 1, 5, 12, 0, 1, 123617, 1, '2025-08-05 18:18:33', '2025-08-05 18:18:33');

--
INSERT INTO `perfil` (`id`, `nome_perfil`, `created_at`, `updated_at`) VALUES
(1, 'Administrativo', NULL, NULL),
(2, 'Usuário', NULL, NULL),
(3, 'Convidados', NULL, NULL);

INSERT INTO `usuarios` (`id`, `primeiroNome`, `sobrenome`, `apelido`, `cpf`, `senha`, `email`, `telefone`, `dataNascimento`, `status`, `dataLimiteCompra`, `dataUltimoPagamento`, `idUltimaCobranca`, `idAssinatura`, `pastasCriadas`, `pontos`, `quantidadeConvites`, `ultimoAcesso`, `idPlano`, `idPerfil`, `created_at`, `updated_at`) VALUES
(1, 'Luis Felipe', 'Araujo Pimenta', 'luispimenta', '02342288140', '$2y$12$xBo0paVUrCJV97elSFAYj.WTf7A0XOeqe27x5L0Q8vyixTkLHUlba', 'luispimenta.contato@gmail.com', '61998690313', '1995-09-19', 1, '2025-08-12 00:00:00', NULL, 891866770, 1347781, 1, 0, 0, '2025-08-07 17:50:04', 6, 2, '2025-07-24 21:08:28', '2025-08-07 17:50:04'),
(2, 'thiago gomes da cunha', 'cunha', 'GomesTH', '12929503742', '$2y$12$vLT9VXMbUa8QeqfNDAyTPuEW5pYEVx0H1kf7eq0vsVRhYz8MgjL0G', 'contatodev.thiago@gmail.com', '21976086757', '1988-12-07', 1, '2026-01-20 00:00:00', NULL, NULL, NULL, -11, 0, 0, '2025-08-07 17:26:25', 2, 2, '2025-07-24 23:05:56', '2025-08-07 17:26:25'),
(3, 'Andrew', 'Vieira', 'andrewcomppare', '14248350700', '$2y$12$b376WHpsNiCIxV68TjmE0eWcW08b9K2R9AZe3KVBlcESOYxwFrCBu', 'andrew.vieira@comppare.com.br', '21995360630', '1994-05-17', 1, '2026-07-20 00:00:00', NULL, NULL, NULL, -4, 0, 0, '2025-08-07 12:55:51', 1, 2, '2025-07-25 11:55:24', '2025-08-07 12:55:51'),
(4, 'Jeniffer Rodrigues', 'cunha', 'Preta', '29990521700', '$2y$12$lFXEkVC/DoW.SGWCS0Oeo.S8tkWCUhDp7D8gchUG84nn3q1C4lwp2', 'test@gmail.com', '21976086788', '1988-12-07', 1, '2026-01-21 00:00:00', NULL, NULL, NULL, 0, 0, 0, NULL, 2, 2, '2025-07-25 13:59:53', '2025-07-25 13:59:53'),
(5, 'ABIMAEL', 'DE ANDRADE SILVA', 'ABIMAEL', '01940300592', '$2y$12$lLZbNx.MpWjxuk0Z4XgmSe7B1m8bl.m3vEOzheWJGqJjMzQUcZvqK', 'abimael.rza@gmail.com', '73999586629', '1984-05-24', 1, '2026-07-27 00:00:00', NULL, NULL, NULL, 0, 0, 0, '2025-08-06 15:13:23', 1, 2, '2025-08-01 12:01:15', '2025-08-06 15:13:23'),
(6, 'ABIMAEL', 'ANDRADE', 'ABIMAEL', '80652801021', '$2y$12$i0IkmvRHKz6m.GRlclum9.I4edZ3ahA3A4nloBcTQfURzoZSuSbse', 'ABIMAEL.RZA2@GMAIL.COM', '73999586628', '1984-05-24', 1, '2026-07-29 00:00:00', NULL, NULL, NULL, 0, 0, 0, NULL, 1, 2, '2025-08-03 20:26:59', '2025-08-03 20:26:59'),
(7, 'ABIMAEL', 'ANDRADE', 'ABIMAEL', '67393153025', '$2y$12$k4M1c4YdtO/iTsk5xqayVuzhbMlv8a2R4VabtyQ7rsdm.hggXiley', 'ABIMAEL.RZA3@GMAIL.COM', '73999586627', '1984-05-24', 1, '2026-07-29 00:00:00', NULL, NULL, NULL, 1, 0, 0, '2025-08-07 15:52:42', 1, 2, '2025-08-03 20:37:48', '2025-08-07 15:52:42'),
(8, 'ABIMAEL', 'ANDRADDE', 'ABIMAEL', '96616839052', '$2y$12$VC18nC7LhBfQeOICCpWZh.OFo88EKLcE/AC./4HoW143VuP3VbsQi', 'ABIMAEL.RZA5@GMAIL.COM', '73999586626', '1984-03-01', 1, '2025-08-13 00:00:00', NULL, 891887764, 1347820, 1, 0, 0, '2025-08-07 15:34:00', 4, 2, '2025-08-03 20:41:20', '2025-08-07 15:34:00'),
(9, 'abimael', 'andrade', 'abimael', '71268005096', '$2y$12$uHi76nmFRtjVjC5rwe0yt.BU8ZtPEtnWfujXRQfsdAMDt490r.Z86', 'abimael.rza7@gmail.com', '73999586624', '1955-01-01', 1, '2026-08-01 00:00:00', NULL, NULL, NULL, 0, 0, 0, '2025-08-06 15:29:00', 1, 2, '2025-08-06 15:28:25', '2025-08-06 15:29:00'),
(10, 'Nathalia', 'Muratori', 'Nath', '16749476740', '$2y$12$gsAWl.bocfSyLdi.Vkp33.fNeyzM8fvPPfHVfNCMSFSDWbopVcrAS', 'nathalia.muratori@gmail.com', '21972292504', '1996-03-30', 1, '2026-08-01 00:00:00', NULL, NULL, NULL, 0, 0, 0, '2025-08-06 17:54:10', 1, 2, '2025-08-06 17:52:01', '2025-08-06 17:54:10'),
(11, 'Fulano', 'Cicrano', 'Fu', '45648951094', '$2y$12$ILzU/EYM3yDi6B1C5eVG4.uHH0.8t3fFjvDI7WigIhex.kbYJSFgy', 'ro.pib.05@gmail.com', '73999095862', '2000-01-09', 1, '2026-08-02 00:00:00', NULL, NULL, NULL, 0, 0, 0, NULL, 1, 2, '2025-08-07 15:17:06', '2025-08-07 15:17:06'),
(12, 'Gato', 'Mil', 'Mil', '50985669080', '$2y$12$kqKED11yNn1jV.nb2DHZUOkaQF8AooJBAxnRDZMijvvwPYUq7gRO6', 'ro.pib.02@gmail.com', '73999526874', '2000-01-21', 1, '2026-02-03 00:00:00', NULL, NULL, NULL, 0, 0, 0, NULL, 2, 2, '2025-08-07 15:27:45', '2025-08-07 15:27:45'),
(13, 'TOF', 'THE DOG - TESTE ABIMAEL', 'TOF', '87952133098', '$2y$12$.DenvDqKrKtqtd3GWB2.FO381e9M9PMQgCh6sY49D1BjZbR5LC39e', 'tof@gmail.com', '73999583333', '2000-01-01', 1, '2026-08-02 00:00:00', NULL, NULL, NULL, 0, 0, 0, NULL, 1, 2, '2025-08-07 15:44:09', '2025-08-07 15:44:09'),
(14, 'TOF', 'THE DOG - ABIMAEL TESTE', 'TOF', '23698356058', '$2y$12$1WFdREpdJlPqtmHYF/UYO.yTFnZ0hb6dDpFqW3IvUPED803c6WWDO', 'tof2@gmail.com', '73999586633', '2000-01-01', 1, '2026-02-03 00:00:00', NULL, NULL, NULL, 0, 0, 0, NULL, 2, 2, '2025-08-07 15:46:09', '2025-08-07 15:46:09');



update  planos set planos.exibicao = 0 where planos.id in (2,5);

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
