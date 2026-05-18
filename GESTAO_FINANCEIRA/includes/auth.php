<?php
// includes/auth.php
// Autenticação, sessão e verificação de papel (admin / user).

// Inicia a sessão (se ainda não estiver iniciada)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verifica se o utilizador está autenticado.
 */
function is_logged_in(): bool
{
    return isset($_SESSION['utilizador_id']);
}

/**
 * Verifica se a sessão atual é de administrador.
 * Nota: o valor é atualizado em require_login() com dados frescos da BD.
 */
function is_admin(): bool
{
    return is_logged_in() && (($_SESSION['utilizador_role'] ?? 'user') === 'admin');
}

/**
 * Caminho relativo para a página de login (funciona a partir de /pages/ e /admin/).
 */
function auth_login_url(): string
{
    return '../auth/login.php';
}

/**
 * Garante que a página só é acessível por utilizadores autenticados com conta ativa.
 * Atualiza role na sessão a partir da BD (útil se o admin alterar papéis).
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: ' . auth_login_url());
        exit;
    }

    require_once __DIR__ . '/../config/db.php';
    $pdo = getPDO();

    $stmt = $pdo->prepare('SELECT role, conta_suspensa FROM utilizadores WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $_SESSION['utilizador_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || (int)($row['conta_suspensa'] ?? 0) === 1) {
        session_unset();
        session_destroy();
        header('Location: ' . auth_login_url() . '?suspenso=1');
        exit;
    }

    $role = $row['role'] ?? 'user';
    if ($role !== 'admin' && $role !== 'user') {
        $role = 'user';
    }
    $_SESSION['utilizador_role'] = $role;
}

/**
 * Apenas administradores. Utilizadores normais são enviados para a área da app.
 */
function require_admin(): void
{
    require_login();

    if (($_SESSION['utilizador_role'] ?? 'user') !== 'admin') {
        header('Location: ../pages/dashboard.php');
        exit;
    }
}

/**
 * Sincroniza o papel na sessão.
 * Se a conta não existir ou estiver suspensa, termina a sessão e devolve false.
 */
function auth_sync_role_from_db(): bool
{
    if (!is_logged_in()) {
        return false;
    }
    require_once __DIR__ . '/../config/db.php';
    $pdo = getPDO();
    $stmt = $pdo->prepare('SELECT role, conta_suspensa FROM utilizadores WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $_SESSION['utilizador_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || (int)($row['conta_suspensa'] ?? 0) === 1) {
        session_unset();
        session_destroy();

        return false;
    }
    $role = $row['role'] ?? 'user';
    $_SESSION['utilizador_role'] = ($role === 'admin') ? 'admin' : 'user';

    return true;
}
