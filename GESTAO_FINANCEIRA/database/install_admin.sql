-- ============================================================
-- Instalação manual (phpMyAdmin) — Área de administração
-- Base de dados: gestao_financeira (ajusta se necessário)
-- ============================================================
-- O projeto usa a tabela `utilizadores` (não "users").
--
-- A aplicação aplica automaticamente estas alterações na primeira
-- ligação PDO (ficheiro includes/schema_bootstrap.php).
-- Usa este script apenas se preferires importar à mão.
-- ============================================================

-- 1) Colunas em utilizadores (executa uma vez; ignora erro se já existirem)
ALTER TABLE utilizadores ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER tema;
ALTER TABLE utilizadores ADD COLUMN conta_suspensa TINYINT(1) NOT NULL DEFAULT 0 AFTER role;

CREATE TABLE IF NOT EXISTS plataforma_config (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    nome_site VARCHAR(200) NOT NULL DEFAULT 'Gestão Financeira Familiar',
    logo_ficheiro VARCHAR(255) NULL DEFAULT NULL,
    atualizado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO plataforma_config (id, nome_site) VALUES (1, 'Gestão Financeira Familiar');

CREATE TABLE IF NOT EXISTS relatorio_acessos_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT NOT NULL,
    mes TINYINT UNSIGNED NOT NULL,
    ano SMALLINT UNSIGNED NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_util (utilizador_id),
    INDEX idx_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2) Conta admin criada automaticamente pelo PHP com:
--    Email: admin@gestao.local
--    Palavra-passe: Admin123!
-- Para promover um utilizador existente a admin:
--    UPDATE utilizadores SET role = 'admin' WHERE email = 'teu@email.com';
