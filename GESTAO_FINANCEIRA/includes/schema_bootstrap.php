<?php
/**
 * schema_bootstrap.php
 * Garante que a base de dados tem as estruturas necessárias para o modo admin.
 * Corre automaticamente na primeira ligação PDO (config/db.php).
 *
 * O projeto usa a tabela `utilizadores` (equivalente a "users").
 */

declare(strict_types=1);

/**
 * Verifica se uma coluna existe na tabela atual (schema DATABASE()).
 */
function schema_column_exists(PDO $pdo, string $table, string $column): bool
{
    $sql = 'SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :t
              AND COLUMN_NAME = :c';
    $st = $pdo->prepare($sql);
    $st->execute(['t' => $table, 'c' => $column]);

    return (int)$st->fetchColumn() > 0;
}

/**
 * Verifica se uma tabela existe.
 */
function schema_table_exists(PDO $pdo, string $table): bool
{
    $sql = 'SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t';
    $st = $pdo->prepare($sql);
    $st->execute(['t' => $table]);

    return (int)$st->fetchColumn() > 0;
}

/**
 * Aplica alterações idempotentes (podes correr várias vezes sem erro).
 */
function bootstrap_finance_schema(PDO $pdo): void
{
    // --- utilizadores: papel (admin | user) e suspensão de conta ---
    if (!schema_column_exists($pdo, 'utilizadores', 'role')) {
        $pdo->exec("ALTER TABLE utilizadores ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'user' AFTER tema");
    }

    if (!schema_column_exists($pdo, 'utilizadores', 'conta_suspensa')) {
        $pdo->exec('ALTER TABLE utilizadores ADD COLUMN conta_suspensa TINYINT(1) NOT NULL DEFAULT 0 AFTER role');
    }

    // Garantir que todos os registos antigos ficam como utilizador normal
    $pdo->exec("UPDATE utilizadores SET role = 'user' WHERE role = '' OR role IS NULL");

    // --- Configuração global da plataforma (nome, logo) ---
    if (!schema_table_exists($pdo, 'plataforma_config')) {
        $pdo->exec("
            CREATE TABLE plataforma_config (
                id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
                nome_site VARCHAR(200) NOT NULL DEFAULT 'Gestão Financeira Familiar',
                logo_ficheiro VARCHAR(255) NULL DEFAULT NULL,
                atualizado_em TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $pdo->exec("INSERT INTO plataforma_config (id, nome_site) VALUES (1, 'Gestão Financeira Familiar')");
    }

    // --- Log simples de acessos à página de relatório imprimível (para estatísticas admin) ---
    if (!schema_table_exists($pdo, 'relatorio_acessos_log')) {
        $pdo->exec('
            CREATE TABLE relatorio_acessos_log (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                utilizador_id INT NOT NULL,
                mes TINYINT UNSIGNED NOT NULL,
                ano SMALLINT UNSIGNED NOT NULL,
                criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_util (utilizador_id),
                INDEX idx_criado (criado_em)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
    }

    // --- Conta admin por defeito (só cria se o email ainda não existir) ---
    $emailAdmin = 'admin@gestao.local';
    $st = $pdo->prepare('SELECT id FROM utilizadores WHERE email = :e LIMIT 1');
    $st->execute(['e' => $emailAdmin]);
    if (!$st->fetch()) {
        $hash = password_hash('Admin123!', PASSWORD_DEFAULT);
        $ins = $pdo->prepare('
            INSERT INTO utilizadores (nome, email, password, foto_perfil, tema, role, conta_suspensa, data_registo)
            VALUES (:nome, :email, :pw, NULL, :tema, :role, 0, NOW())
        ');
        $ins->execute([
            'nome' => 'Administrador',
            'email' => $emailAdmin,
            'pw' => $hash,
            'tema' => 'dark',
            'role' => 'admin',
        ]);
    }
}
