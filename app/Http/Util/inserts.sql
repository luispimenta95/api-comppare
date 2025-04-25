INSERT INTO `planos` (`id`, `nome`, `descricao`, `valor`, `tempoGratuidade`, `quantidadeTags`, `quantidadeFotos`, `quantidadePastas`, `frequenciaCobranca`, `status`, `idHost`, `created_at`, `updated_at`, `quantidadeConvites`)
VALUES

(1, 'Plano Gratuito', 'Plano Gratuito', 0, 360, 3, 5, 4, 0, 1, NULL, NULL, NULL, 0),
(2, 'Plano de Filiados', 'Plano de Filiados', 0, 180, 10, 15, 40, 0, 1, NULL, NULL, NULL, 0),
(3, 'Plano Básico', 'Plano Básico', 19.9, 15, 6, 2, 20, 1, 1, 122812, '2025-03-18 14:45:59', '2025-03-18 14:45:59', 0),
(4, 'Plano Básico Anual', 'Plano Básico Anual', 179.1, 15, 6, 2, 20, 12, 1, 122813, '2025-03-18 14:48:42', '2025-03-18 14:48:42', 0),
(5, 'Plano Avançado', 'Plano Avançado', 39.9, 15, 10, 2, 40, 1, 1, 122814, '2025-03-18 14:51:14', '2025-03-18 14:51:14', 0),
(6, 'Plano Avançado Anual', 'Plano Avançado Anual', 359.1, 15, 10, 2, 40, 12, 1, 122815, '2025-03-18 14:53:11', '2025-03-18 14:53:11', 0),
(7, 'Plano de Convidados', 'Plano de Convidados', 0, 360, 0, 0, 0, 0, 1, NULL, '2025-03-25 19:32:44', '2025-03-25 19:33:00', 0);


INSERT INTO `perfil` (`id`, `nome_perfil`, `created_at`, `updated_at`) VALUES
(1, 'Administrativo', NULL, NULL),
(2, 'Usuário', NULL, NULL),
(3, 'Convidados', NULL, NULL);


update  planos set planos.exibicao = 0 where planos.id in (2,7);
