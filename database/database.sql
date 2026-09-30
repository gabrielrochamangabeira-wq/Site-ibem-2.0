-- ========================================================
-- BANCO DE DADOS: PORTAL ESCOLAR / SERVIDOR IBEM
-- Instituto Brasileiro de Educação Moderna (IBEM)
-- ========================================================

CREATE DATABASE IF NOT EXISTS `portal_escolar` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `portal_escolar`;

-- --------------------------------------------------------
-- 1. TABELA DE USUÁRIOS (Admin, Professores e Alunos)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(255) NOT NULL,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `senha` VARCHAR(255) NOT NULL,
  `tipo` ENUM('admin', 'professor', 'aluno') NOT NULL DEFAULT 'aluno',
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 2. TABELA DE MATERIAIS DIDÁTICOS E APOSTILAS
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `materiais` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(255) NOT NULL,
  `arquivo` VARCHAR(255) NOT NULL,
  `professor_id` INT(11) DEFAULT NULL,
  `criado_em` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_materiais_professor` (`professor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 3. TABELA DE MÓDULOS DAS AULAS (EAD / Plataforma)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `modulos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `titulo` VARCHAR(255) NOT NULL,
  `texto` LONGTEXT DEFAULT NULL,
  `ordem` INT(11) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 4. TABELA DE PROGRESSO E CONCLUSÃO DO ALUNO
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `progresso_aluno` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario` VARCHAR(255) NOT NULL,
  `modulo_id` INT(11) NOT NULL,
  `liberado` TINYINT(1) DEFAULT 1,
  `concluido` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_progresso_usuario` (`usuario`),
  KEY `idx_progresso_modulo` (`modulo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 5. TABELA DE SOLICITAÇÕES DE AULAS (Dashboard do Admin)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `solicitacoes_aulas` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `usuario` VARCHAR(255) NOT NULL,
  `modulo_id` INT(11) NOT NULL,
  `status` ENUM('pendente', 'aprovada', 'rejeitada') NOT NULL DEFAULT 'pendente',
  `data_solicitacao` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `data_resposta` TIMESTAMP NULL DEFAULT NULL,
  `observacao` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_solic_usuario` (`usuario`),
  KEY `idx_solic_modulo` (`modulo_id`),
  KEY `idx_solic_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- 6. CARGA DE MÓDULOS DE EXEMPLO
-- --------------------------------------------------------
INSERT INTO `modulos` (`id`, `titulo`, `texto`, `ordem`) VALUES
(1, 'Módulo 1 - Introdução e Fundamentos', 'Bem-vindo ao primeiro módulo do seu curso no IBEM! Neste módulo abordaremos os princípios fundamentais da sua profissão, ética no trabalho e postura de sucesso.', 1),
(2, 'Módulo 2 - Prática Operacional e Ferramentas', 'Neste módulo prático, você conhecerá os métodos operacionais, segurança e ferramentas essenciais para sua atuação no mercado.', 2),
(3, 'Módulo 3 - Preparação para o Mercado de Trabalho', 'Aprenda a montar um currículo profissional, realizar entrevistas e conectar-se com oportunidades em empresas parceiras do IBEM.', 3)
ON DUPLICATE KEY UPDATE `id`=`id`;

-- --------------------------------------------------------
-- 7. USUÁRIOS PADRÃO PARA TESTES
-- --------------------------------------------------------
-- Administrador: admin@ibem.com.br / admin123
-- Aluno Teste: aluno@ibem.com.br / aluno123
INSERT INTO `usuarios` (`id`, `nome`, `email`, `senha`, `tipo`) VALUES
(1, 'Administrador IBEM', 'admin@ibem.com.br', '$2y$10$X86xZ1hD.65kS2l8Y4fO8Oz4aCqB5u3jZ.0v4q6.q0XmQ0pG9hK6G', 'admin'),
(2, 'Aluno Teste IBEM', 'aluno@ibem.com.br', '$2y$10$.j9LhlMq/6F/UhDvwUXADeIq2EnFEJzvdlZNdc5XDKnavcK2bKnmK', 'aluno')
ON DUPLICATE KEY UPDATE `id`=`id`;

-- Libera o primeiro módulo para o aluno de teste
INSERT INTO `progresso_aluno` (`usuario`, `modulo_id`, `liberado`, `concluido`) VALUES
('Aluno Teste IBEM', 1, 1, 0)
ON DUPLICATE KEY UPDATE `id`=`id`;


