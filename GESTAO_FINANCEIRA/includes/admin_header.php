<?php
/**
 * Shell admin — mesmo design system que a app (temas sincronizados)
 *
 * Variáveis opcionais:
 *   $adminPageTitle
 *   $adminNavActive — 'dashboard' | 'utilizadores' | 'categorias' | 'relatorios' | 'configuracoes'
 *   $adminTopBarTitle
 */
declare(strict_types=1);

$adminPageTitle = $adminPageTitle ?? 'Administração';
$adminNavActive = $adminNavActive ?? '';
$adminTopBarTitle = $adminTopBarTitle ?? '';

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
    $cfg = plataforma_obter_config(getPDO());
    $marcaPlataforma = $cfg['nome_site'];
    $logoPlataformaUrl = plataforma_logo_url($cfg['logo_ficheiro'], '../');
} catch (Throwable $e) {
}

function admin_nav_active(string $key, string $current): string
{
    return $key === $current ? 'app-sidebar__link--active' : '';
}

$fotoPerfil = $_SESSION['utilizador_foto'] ?? '';
$avatarPath = $fotoPerfil ? '../assets/img/profiles/' . $fotoPerfil : '../assets/img/profiles/default.svg';
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($adminPageTitle); ?> — Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/themes.css">
    <link rel="stylesheet" href="../assets/css/neon-theme.css">
    <link rel="stylesheet" href="../assets/css/app-shell.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin-layout.css">
</head>
<body class="<?php echo htmlspecialchars($classeTema); ?>">
<div class="app-root">
    <div class="app-sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

    <aside class="app-sidebar" id="appSidebar" aria-label="Navegação admin">
        <div class="app-sidebar__inner">
            <a class="app-sidebar__brand flex-wrap" href="dashboard.php">
                <?php if ($logoPlataformaUrl !== ''): ?>
                    <img src="<?php echo htmlspecialchars($logoPlataformaUrl); ?>" alt="" class="app-sidebar__logo">
                <?php else: ?>
                    <span class="d-inline-flex align-items-center justify-content-center rounded-2 app-sidebar__logo"
                          style="width:34px;height:34px;background:var(--accent-muted);color:var(--accent);font-weight:800;font-size:0.85rem;">A</span>
                <?php endif; ?>
                <span class="brand-text"><?php echo htmlspecialchars($marcaPlataforma); ?></span>
                <span class="app-sidebar__badge brand-text">Admin</span>
            </a>

            <div class="app-sidebar__section-label">Painel</div>
            <nav class="app-sidebar__nav">
                <a class="app-sidebar__link <?php echo admin_nav_active('dashboard', $adminNavActive); ?>" href="dashboard.php">
                    <i class="bi bi-grid-1x2-fill"></i><span class="app-sidebar__link-text">Dashboard</span>
                </a>
                <a class="app-sidebar__link <?php echo admin_nav_active('utilizadores', $adminNavActive); ?>" href="utilizadores.php">
                    <i class="bi bi-people"></i><span class="app-sidebar__link-text">Utilizadores</span>
                </a>
                <a class="app-sidebar__link <?php echo admin_nav_active('categorias', $adminNavActive); ?>" href="categorias.php">
                    <i class="bi bi-tags"></i><span class="app-sidebar__link-text">Categorias</span>
                </a>
                <a class="app-sidebar__link <?php echo admin_nav_active('relatorios', $adminNavActive); ?>" href="relatorios.php">
                    <i class="bi bi-download"></i><span class="app-sidebar__link-text">Relatórios</span>
                </a>
                <a class="app-sidebar__link <?php echo admin_nav_active('configuracoes', $adminNavActive); ?>" href="configuracoes.php">
                    <i class="bi bi-sliders"></i><span class="app-sidebar__link-text">Configurações</span>
                </a>
            </nav>

            <div class="app-sidebar__footer">
                <a class="app-sidebar__link" href="../pages/dashboard.php">
                    <i class="bi bi-arrow-left-circle"></i><span class="app-sidebar__link-text">Área pessoal</span>
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
                <button type="button" class="app-topbar__toggle d-none d-lg-inline-flex" id="btnCollapseSidebar" title="Colapsar barra lateral">
                    <i class="bi bi-layout-sidebar-inset fs-6"></i>
                </button>
                <?php if ($adminTopBarTitle !== ''): ?>
                    <span class="app-topbar__title d-none d-sm-inline"><?php echo htmlspecialchars($adminTopBarTitle); ?></span>
                <?php else: ?>
                    <span class="app-topbar__title d-none d-sm-inline text-uppercase small" style="letter-spacing:0.08em;">Admin</span>
                <?php endif; ?>
            </div>
            <div class="app-topbar__right">
                <div class="dropdown">
                    <button class="btn dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="" class="app-topbar__avatar" width="34" height="34">
                        <span class="d-none d-md-inline text-truncate" style="max-width:140px;"><?php echo htmlspecialchars($_SESSION['utilizador_nome'] ?? ''); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><a class="dropdown-item" href="../pages/area_utilizador.php"><i class="bi bi-person me-2"></i>Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <main class="app-content">
