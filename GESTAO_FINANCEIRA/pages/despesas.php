<?php
// pages/despesas.php
// CRUD completo de despesas com categorias:
// - Listar despesas do utilizador autenticado
// - Adicionar nova despesa (escolhendo categoria)
// - Editar despesa existente
// - Apagar despesa

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = getPDO();
$utilizadorId = $_SESSION['utilizador_id'];

// 1) Carregar categorias disponíveis (para o <select>)
$stmt = $pdo->query('SELECT id, nome FROM categorias ORDER BY nome');
$categorias = $stmt->fetchAll();

$mensagemSucesso = '';
$mensagemErro = '';

// 2) Processar ações (apagar, adicionar, editar)
$acao = $_GET['acao'] ?? '';

// Apagar despesa
if ($acao === 'apagar' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare('DELETE FROM despesas WHERE id = :id AND utilizador_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $utilizadorId]);

    if ($stmt->rowCount() > 0) {
        $mensagemSucesso = 'Despesa apagada com sucesso.';
    } else {
        $mensagemErro = 'Não foi possível apagar a despesa.';
    }
}

// Adicionar / editar (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valor = isset($_POST['valor']) ? (float)str_replace(',', '.', $_POST['valor']) : 0;
    $categoriaId = isset($_POST['categoria_id']) ? (int)$_POST['categoria_id'] : 0;
    $data = $_POST['data'] ?? '';
    $descricao = trim($_POST['descricao'] ?? '');
    $idEditar = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($valor <= 0 || $categoriaId <= 0 || $data === '') {
        $mensagemErro = 'Preenche todos os campos obrigatórios (valor, categoria, data) com valores válidos.';
    } else {
        // Verificar se a categoria existe
        $stmt = $pdo->prepare('SELECT id FROM categorias WHERE id = :id');
        $stmt->execute(['id' => $categoriaId]);
        if (!$stmt->fetch()) {
            $mensagemErro = 'A categoria selecionada não é válida.';
        } else {
            if ($idEditar > 0) {
                // Atualizar despesa existente
                $stmt = $pdo->prepare('
                    UPDATE despesas
                    SET categoria_id = :categoria_id, valor = :valor, data = :data, descricao = :descricao
                    WHERE id = :id AND utilizador_id = :uid
                ');
                $ok = $stmt->execute([
                    'categoria_id' => $categoriaId,
                    'valor' => $valor,
                    'data' => $data,
                    'descricao' => $descricao,
                    'id' => $idEditar,
                    'uid' => $utilizadorId,
                ]);

                if ($ok && $stmt->rowCount() > 0) {
                    $mensagemSucesso = 'Despesa atualizada com sucesso.';
                } else {
                    $mensagemErro = 'Não foi possível atualizar a despesa.';
                }
            } else {
                // Inserir nova despesa
                $stmt = $pdo->prepare('
                    INSERT INTO despesas (utilizador_id, categoria_id, valor, data, descricao)
                    VALUES (:uid, :categoria_id, :valor, :data, :descricao)
                ');
                $ok = $stmt->execute([
                    'uid' => $utilizadorId,
                    'categoria_id' => $categoriaId,
                    'valor' => $valor,
                    'data' => $data,
                    'descricao' => $descricao,
                ]);

                if ($ok) {
                    $mensagemSucesso = 'Despesa adicionada com sucesso.';
                } else {
                    $mensagemErro = 'Não foi possível adicionar a despesa.';
                }
            }
        }
    }
}

// 3) Se estivermos a editar, buscar dados da despesa
$despesaEditar = null;
if ($acao === 'editar' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare('SELECT * FROM despesas WHERE id = :id AND utilizador_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $utilizadorId]);
    $despesaEditar = $stmt->fetch();

    if (!$despesaEditar) {
        $mensagemErro = 'Despesa não encontrada.';
    }
}

// 4) Listar todas as despesas do utilizador com o nome da categoria
$stmt = $pdo->prepare('
    SELECT d.id, d.valor, d.data, d.descricao, c.nome AS categoria
    FROM despesas d
    INNER JOIN categorias c ON c.id = d.categoria_id
    WHERE d.utilizador_id = :uid
    ORDER BY d.data DESC, d.id DESC
');
$stmt->execute(['uid' => $utilizadorId]);
$despesas = $stmt->fetchAll();

$appTopBarTitle = 'Despesas';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="ds-page-header">
    <h1>Despesas</h1>
    <p>Regista e acompanha gastos por categoria.</p>
</div>

<?php if ($mensagemSucesso): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($mensagemSucesso); ?></div>
<?php endif; ?>

<?php if ($mensagemErro): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($mensagemErro); ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <?php echo $despesaEditar ? 'Editar despesa' : 'Adicionar despesa'; ?>
                </h5>
                <form method="post" action="">
                    <?php if ($despesaEditar): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$despesaEditar['id']; ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="valor" class="form-label">Valor (€)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="valor" name="valor"
                               value="<?php echo $despesaEditar ? htmlspecialchars($despesaEditar['valor']) : ''; ?>"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="categoria_id" class="form-label">Categoria</label>
                        <select class="form-select" id="categoria_id" name="categoria_id" required>
                            <option value="">-- Seleciona uma categoria --</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo (int)$cat['id']; ?>"
                                    <?php
                                    $selecionada = $despesaEditar && (int)$despesaEditar['categoria_id'] === (int)$cat['id'];
                                    echo $selecionada ? 'selected' : '';
                                    ?>>
                                    <?php echo htmlspecialchars($cat['nome']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="data" class="form-label">Data</label>
                        <input type="date" class="form-control" id="data" name="data"
                               value="<?php echo $despesaEditar ? htmlspecialchars($despesaEditar['data']) : date('Y-m-d'); ?>"
                               required>
                    </div>

                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="2"
                                  placeholder="Opcional"><?php
                            echo $despesaEditar ? htmlspecialchars($despesaEditar['descricao']) : '';
                            ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <?php echo $despesaEditar ? 'Guardar alterações' : 'Adicionar'; ?>
                    </button>
                    <?php if ($despesaEditar): ?>
                        <a href="despesas.php" class="btn btn-link w-100 mt-2">Cancelar edição</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Lista de despesas</h5>
                <?php if ($despesas): ?>
                    <div class="table-responsive ds-table-wrap">
                        <table class="table table-striped align-middle">
                            <thead>
                            <tr>
                                <th>Data</th>
                                <th>Categoria</th>
                                <th class="text-end">Valor (€)</th>
                                <th>Descrição</th>
                                <th class="text-end">Ações</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($despesas as $d): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($d['data'])); ?></td>
                                    <td><?php echo htmlspecialchars($d['categoria']); ?></td>
                                    <td class="text-end" style="color: var(--danger-color); font-weight: 600;">
                                        <?php echo number_format((float)$d['valor'], 2, ',', ' '); ?> €
                                    </td>
                                    <td><?php echo nl2br(htmlspecialchars($d['descricao'] ?? '')); ?></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex justify-content-end gap-2">
                                            <a href="despesas.php?acao=editar&id=<?php echo (int)$d['id']; ?>"
                                               class="btn btn-sm btn-outline-primary">
                                                Editar
                                            </a>
                                            <a href="despesas.php?acao=apagar&id=<?php echo (int)$d['id']; ?>"
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Tens a certeza que queres apagar esta despesa?');">
                                                Apagar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Ainda não registaste qualquer despesa.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

