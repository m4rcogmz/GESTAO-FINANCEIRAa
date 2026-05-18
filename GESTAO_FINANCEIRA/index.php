<?php
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    if (!auth_sync_role_from_db()) {
        header('Location: auth/login.php?suspenso=1');
        exit;
    }
}
if (is_logged_in()) {
    if (($_SESSION['utilizador_role'] ?? 'user') === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: pages/dashboard.php');
    }
    exit;
}

$marcaSiteNome = 'Gestão Financeira Familiar';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/platform_settings.php';
try {
    $marcaSiteNome = plataforma_obter_config(getPDO())['nome_site'];
} catch (Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($marcaSiteNome); ?> — Finanças pessoais</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="assets/css/themes.css">
    <link rel="stylesheet" href="assets/css/neon-theme.css">
    <link rel="stylesheet" href="assets/css/app-shell.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="theme-dark public-page">
<header class="landing-nav">
    <div class="container py-3 d-flex align-items-center justify-content-between gap-3 flex-wrap">
        <a class="navbar-brand m-0 fw-bold text-decoration-none" href="index.php" style="color: var(--heading-color); letter-spacing: -0.03em;">
            <?php echo htmlspecialchars($marcaSiteNome); ?>
        </a>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill px-3" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-palette me-1"></i> Tema
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><a class="dropdown-item" href="#" data-set-theme="dark">Escuro</a></li>
                    <li><a class="dropdown-item" href="#" data-set-theme="light">Claro</a></li>
                    <li><a class="dropdown-item" href="#" data-set-theme="purple">Roxo</a></li>
                    <li><a class="dropdown-item" href="#" data-set-theme="red">Vermelho</a></li>
                    <li><a class="dropdown-item" href="#" data-set-theme="gray">Cinza</a></li>
                </ul>
            </div>
            <a class="btn btn-sm btn-outline-secondary rounded-pill px-3" href="auth/login.php">Entrar</a>
            <a class="btn btn-sm btn-primary rounded-pill px-3" href="auth/registo.php">Criar conta</a>
        </div>
    </div>
</header>

<section class="landing-hero">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9 col-xl-8">
                <p class="small text-uppercase fw-semibold mb-2" style="color: var(--accent); letter-spacing: 0.12em;">Finanças · Família · Clareza</p>
                <h1 class="landing-hero__title"><?php echo htmlspecialchars($marcaSiteNome); ?></h1>
                <p class="landing-hero__lead">
                    Um painel moderno para acompanhar rendimentos, despesas, orçamentos e objetivos — com relatórios claros e segurança simples.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="auth/registo.php" class="btn btn-primary btn-lg rounded-pill px-4">Começar grátis</a>
                    <a href="auth/login.php" class="btn btn-outline-secondary btn-lg rounded-pill px-4">Já tenho conta</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="landing-section landing-section--alt">
    <div class="container">
        <h2 class="landing-section__title">Tudo num só lugar</h2>
        <div class="row g-4">
            <div class="col-md-6 col-lg-4">
                <div class="landing-feature">
                    <div class="landing-feature__icon"><i class="bi bi-graph-up-arrow"></i></div>
                    <h3 class="h5 fw-bold mb-2" style="color: var(--heading-color);">Rendimentos &amp; despesas</h3>
                    <p class="small text-muted mb-0">Registo rápido, categorização e histórico sempre disponível.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="landing-feature">
                    <div class="landing-feature__icon"><i class="bi bi-piggy-bank"></i></div>
                    <h3 class="h5 fw-bold mb-2" style="color: var(--heading-color);">Orçamentos inteligentes</h3>
                    <p class="small text-muted mb-0">Define limites por categoria e vê quando te aproximas do teto.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="landing-feature">
                    <div class="landing-feature__icon"><i class="bi bi-bullseye"></i></div>
                    <h3 class="h5 fw-bold mb-2" style="color: var(--heading-color);">Objetivos financeiros</h3>
                    <p class="small text-muted mb-0">Poupança com progresso visual e motivação constante.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="landing-feature">
                    <div class="landing-feature__icon"><i class="bi bi-file-earmark-pdf"></i></div>
                    <h3 class="h5 fw-bold mb-2" style="color: var(--heading-color);">Relatórios &amp; PDF</h3>
                    <p class="small text-muted mb-0">Exporta períodos para arquivo ou partilha com a família.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="landing-feature">
                    <div class="landing-feature__icon"><i class="bi bi-moon-stars"></i></div>
                    <h3 class="h5 fw-bold mb-2" style="color: var(--heading-color);">Temas premium</h3>
                    <p class="small text-muted mb-0">Escuro, claro, roxo, vermelho e cinza — consistente em toda a app.</p>
                </div>
            </div>
            <div class="col-md-6 col-lg-4">
                <div class="landing-feature">
                    <div class="landing-feature__icon"><i class="bi bi-shield-lock"></i></div>
                    <h3 class="h5 fw-bold mb-2" style="color: var(--heading-color);">Área admin</h3>
                    <p class="small text-muted mb-0">Gestão de utilizadores e configuração global quando precisares.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="landing-section">
    <div class="container">
        <div class="landing-cta">
            <h2 class="h4 fw-bold mb-2" style="color: var(--heading-color);">Pronto para organizar as tuas finanças?</h2>
            <p class="text-muted mb-4 mx-auto" style="max-width: 480px;">Interface pensada para ser rápida, bonita e fácil de usar — ideal para projetos académicos e uso familiar.</p>
            <a href="auth/registo.php" class="btn btn-primary btn-lg rounded-pill px-5">Criar conta</a>
        </div>
    </div>
</section>

<footer class="py-4 text-center small" style="color: var(--text-tertiary); border-top: 1px solid var(--border-subtle);">
    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($marcaSiteNome); ?>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>
