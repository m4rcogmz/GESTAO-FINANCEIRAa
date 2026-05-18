<?php
// pages/rendimentos.php
// CRUD completo de rendimentos:
// - Listar rendimentos do utilizador autenticado
// - Adicionar novo rendimento
// - Editar rendimento existente
// - Apagar rendimento

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = getPDO();
$utilizadorId = $_SESSION['utilizador_id'];

// Variáveis para mensagens
$mensagemSucesso = '';
$mensagemErro = '';

// 1) Processar ações (adicionar, editar, apagar)
$acao = $_GET['acao'] ?? '';

// Apagar rendimento
if ($acao === 'apagar' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];

    // Só apaga se o rendimento pertencer ao utilizador atual
    $stmt = $pdo->prepare('DELETE FROM rendimentos WHERE id = :id AND utilizador_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $utilizadorId]);

    if ($stmt->rowCount() > 0) {
        $mensagemSucesso = 'Rendimento apagado com sucesso.';
    } else {
        $mensagemErro = 'Não foi possível apagar o rendimento.';
    }
}

// Adicionar ou editar (submissão do formulário)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valor = isset($_POST['valor']) ? (float)str_replace(',', '.', $_POST['valor']) : 0;
    $tipo = trim($_POST['tipo'] ?? '');
    $data = $_POST['data'] ?? '';
    $descricao = trim($_POST['descricao'] ?? '');
    $idEditar = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($valor <= 0 || $tipo === '' || $data === '') {
        $mensagemErro = 'Preenche todos os campos obrigatórios (valor, tipo, data) com valores válidos.';
    } else {
        if ($idEditar > 0) {
            // Atualizar rendimento existente
            $stmt = $pdo->prepare('
                UPDATE rendimentos
                SET valor = :valor, tipo = :tipo, data = :data, descricao = :descricao
                WHERE id = :id AND utilizador_id = :uid
            ');
            $ok = $stmt->execute([
                'valor' => $valor,
                'tipo' => $tipo,
                'data' => $data,
                'descricao' => $descricao,
                'id' => $idEditar,
                'uid' => $utilizadorId,
            ]);

            if ($ok && $stmt->rowCount() > 0) {
                $mensagemSucesso = 'Rendimento atualizado com sucesso.';
            } else {
                $mensagemErro = 'Não foi possível atualizar o rendimento.';
            }
        } else {
            // Inserir novo rendimento
            $stmt = $pdo->prepare('
                INSERT INTO rendimentos (utilizador_id, valor, tipo, data, descricao)
                VALUES (:uid, :valor, :tipo, :data, :descricao)
            ');
            $ok = $stmt->execute([
                'uid' => $utilizadorId,
                'valor' => $valor,
                'tipo' => $tipo,
                'data' => $data,
                'descricao' => $descricao,
            ]);

            if ($ok) {
                $mensagemSucesso = 'Rendimento adicionado com sucesso.';
            } else {
                $mensagemErro = 'Não foi possível adicionar o rendimento.';
            }
        }
    }
}

// 2) Se estivermos a editar, buscar os dados do rendimento
$rendimentoEditar = null;
if ($acao === 'editar' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare('SELECT * FROM rendimentos WHERE id = :id AND utilizador_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $utilizadorId]);
    $rendimentoEditar = $stmt->fetch();
    if (!$rendimentoEditar) {
        $mensagemErro = 'Rendimento não encontrado.';
    }
}

// 3) Listar todos os rendimentos do utilizador
$stmt = $pdo->prepare('
    SELECT id, valor, tipo, data, descricao
    FROM rendimentos
    WHERE utilizador_id = :uid
    ORDER BY data DESC, id DESC
');
$stmt->execute(['uid' => $utilizadorId]);
$rendimentos = $stmt->fetchAll();

$appTopBarTitle = 'Rendimentos';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="ds-page-header">
    <h1>Rendimentos</h1>
    <p>Gere entradas de dinheiro: salário, apoios, extras e outros.</p>
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
                    <?php echo $rendimentoEditar ? 'Editar rendimento' : 'Adicionar rendimento'; ?>
                </h5>
                <form method="post" action="">
                    <?php if ($rendimentoEditar): ?>
                        <input type="hidden" name="id" value="<?php echo (int)$rendimentoEditar['id']; ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="valor" class="form-label">Valor (€)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="valor" name="valor"
                               value="<?php echo $rendimentoEditar ? htmlspecialchars($rendimentoEditar['valor']) : ''; ?>"
                               required>
                    </div>
                    <div class="mb-3">
                        <label for="tipo" class="form-label">Tipo</label>
                        <input type="text" class="form-control" id="tipo" name="tipo"
                               placeholder="Ex: Salário, Apoio, Extra"
                               value="<?php echo $rendimentoEditar ? htmlspecialchars($rendimentoEditar['tipo']) : ''; ?>"
                               required>
                    </div>
                    <div class="mb-3">
                        <label for="data" class="form-label">Data</label>
                        <input type="date" class="form-control" id="data" name="data"
                               value="<?php echo $rendimentoEditar ? htmlspecialchars($rendimentoEditar['data']) : date('Y-m-d'); ?>"
                               required>
                    </div>
                    <div class="mb-3">
                        <label for="descricao" class="form-label">Descrição</label>
                        <textarea class="form-control" id="descricao" name="descricao" rows="2"
                                  placeholder="Opcional"><?php
                            echo $rendimentoEditar ? htmlspecialchars($rendimentoEditar['descricao']) : '';
                            ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <?php echo $rendimentoEditar ? 'Guardar alterações' : 'Adicionar'; ?>
                    </button>
                    <?php if ($rendimentoEditar): ?>
                        <a href="rendimentos.php" class="btn btn-link w-100 mt-2">Cancelar edição</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Lista de rendimentos</h5>
                <?php if ($rendimentos): ?>
                    <div class="table-responsive ds-table-wrap">
                        <table class="table table-striped align-middle">
                            <thead>
                            <tr>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th class="text-end">Valor (€)</th>
                                <th>Descrição</th>
                                <th class="text-end">Ações</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rendimentos as $r): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($r['data'])); ?></td>
                                    <td><?php echo htmlspecialchars($r['tipo']); ?></td>
                                    <td class="text-end">
                                        <?php echo number_format((float)$r['valor'], 2, ',', ' '); ?> €
                                    </td>
                                    <td><?php echo nl2br(htmlspecialchars($r['descricao'] ?? '')); ?></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex justify-content-end gap-2">
                                            <a href="rendimentos.php?acao=editar&id=<?php echo (int)$r['id']; ?>"
                                               class="btn btn-sm btn-outline-primary">Editar</a>
                                            <a href="rendimentos.php?acao=apagar&id=<?php echo (int)$r['id']; ?>"
                                               class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Tens a certeza que queres apagar este rendimento?');">
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
                    <p class="text-muted mb-0">Ainda não registaste qualquer rendimento.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

