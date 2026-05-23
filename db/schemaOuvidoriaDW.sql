-- ============================================================
-- schemaOuvidoriaDw.sql — Schema completo do banco de dados
-- Ouvidoria Escolar · EEEP Dom Walfrido Vieira Teixeira
--
-- Execute este arquivo único para criar toda a estrutura:
--   SOURCE db/schemaOuvidoriaDw.sql;
--
-- Servidor: MariaDB 10.4+ ou MySQL 5.7+
-- Charset:  utf8mb4 (suporte a emojis e caracteres especiais)
-- ============================================================

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- ── Banco de dados ───────────────────────────────────────────
CREATE DATABASE IF NOT EXISTS `dbouvidoria`
  /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `dbouvidoria`;

-- ── 1. tbadm — membros do Grêmio com acesso ao painel ────────
CREATE TABLE IF NOT EXISTS `tbadm` (
  `IDadm`     int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome`      varchar(80)  NOT NULL,
  `cargo`     varchar(80)  DEFAULT NULL COMMENT 'Ex: Presidente, Secretário',
  `email`     varchar(200) NOT NULL,
  `senha`     varchar(255) NOT NULL COMMENT 'bcrypt hash — NUNCA em texto puro',
  `ativo`     tinyint(1)   NOT NULL DEFAULT 1,
  `criado_em` datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IDadm`),
  UNIQUE KEY `uq_adm_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 2. tbsetores — setores/órgãos da escola ──────────────────
CREATE TABLE IF NOT EXISTS `tbsetores` (
  `IDsetor`   int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome`      varchar(80)  NOT NULL  COMMENT 'Nome do setor/órgão',
  `descricao` varchar(200) DEFAULT NULL COMMENT 'Descrição opcional',
  `ativo`     tinyint(1)   NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=desativado',
  `criado_em` datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IDsetor`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Setores/órgãos da escola que recebem manifestações';

INSERT INTO `tbsetores` (`IDsetor`, `nome`, `descricao`, `ativo`, `criado_em`) VALUES
  (1, 'Direção',           'Direção geral da escola',             1, NOW()),
  (2, 'Coordenação',       'Coordenação pedagógica',              1, NOW()),
  (3, 'Grêmio Estudantil', 'Representação dos estudantes',        1, NOW()),
  (4, 'Biblioteca',        'Biblioteca e acervo escolar',         1, NOW()),
  (5, 'Cozinha',           'Equipe de alimentação e refeitório',  1, NOW()),
  (6, 'Corpo Docente',     'Professores e equipe de ensino',      1, NOW());

-- ── 3. tbusuarios — alunos cadastrados ───────────────────────
CREATE TABLE IF NOT EXISTS `tbusuarios` (
  `IDusu`     int(11) unsigned NOT NULL AUTO_INCREMENT,
  `nome`      varchar(80)  NOT NULL,
  `serie`     int(1)       DEFAULT NULL COMMENT 'Série/ano escolar',
  `curso`     varchar(60)  DEFAULT NULL,
  `matricula` varchar(40)  DEFAULT NULL,
  `email`     varchar(200) NOT NULL,
  `senha`     varchar(255) NOT NULL COMMENT 'bcrypt hash — NUNCA em texto puro',
  `ativo`     tinyint(1)   NOT NULL DEFAULT 1 COMMENT '1=ativo, 0=bloqueado',
  `criado_em` datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`IDusu`),
  UNIQUE KEY `uq_email` (`email`)
  -- Nota: UNIQUE em matricula foi removido pois o campo é opcional (NULL).
  -- Dois alunos sem matrícula causariam conflito de chave única.
  -- O e-mail já garante unicidade.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. tipos — tipos de manifestação ─────────────────────────
CREATE TABLE IF NOT EXISTS `tipos` (
  `IDtipo`    int(11) unsigned NOT NULL AUTO_INCREMENT,
  `descricao` varchar(60) NOT NULL,
  PRIMARY KEY (`IDtipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tipos` (`IDtipo`, `descricao`) VALUES
  (1, 'Denúncia'),
  (2, 'Reclamação'),
  (3, 'Sugestão'),
  (4, 'Elogio'),
  (5, 'Solicitação');

-- ── 5. tbmanifest — manifestações registradas ────────────────
CREATE TABLE IF NOT EXISTS `tbmanifest` (
  `IDmanifest`   int(11) unsigned NOT NULL AUTO_INCREMENT,
  `protocolo`    varchar(20)  NOT NULL    COMMENT 'Protocolo público para rastreamento',
  `IDusu`        int(11) unsigned DEFAULT NULL COMMENT 'NULL = manifestação anônima',
  `IDadm`        int(11) unsigned DEFAULT NULL COMMENT 'NULL = sem atendente atribuído',
  `IDtipo`       int(11) unsigned NOT NULL,
  `IDsetor`      int(11) unsigned DEFAULT NULL,
  `anonimo`      tinyint(1)   NOT NULL DEFAULT 0 COMMENT '1 = anônima',
  `manifest`     text         NOT NULL,
  `STATUS`       enum('Aberta','Em análise','Respondida','Encerrada')
                              NOT NULL DEFAULT 'Aberta',
  `feedback`     mediumtext   DEFAULT NULL,
  `contato`      varchar(150) DEFAULT NULL COMMENT 'Contato opcional (só identificadas)',
  `criado_em`    datetime     NOT NULL DEFAULT current_timestamp(),
  `atualizado_em` datetime    NOT NULL DEFAULT current_timestamp()
                              ON UPDATE current_timestamp(),
  PRIMARY KEY (`IDmanifest`),
  UNIQUE KEY `uq_protocolo` (`protocolo`),
  KEY `idusu`       (`IDusu`),
  KEY `idadm`       (`IDadm`),
  KEY `idtipo`      (`IDtipo`),
  KEY `idx_status`  (`STATUS`),
  KEY `idx_setor`   (`IDsetor`),
  KEY `idx_criado`  (`criado_em`),
  CONSTRAINT `fk_manifest_adm`   FOREIGN KEY (`IDadm`)   REFERENCES `tbadm`      (`IDadm`)   ON DELETE SET NULL,
  CONSTRAINT `fk_manifest_setor` FOREIGN KEY (`IDsetor`) REFERENCES `tbsetores`  (`IDsetor`) ON DELETE SET NULL,
  CONSTRAINT `fk_manifest_tipo`  FOREIGN KEY (`IDtipo`)  REFERENCES `tipos`      (`IDtipo`),
  CONSTRAINT `fk_manifest_usu`   FOREIGN KEY (`IDusu`)   REFERENCES `tbusuarios` (`IDusu`)   ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. password_resets — tokens de recuperação de senha ──────
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`        int(11) unsigned NOT NULL AUTO_INCREMENT,
  `email`     varchar(200) NOT NULL,
  `token`     varchar(100) NOT NULL,
  `expira_em` datetime     NOT NULL,
  `usado`     tinyint(1)   NOT NULL DEFAULT 0,
  `criado_em` datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_token` (`token`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Tokens temporários para recuperação de senha';

-- ── 7. log_acesso — auditoria de ações no sistema ────────────
CREATE TABLE IF NOT EXISTS `log_acesso` (
  `id`         int(11) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) unsigned DEFAULT NULL COMMENT 'NULL = ação anônima',
  `acao`       varchar(100) NOT NULL    COMMENT 'Ex: manifestacao:42, adm:login:1',
  `ip`         varchar(45)  DEFAULT NULL COMMENT 'IPv4 ou IPv6',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'Navegador e SO',
  `criado_em`  datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_acao`    (`acao`),
  KEY `idx_criado`  (`criado_em`),
  CONSTRAINT `fk_log_usuario` FOREIGN KEY (`usuario_id`)
    REFERENCES `tbusuarios` (`IDusu`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Auditoria de ações realizadas no sistema';

-- ── 8. tbmanifest_arquivos — arquivos anexados ───────────────
CREATE TABLE IF NOT EXISTS `tbmanifest_arquivos` (
  `id`            int(11) unsigned NOT NULL AUTO_INCREMENT,
  `IDmanifest`    int(11) unsigned NOT NULL  COMMENT 'Manifestação à qual pertence',
  `nome_original` varchar(255)     NOT NULL  COMMENT 'Nome original (exibição)',
  `nome_salvo`    varchar(100)     NOT NULL  COMMENT 'Nome aleatório gerado no servidor',
  `mime_type`     varchar(100)     NOT NULL  COMMENT 'Tipo real verificado por finfo',
  `tamanho`       int(11) unsigned NOT NULL  COMMENT 'Tamanho em bytes',
  `criado_em`     datetime         NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_manifest` (`IDmanifest`),
  CONSTRAINT `fk_arquivo_manifest` FOREIGN KEY (`IDmanifest`)
    REFERENCES `tbmanifest` (`IDmanifest`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Arquivos anexados às manifestações';

-- ── 9. v_manifestacoes — view para consultas ─────────────────
CREATE TABLE `v_manifestacoes` (
  `IDmanifest`  INT(11) UNSIGNED NOT NULL,
  `protocolo`   VARCHAR(1) NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `anonimo`     TINYINT(1) NOT NULL,
  `autor_nome`  VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
  `autor_email` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
  `autor_serie` INT(11)    NULL,
  `autor_curso` VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
  `tipo`        VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
  `setor`       VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
  `manifest`    TEXT NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `STATUS`      ENUM('Aberta','Em análise','Respondida','Encerrada') NOT NULL COLLATE 'utf8mb4_unicode_ci',
  `feedback`    MEDIUMTEXT NULL COLLATE 'utf8mb4_unicode_ci',
  `contato`     VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
  `atendente`   VARCHAR(1) NULL COLLATE 'utf8mb4_unicode_ci',
  `criado_em`   DATETIME NOT NULL,
  `atualizado_em` DATETIME NOT NULL
);

DROP TABLE IF EXISTS `v_manifestacoes`;
CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_manifestacoes` AS
SELECT
  m.IDmanifest,
  m.protocolo,
  m.anonimo,
  CASE WHEN m.anonimo = 1 THEN 'Anônimo' ELSE u.nome  END AS autor_nome,
  CASE WHEN m.anonimo = 1 THEN NULL       ELSE u.email END AS autor_email,
  CASE WHEN m.anonimo = 1 THEN NULL       ELSE u.serie END AS autor_serie,
  CASE WHEN m.anonimo = 1 THEN NULL       ELSE u.curso END AS autor_curso,
  t.descricao  AS tipo,
  s.nome       AS setor,
  m.manifest,
  m.STATUS,
  m.feedback,
  m.contato,
  a.nome       AS atendente,
  m.criado_em,
  m.atualizado_em
FROM `tbmanifest` m
LEFT JOIN `tbusuarios` u ON m.IDusu   = u.IDusu
LEFT JOIN `tbadm`      a ON m.IDadm   = a.IDadm
LEFT JOIN `tipos`      t ON m.IDtipo  = t.IDtipo
LEFT JOIN `tbsetores`  s ON m.IDsetor = s.IDsetor;

-- ── Restaurar configurações ───────────────────────────────────
/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
