<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_helpers.php';
require_admin();

$pdo = getPDO();
$adminSessaoId = (int)$_SESSION['utilizador_id'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare('SELECT id, nome, email, role, conta_suspensa, data_registo, tema, foto_perfil FROM utilizadores WHERE id = :id');
$stmt->execute(['id' => $id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: utilizadores.php');
    exit;
}

$mensagem = '';
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $suspensa = isset($_POST['conta_suspensa']) ? 1 : 0;
    $password = $_POST['password'] ?? '';

    if ($role !== 'admin' && $role !== 'user') {
        $role = 'user';
    }

    if ($nome === '' || $email === '') {
        $erro = 'Nome e email são obrigatórios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Email inválido.';
    } else {
        $st = $pdo->prepare('SELECT id FROM utilizadores WHERE email = :e AND id <> :id');
        $st->execute(['e' => $email, 'id' => $id]);
        if ($st->fetch()) {
            $erro = 'Já existe outro utilizador com este email.';
        }
    }

    // Não permitir que o último admin deixe de ser admin
    if (!$erro && $user['role'] === 'admin' && $role === 'user' && admin_count_admins($pdo) <= 1) {
        $erro = 'Não podes retirar o papel de administrador ao único admin da plataforma.';
    }

    // Não suspender a própria sessão por engano
    if (!$erro && $id === $adminSessaoId && $suspensa === 1) {
        $erro = 'Não podes suspender a tua própria conta enquanto estás autenticado.';
    }

    if (!$erro) {
        $campos = 'nome = :nome, email = :email, role = :role, conta_suspensa = :sus';
        $params = [
            'nome' => $nome,
            'email' => $email,
            'role' => $role,
            'sus' => $suspensa,
            'id' => $id,
        ];
        if ($password !== '') {
            if (strlen($password) < 6) {
                $erro = 'A palavra-passe deve ter pelo menos 6 caracteres.';
            } else {
                $campos .= ', password = :pw';
                $params['pw'] = password_hash($password, PASSWORD_DEFAULT);
            }
        }

        if (!$erro) {
            $sql = 'UPDATE utilizadores SET ' . $campos . ' WHERE id = :id';
            $pdo->prepare($sql)->execute($params);
            $mensagem = 'Dados atualizados com sucesso.';
            if ($id === $adminSessaoId) {
                $_SESSION['utilizador_nome'] = $nome;
                $_SESSION['utilizador_email'] = $email;
                $_SESSION['utilizador_role'] = $role;
            }
            $stReload = $pdo->prepare('SELECT id, nome, email, role, conta_suspensa, data_registo, tema, foto_perfil FROM utilizadores WHERE id = :id');
            $stReload->execute(['id' => $id]);
            $user = $stReload->fetch(PDO::FETCH_ASSOC) ?: $user;
        }
    }
}

$adminPageTitle = 'Editar utilizador';
$adminNavActive = 'utilizadores';
$adminTopBarTitle = 'Editar utilizador';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="ds-page-header">
    <h1>Detalhes do utilizador</h1>
    <p>ID <?php echo (int)$user['id']; ?> · criado em <?php echo htmlspecialchars($user['data_registo'] ?? '-'); ?></p>
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
                <h5 class="card-title mb-3">Editar conta</h5>
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label">Nome</label>
                        <input type="text" name="nome" class="form-control" required
                               value="<?php echo htmlspecialchars($user['nome']); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Papel (role)</label>
                        <select name="role" class="form-select">
                            <option value="user" <?php echo ($user['role'] ?? '') === 'user' ? 'selected' : ''; ?>>user</option>
                            <option value="admin" <?php echo ($user['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>admin</option>
                        </select>
                        <div class="form-text">Apenas dois valores: <code>admin</code> ou <code>user</code>.</div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="conta_suspensa" value="1" id="sus"
                            <?php echo (int)($user['conta_suspensa'] ?? 0) === 1 ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="sus">Conta suspensa</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nova palavra-passe (opcional)</label>
                        <input type="password" name="password" class="form-control" placeholder="Deixa vazio para não alterar" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a href="utilizadores.php" class="btn btn-outline-secondary">Voltar à lista</a>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h6 class="card-title">Resumo</h6>
                <ul class="list-unstyled small text-muted mb-0">
                    <li class="mb-2"><strong>Tema:</strong> <?php echo htmlspecialchars($user['tema'] ?? '-'); ?></li>
                    <li class="mb-2"><strong>Foto (ficheiro):</strong> <?php echo htmlspecialchars($user['foto_perfil'] ?: '—'); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
