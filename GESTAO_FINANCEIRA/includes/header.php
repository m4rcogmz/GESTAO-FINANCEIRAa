<?php
// includes/header.php — Shell principal (sidebar + topbar + conteúdo)
$temaSessaoRaw = $_SESSION['utilizador_tema'] ?? 'dark';

$mapTemasAntigos = [
    'escuro'    => 'dark',
    'claro'     => 'light',
    'neon-azul' => 'dark',
    'roxo'      => 'purple',
    'vermelho'  => 'red',
    'cinza'     => 'gray',
];

$temaSessao = $mapTemasAntigos[$temaSessaoRaw] ?? $temaSessaoRaw;
$temasPermitidos = ['dark', 'light', 'purple', 'red', 'gray'];
if (!in_array($temaSessao, $temasPermitidos, true)) {
    $temaSessao = 'dark';
}

$classeTema = 'theme-' . $temaSessao;

require_once __DIR__ . '/platform_settings.php';
$marcaPlataforma = 'Gestão Financeira Familiar';
$logoPlataformaUrl = '';
try {
    if (function_exists('getPDO')) {
        $cfg = plataforma_obter_config(getPDO());
        $marcaPlataforma = $cfg['nome_site'];
        $logoPlataformaUrl = plataforma_logo_url($cfg['logo_ficheiro'], '../');
    }
} catch (Throwable $e) {
}

$appTopBarTitle = $appTopBarTitle ?? '';
$paginaAtual = basename($_SERVER['PHP_SELF'] ?? '');

function nav_active_app(string $ficheiro, string $atual): string
{
    return $ficheiro === $atual ? 'app-sidebar__link--active' : '';
}

$fotoPerfil = $_SESSION['utilizador_foto'] ?? '';
$avatarPath = $fotoPerfil ? '../assets/img/profiles/' . $fotoPerfil : '../assets/img/profiles/default.svg';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($marcaPlataforma); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css">
    <link rel="stylesheet" href="../assets/css/neon-theme.css">
    <link rel="stylesheet" href="../assets/css/app-shell.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="<?php echo htmlspecialchars($classeTema); ?>">
<div class="app-root">
    <div class="app-sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

    <aside class="app-sidebar" id="appSidebar" aria-label="Navegação principal">
        <div class="app-sidebar__inner">
            <a class="app-sidebar__brand flex-wrap" href="../pages/dashboard.php">
                <?php if ($logoPlataformaUrl !== ''): ?>
                    <img src="<?php echo htmlspecialchars($logoPlataformaUrl); ?>" alt="" class="app-sidebar__logo">
                <?php else: ?>
                    <span class="d-inline-flex align-items-center justify-content-center rounded-2 app-sidebar__logo"
                          style="width:34px;height:34px;background:var(--accent-muted);color:var(--accent);font-weight:800;font-size:0.85rem;">GF</span>
                <?php endif; ?>
                <span class="brand-text"><?php echo htmlspecialchars($marcaPlataforma); ?></span>
            </a>

            <div class="app-sidebar__section-label">Navegação</div>
            <nav class="app-sidebar__nav">
                <a class="app-sidebar__link <?php echo nav_active_app('dashboard.php', $paginaAtual); ?>"
                   href="../pages/dashboard.php" title="Dashboard">
                    <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i>
                    <span class="app-sidebar__link-text">Dashboard</span>
                </a>
                <a class="app-sidebar__link <?php echo nav_active_app('rendimentos.php', $paginaAtual); ?>"
                   href="../pages/rendimentos.php" title="Rendimentos">
                    <i class="bi bi-graph-up-arrow" aria-hidden="true"></i>
                    <span class="app-sidebar__link-text">Rendimentos</span>
                </a>
                <a class="app-sidebar__link <?php echo nav_active_app('despesas.php', $paginaAtual); ?>"
                   href="../pages/despesas.php" title="Despesas">
                    <i class="bi bi-wallet2" aria-hidden="true"></i>
                    <span class="app-sidebar__link-text">Despesas</span>
                </a>
                <a class="app-sidebar__link <?php echo nav_active_app('orcamentos.php', $paginaAtual); ?>"
                   href="../pages/orcamentos.php" title="Orçamentos">
                    <i class="bi bi-piggy-bank" aria-hidden="true"></i>
                    <span class="app-sidebar__link-text">Orçamentos</span>
                </a>
                <a class="app-sidebar__link <?php echo nav_active_app('objetivos.php', $paginaAtual); ?>"
                   href="../pages/objetivos.php" title="Objetivos">
                    <i class="bi bi-bullseye" aria-hidden="true"></i>
                    <span class="app-sidebar__link-text">Objetivos</span>
                </a>
                <a class="app-sidebar__link <?php echo nav_active_app('relatorios.php', $paginaAtual); ?>"
                   href="../pages/relatorios.php" title="Relatórios">
                    <i class="bi bi-file-earmark-bar-graph" aria-hidden="true"></i>
                    <span class="app-sidebar__link-text">Relatórios</span>
                </a>
            </nav>

            <?php if (function_exists('is_admin') && is_admin()): ?>
                <div class="app-sidebar__section-label">Sistema</div>
                <nav class="app-sidebar__nav">
                    <a class="app-sidebar__link" href="../admin/dashboard.php" title="Administração">
                        <i class="bi bi-shield-lock" aria-hidden="true"></i>
                        <span class="app-sidebar__link-text">Administração</span>
                    </a>
                </nav>
            <?php endif; ?>

            <div class="app-sidebar__footer">
                <a class="app-sidebar__link <?php echo nav_active_app('area_utilizador.php', $paginaAtual); ?>"
                   href="../pages/area_utilizador.php" title="Perfil">
                    <i class="bi bi-person-circle" aria-hidden="true"></i>
                    <span class="app-sidebar__link-text">Perfil &amp; tema</span>
                </a>
            </div>
        </div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <div class="app-topbar__left">
                <button type="button" class="app-topbar__toggle d-lg-none" id="btnMobileSidebar" aria-label="Abrir menu">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <button type="button" class="app-topbar__toggle d-none d-lg-inline-flex" id="btnCollapseSidebar" title="Colapsar barra lateral" aria-label="Colapsar menu">
                    <i class="bi bi-layout-sidebar-inset fs-6"></i>
                </button>
                <?php if ($appTopBarTitle !== ''): ?>
                    <span class="app-topbar__title d-none d-sm-inline"><?php echo htmlspecialchars($appTopBarTitle); ?></span>
                <?php endif; ?>
            </div>
            <div class="app-topbar__right">
                <div class="dropdown">
                    <button class="btn dropdown-toggle d-flex align-items-center gap-2" type="button" id="userMenuBtn"
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="" class="app-topbar__avatar" width="34" height="34">
                        <span class="d-none d-md-inline text-truncate" style="max-width:140px;">
                            <?php echo htmlspecialchars($_SESSION['utilizador_nome'] ?? 'Utilizador'); ?>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userMenuBtn">
                        <li><a class="dropdown-item" href="../pages/area_utilizador.php"><i class="bi bi-person me-2"></i>Perfil</a></li>
                        <?php if (function_exists('is_admin') && is_admin()): ?>
                            <li><a class="dropdown-item" href="../admin/dashboard.php"><i class="bi bi-shield-lock me-2"></i>Admin</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#logoutModal">
                                <i class="bi bi-box-arrow-right me-2"></i>Terminar sessão
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Modal logout -->
        <div class="modal fade modal-neon" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="logoutModalLabel">Terminar sessão</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2 fw-semibold" style="color: var(--text-primary);">Desejas sair da aplicação?</p>
                        <p class="mb-0 small">Terás de iniciar sessão novamente para continuar.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <a href="../auth/logout.php" class="btn btn-primary">Sair</a>
                    </div>
                </div>
            </div>
        </div>

        <main class="app-content">
