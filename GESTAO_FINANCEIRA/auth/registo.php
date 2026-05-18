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

$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if ($nome === '' || $email === '' || $password === '' || $password_confirm === '') {
        $erros[] = 'Todos os campos são obrigatórios.';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'O email não é válido.';
    }

    if ($password !== $password_confirm) {
        $erros[] = 'As palavras-passe não coincidem.';
    }

    if (strlen($password) < 6) {
        $erros[] = 'A palavra-passe deve ter pelo menos 6 caracteres.';
    }

    if (empty($erros)) {
        $pdo = getPDO();

        $stmt = $pdo->prepare('SELECT id FROM utilizadores WHERE email = :email');
        $stmt->execute(['email' => $email]);

        if ($stmt->fetch()) {
            $erros[] = 'Já existe um utilizador registado com este email.';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare('
                INSERT INTO utilizadores (nome, email, password, foto_perfil, tema, role, conta_suspensa, data_registo)
                VALUES (:nome, :email, :password, :foto_perfil, :tema, :role, 0, NOW())
            ');

            $ok = $stmt->execute([
                'nome' => $nome,
                'email' => $email,
                'password' => $password_hash,
                'foto_perfil' => null,
                'tema' => 'dark',
                'role' => 'user',
            ]);

            if ($ok) {
                header('Location: login.php?registo=sucesso');
                exit;
            } else {
                $erros[] = 'Ocorreu um erro ao registar. Tenta novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar conta</title>
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
            <h2 class="auth-page__headline">Cria a tua conta</h2>
            <p class="auth-page__sub">Menos de um minuto para começares a organizar rendimentos, despesas e objetivos.</p>
        </div>
        <div class="small" style="color: var(--text-tertiary);">Sem cartão · Projeto académico / local</div>
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
            <h1 class="auth-card__title">Criar conta</h1>
            <p class="auth-card__subtitle">Preenche os dados abaixo para te registares.</p>

            <?php if (!empty($erros)): ?>
                <div class="alert alert-danger small">
                    <ul class="mb-0 ps-3">
                        <?php foreach ($erros as $erro): ?>
                            <li><?php echo htmlspecialchars($erro); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" action="" class="mt-3">
                <div class="mb-3">
                    <label for="nome" class="form-label">Nome</label>
                    <input type="text" class="form-control" id="nome" name="nome"
                           value="<?php echo isset($nome) ? htmlspecialchars($nome) : ''; ?>"
                           placeholder="O teu nome" required autocomplete="name">
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email"
                           value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>"
                           placeholder="nome@email.com" required autocomplete="email">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Palavra-passe</label>
                    <input type="password" class="form-control" id="password" name="password"
                           placeholder="Mínimo 6 caracteres" required autocomplete="new-password">
                </div>
                <div class="mb-3">
                    <label for="password_confirm" class="form-label">Confirmar</label>
                    <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                           placeholder="Repete a palavra-passe" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 mb-3">Registar</button>
            </form>

            <p class="text-center small text-muted mb-2">Já tens conta? <a href="login.php">Iniciar sessão</a></p>
            <p class="text-center small mb-0"><a href="../index.php"><i class="bi bi-house-door me-1"></i>Página inicial</a></p>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/main.js"></script>
</body>
</html>
