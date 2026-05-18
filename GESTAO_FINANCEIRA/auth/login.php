<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    if (!auth_sync_role_from_db()) {
        header('Location: login.php?suspenso=1');
        exit;
    }
}
if (is_logged_in()) {
    if (($_SESSION['utilizador_role'] ?? 'user') === 'admin') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../pages/dashboard.php');
    }
    exit;
}

$erro = '';
if (isset($_GET['suspenso'])) {
    $erro = 'A tua conta está suspensa. Contacta o administrador.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $erro = 'Preenche o email e a palavra-passe.';
    } else {
        $pdo = getPDO();

        $stmt = $pdo->prepare('
            SELECT id, nome, email, password, foto_perfil, tema, role, conta_suspensa
            FROM utilizadores
            WHERE email = :email
        ');
        $stmt->execute(['email' => $email]);
        $utilizador = $stmt->fetch();

        if ($utilizador && (int)($utilizador['conta_suspensa'] ?? 0) === 1) {
            $erro = 'Esta conta está suspensa. Contacta o administrador.';
        } elseif ($utilizador && password_verify($password, $utilizador['password'])) {
            $_SESSION['utilizador_id'] = $utilizador['id'];
            $_SESSION['utilizador_nome'] = $utilizador['nome'];
            $_SESSION['utilizador_email'] = $utilizador['email'];
            $_SESSION['utilizador_foto'] = $utilizador['foto_perfil'] ?? '';
            $_SESSION['utilizador_tema'] = $utilizador['tema'] ?? 'escuro';

            $role = $utilizador['role'] ?? 'user';
            $_SESSION['utilizador_role'] = ($role === 'admin') ? 'admin' : 'user';

            if ($_SESSION['utilizador_role'] === 'admin') {
                header('Location: ../admin/dashboard.php');
            } else {
                header('Location: ../pages/dashboard.php');
            }
            exit;
        } else {
            $erro = 'Credenciais inválidas. Verifica o email e a palavra-passe.';
        }
    }
}

$registoSucesso = isset($_GET['registo']) && $_GET['registo'] === 'sucesso';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sessão</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css">
    <link rel="stylesheet" href="../assets/css/neon-theme.css">
    <link rel="stylesheet" href="../assets/css/app-shell.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="theme-dark public-page">
<div class="auth-page">
    <div class="auth-page__visual d-none d-lg-flex">
        <div class="auth-page__visual-inner">
            <a href="../index.php" class="text-decoration-none small fw-semibold mb-4 d-inline-flex align-items-center gap-2" style="color: var(--accent);">
                <i class="bi bi-arrow-left"></i> Voltar ao site
            </a>
            <h2 class="auth-page__headline">Bem-vindo de volta</h2>
            <p class="auth-page__sub">Acede ao teu painel financeiro com o mesmo visual premium em qualquer tema.</p>
        </div>
        <div class="small" style="color: var(--text-tertiary);">Gestão financeira familiar</div>
    </div>
    <div class="auth-page__panel">
        <div class="w-100 d-flex justify-content-end mb-3 px-1">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary rounded-pill dropdown-toggle" type="button" data-bs-toggle="dropdown"><i class="bi bi-palette"></i></button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><a class="dropdown-item" href="#" data-set-theme="dark">Escuro</a></li>
                    <li><a class="dropdown-item" href="#" data-set-theme="light">Claro</a></li>
                    <li><a class="dropdown-item" href="#" data-set-theme="purple">Roxo</a></li>
                    <li><a class="dropdown-item" href="#" data-set-theme="red">Vermelho</a></li>
                    <li><a class="dropdown-item" href="#" data-set-theme="gray">Cinza</a></li>
                </ul>
            </div>
        </div>
        <div class="auth-card">
            <h1 class="auth-card__title">Iniciar sessão</h1>
            <p class="auth-card__subtitle">Introduz as tuas credenciais para continuar.</p>

            <?php if ($registoSucesso): ?>
                <div class="alert alert-success small">Registo concluído. Já podes entrar.</div>
            <?php endif; ?>

            <?php if ($erro): ?>
                <div class="alert alert-danger small"><?php echo htmlspecialchars($erro); ?></div>
            <?php endif; ?>

            <form method="post" action="" class="mt-3">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                           placeholder="nome@email.com" required autocomplete="username">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Palavra-passe</label>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="••••••••" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 mb-3">Entrar</button>
            </form>

            <p class="text-center small text-muted mb-2">Ainda não tens conta? <a href="registo.php">Criar conta</a></p>
            <p class="text-center small mb-0"><a href="../index.php"><i class="bi bi-house-door me-1"></i>Página inicial</a></p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>
