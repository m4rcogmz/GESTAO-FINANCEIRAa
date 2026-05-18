<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/platform_settings.php';
require_admin();

$pdo = getPDO();
$mensagem = '';
$erro = '';

$cfg = plataforma_obter_config($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nomeSite = trim($_POST['nome_site'] ?? '');
    if ($nomeSite === '') {
        $erro = 'O nome da plataforma não pode estar vazio.';
    } else {
        $logoFicheiro = $cfg['logo_ficheiro'];
        if (!empty($_FILES['logo']['name'])) {
            $permitidos = ['image/jpeg' => '.jpg', 'image/png' => '.png', 'image/svg+xml' => '.svg'];
            $tmp = $_FILES['logo']['tmp_name'];
            $tipo = @mime_content_type($tmp);
            if (!isset($permitidos[$tipo])) {
                $erro = 'Logo: usa JPG, PNG ou SVG.';
            } else {
                $dir = __DIR__ . '/../assets/img/platform/';
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $novo = 'logo_' . time() . $permitidos[$tipo];
                if (move_uploaded_file($tmp, $dir . $novo)) {
                    if ($logoFicheiro && is_file($dir . $logoFicheiro)) {
                        @unlink($dir . $logoFicheiro);
                    }
                    $logoFicheiro = $novo;
                } else {
                    $erro = 'Não foi possível guardar o logo.';
                }
            }
        }

        if (!$erro) {
            $st = $pdo->prepare('UPDATE plataforma_config SET nome_site = :n, logo_ficheiro = :l WHERE id = 1');
            $st->execute(['n' => $nomeSite, 'l' => $logoFicheiro]);
            $mensagem = 'Configurações guardadas.';
            $cfg = plataforma_obter_config($pdo);
        }
    }
}

$adminPageTitle = 'Configurações da plataforma';
$adminNavActive = 'configuracoes';
$adminTopBarTitle = 'Configurações';
require_once __DIR__ . '/../includes/admin_header.php';

$logoUrl = plataforma_logo_url($cfg['logo_ficheiro'], '../');
?>

<div class="ds-page-header">
    <h1>Configurações</h1>
    <p>Nome da plataforma e logótipo na barra superior.</p>
</div>

<?php if ($mensagem): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
<?php endif; ?>
<?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Nome da plataforma</label>
                        <input type="text" name="nome_site" class="form-control" required
                               value="<?php echo htmlspecialchars($cfg['nome_site']); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Logo (opcional)</label>
                        <input type="file" name="logo" class="form-control" accept="image/jpeg,image/png,image/svg+xml">
                        <div class="form-text">Recomendado: imagem horizontal, fundo transparente (PNG/SVG).</div>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body text-center">
                <h6 class="mb-3">Pré-visualização</h6>
                <?php if ($logoUrl !== ''): ?>
                    <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo" class="img-fluid mb-3" style="max-height: 80px;">
                <?php else: ?>
                    <p class="text-muted small">Sem logo personalizado.</p>
                <?php endif; ?>
                <p class="mb-0 fw-semibold"><?php echo htmlspecialchars($cfg['nome_site']); ?></p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
