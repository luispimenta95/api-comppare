-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Tempo de geração: 18/03/2025 às 20:08
-- Versão do servidor: 10.11.10-MariaDB-log
-- Versão do PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `u757410616_comppare`
--

-- --------------------------------------------------------

--
-- Estrutura para tabela `planos`
--

CREATE TABLE `planos` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `nome` varchar(255) NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `valor` double NOT NULL,
  `tempoGratuidade` int(11) NOT NULL DEFAULT 15,
  `quantidadeTags` int(11) NOT NULL DEFAULT 5,
  `quantidadeFotos` int(11) NOT NULL DEFAULT 2,
  `quantidadePastas` int(11) NOT NULL DEFAULT 10,
  `frequenciaCobranca` int(11) NOT NULL DEFAULT 1,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `idHost` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Despejando dados para a tabela `planos`
--

INSERT INTO `planos` (`id`, `nome`, `descricao`, `valor`, `tempoGratuidade`, `quantidadeTags`, `quantidadeFotos`, `quantidadePastas`, `frequenciaCobranca`, `status`, `idHost`, `created_at`, `updated_at`) VALUES
(1, 'Plano Gratuito', 'Plano Gratuito', 0, 360, 3, 5, 4, 0, 1, NULL, NULL, NULL),
(2, 'Plano de Filiados', 'Plano de Filiados', 0, 180, 10, 15, 40, 0, 1, NULL, NULL, NULL),
(3, 'Plano Básico', 'Plano Básico', 19.9, 15, 6, 2, 20, 1, 1, 122812, '2025-03-18 14:45:59', '2025-03-18 14:45:59'),
(4, 'Plano Básico Anual', 'Plano Básico Anual', 179.1, 15, 6, 2, 20, 12, 1, 122813, '2025-03-18 14:48:42', '2025-03-18 14:48:42'),
(5, 'Plano Avançado', 'Plano Avançado', 39.9, 15, 10, 2, 40, 1, 1, 122814, '2025-03-18 14:51:14', '2025-03-18 14:51:14'),
(6, 'Plano Avançado Anual', 'Plano Avançado Anual', 359.1, 15, 10, 2, 40, 12, 1, 122815, '2025-03-18 14:53:11', '2025-03-18 14:53:11');

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `planos`
--
ALTER TABLE `planos`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `planos`
--
ALTER TABLE `planos`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
