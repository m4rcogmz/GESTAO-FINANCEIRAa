<?php
/**
 * Funções auxiliares da área admin (reutilizáveis entre páginas).
 */
declare(strict_types=1);

/**
 * Conta quantos administradores existem na base de dados.
 */
function admin_count_admins(PDO $pdo): int
{
    $n = $pdo->query("SELECT COUNT(*) FROM utilizadores WHERE role = 'admin'")->fetchColumn();

    return (int)$n;
}

/**
 * Apaga todos os dados associados a um utilizador e o próprio registo.
 * Ordem respeitada para evitar erros de integridade referencial.
 */
function admin_apagar_utilizador_e_dados(PDO $pdo, int $userId): void
{
    $pdo->beginTransaction();
    try {
        try {
            $pdo->prepare('DELETE FROM relatorio_acessos_log WHERE utilizador_id = :id')->execute(['id' => $userId]);
        } catch (Throwable $e) {
            // tabela opcional
        }

        $pdo->prepare('DELETE FROM despesas WHERE utilizador_id = :id')->execute(['id' => $userId]);
        $pdo->prepare('DELETE FROM rendimentos WHERE utilizador_id = :id')->execute(['id' => $userId]);
        $pdo->prepare('DELETE FROM orcamentos WHERE utilizador_id = :id')->execute(['id' => $userId]);
        $pdo->prepare('DELETE FROM objetivos WHERE utilizador_id = :id')->execute(['id' => $userId]);
        $pdo->prepare('DELETE FROM utilizadores WHERE id = :id')->execute(['id' => $userId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
