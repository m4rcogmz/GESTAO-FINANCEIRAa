<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_helpers.php';
require_admin();

$pdo = getPDO();
$adminId = (int)$_SESSION['utilizador_id'];
$mensagem = '';
$erro = '';

// --- Ações POST (apagar / suspender / reativar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';
    $uid = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($uid <= 0) {
        $erro = 'Identificador inválido.';
    } elseif ($acao === 'apagar') {
        if ($uid === $adminId) {
            $erro = 'Não podes apagar a tua própria conta nesta sessão.';
        } else {
            $st = $pdo->prepare('SELECT role FROM utilizadores WHERE id = :id');
            $st->execute(['id' => $uid]);
            $alvo = $st->fetch(PDO::FETCH_ASSOC);
            if (!$alvo) {
                $erro = 'Utilizador não encontrado.';
            } elseif ($alvo['role'] === 'admin' && admin_count_admins($pdo) <= 1) {
                $erro = 'Não é possível apagar o último administrador da plataforma.';
            } else {
                try {
                    admin_apagar_utilizador_e_dados($pdo, $uid);
                    $mensagem = 'Utilizador e dados associados foram apagados.';
                } catch (Throwable $e) {
                    $erro = 'Erro ao apagar utilizador.';
                }
            }
        }
    } elseif ($acao === 'suspender') {
        if ($uid === $adminId) {
            $erro = 'Não podes suspender a tua própria conta.';
        } else {
            $pdo->prepare('UPDATE utilizadores SET conta_suspensa = 1 WHERE id = :id')->execute(['id' => $uid]);
            $mensagem = 'Conta suspensa.';
        }
    } elseif ($acao === 'reativar') {
        $pdo->prepare('UPDATE utilizadores SET conta_suspensa = 0 WHERE id = :id')->execute(['id' => $uid]);
        $mensagem = 'Conta reativada.';
    } else {
        $erro = 'Ação desconhecida.';
    }
}

$q = trim($_GET['q'] ?? '');
$sql = 'SELECT id, nome, email, role, conta_suspensa, data_registo FROM utilizadores';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE nome LIKE :q OR email LIKE :q';
    $params['q'] = '%' . $q . '%';
}
$sql .= ' ORDER BY id DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

$adminPageTitle = 'Gestão de utilizadores';
$adminNavActive = 'utilizadores';
$adminTopBarTitle = 'Utilizadores';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="ds-page-header">
    <h1>Utilizadores</h1>
    <p>Lista, pesquisa, edição, suspensão e remoção de contas.</p>
</div>

<?php if ($mensagem): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($mensagem); ?></div>
<?php endif; ?>
<?php if ($erro): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($erro); ?></div>
<?php endif; ?>

<form method="get" class="row g-2 mb-4 align-items-end">
    <div class="col-md-6 col-lg-4">
        <label class="form-label">Pesquisar</label>
        <input type="text" name="q" class="form-control" placeholder="Nome ou email…"
               value="<?php echo htmlspecialchars($q); ?>">
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-primary">Pesquisar</button>
        <?php if ($q !== ''): ?>
            <a href="utilizadores.php" class="btn btn-outline-secondary">Limpar</a>
        <?php endif; ?>
    </div>
</form>

<div class="table-responsive ds-table-wrap">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>Email</th>
                    <th>Papel</th>
                    <th>Estado</th>
                    <th>Registo</th>
                    <th class="text-end">Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista as $u): ?>
                    <tr>
                        <td><?php echo (int)$u['id']; ?></td>
                        <td><?php echo htmlspecialchars($u['nome']); ?></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td>
                            <span class="badge <?php echo ($u['role'] ?? '') === 'admin' ? 'text-bg-warning' : 'text-bg-secondary'; ?>">
                                <?php echo htmlspecialchars($u['role'] ?? 'user'); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ((int)($u['conta_suspensa'] ?? 0) === 1): ?>
                                <span class="text-danger">Suspensa</span>
                            <?php else: ?>
                                <span class="text-success">Ativa</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted"><?php echo htmlspecialchars($u['data_registo'] ?? '-'); ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="utilizador_editar.php?id=<?php echo (int)$u['id']; ?>">Detalhes / Editar</a>

                            <?php if ((int)$u['id'] !== $adminId): ?>
                                <?php if ((int)($u['conta_suspensa'] ?? 0) === 1): ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Reativar esta conta?');">
                                        <input type="hidden" name="acao" value="reativar">
                                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success">Reativar</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" class="d-inline" onsubmit="return confirm('Suspender esta conta?');">
                                        <input type="hidden" name="acao" value="suspender">
                                        <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning">Suspender</button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" class="d-inline" onsubmit="return confirm('Isto apaga o utilizador e TODOS os dados financeiros associados. Continuar?');">
                                    <input type="hidden" name="acao" value="apagar">
                                    <input type="hidden" name="id" value="<?php echo (int)$u['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">Apagar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($lista)): ?>
                    <tr><td colspan="7" class="text-muted">Nenhum utilizador encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
