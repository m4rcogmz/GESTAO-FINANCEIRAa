<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getPDO();
$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    if ($acao === 'criar') {
        $nome = trim($_POST['nome'] ?? '');
        if ($nome === '') {
            $erro = 'Indica o nome da categoria.';
        } else {
            try {
                $pdo->prepare('INSERT INTO categorias (nome) VALUES (:n)')->execute(['n' => $nome]);
                $mensagem = 'Categoria criada.';
            } catch (Throwable $e) {
                $erro = 'Não foi possível criar (nome duplicado?).';
            }
        }
    } elseif ($acao === 'editar') {
        $cid = (int)($_POST['id'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        if ($cid <= 0 || $nome === '') {
            $erro = 'Dados inválidos.';
        } else {
            try {
                $pdo->prepare('UPDATE categorias SET nome = :n WHERE id = :id')->execute(['n' => $nome, 'id' => $cid]);
                $mensagem = 'Categoria atualizada.';
            } catch (Throwable $e) {
                $erro = 'Não foi possível atualizar.';
            }
        }
    } elseif ($acao === 'apagar') {
        $cid = (int)($_POST['id'] ?? 0);
        if ($cid <= 0) {
            $erro = 'ID inválido.';
        } else {
            $st = $pdo->prepare('SELECT COUNT(*) FROM despesas WHERE categoria_id = :id');
            $st->execute(['id' => $cid]);
            $nDesp = (int)$st->fetchColumn();
            $st = $pdo->prepare('SELECT COUNT(*) FROM orcamentos WHERE categoria_id = :id');
            $st->execute(['id' => $cid]);
            $nOrc = (int)$st->fetchColumn();
            if ($nDesp > 0 || $nOrc > 0) {
                $erro = 'Esta categoria está em uso em despesas ou orçamentos. Não pode ser apagada.';
            } else {
                $pdo->prepare('DELETE FROM categorias WHERE id = :id')->execute(['id' => $cid]);
                $mensagem = 'Categoria removida.';
            }
        }
    }
}

$categorias = $pdo->query('SELECT id, nome FROM categorias ORDER BY nome ASC')->fetchAll(PDO::FETCH_ASSOC);

$adminPageTitle = 'Categorias globais';
$adminNavActive = 'categorias';
$adminTopBarTitle = 'Categorias';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="ds-page-header">
    <h1>Categorias globais</h1>
    <p>Partilhadas por todos os utilizadores (despesas e orçamentos).</p>
</div>

<?php if ($mensagem): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
<?php endif; ?>
<?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3">Nova categoria</h5>
        <form method="post" class="row g-2 align-items-end">
            <input type="hidden" name="acao" value="criar">
            <div class="col-md-8">
                <label class="form-label">Nome</label>
                <input type="text" name="nome" class="form-control" placeholder="Ex.: Supermercado" required>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">Criar</button>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive ds-table-wrap">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categorias as $c): ?>
                    <tr>
                        <td><?php echo (int)$c['id']; ?></td>
                        <td>
                            <form method="post" class="d-flex gap-2 flex-wrap align-items-center">
                                <input type="hidden" name="acao" value="editar">
                                <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                <input type="text" name="nome" class="form-control form-control-sm" style="max-width: 280px;"
                                       value="<?php echo htmlspecialchars($c['nome']); ?>" required>
                                <button type="submit" class="btn btn-sm btn-outline-primary">Guardar</button>
                            </form>
                        </td>
                        <td class="text-end">
                            <form method="post" class="d-inline" onsubmit="return confirm('Apagar esta categoria?');">
                                <input type="hidden" name="acao" value="apagar">
                                <input type="hidden" name="id" value="<?php echo (int)$c['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">Apagar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($categorias)): ?>
                    <tr><td colspan="3" class="text-muted">Sem categorias.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
