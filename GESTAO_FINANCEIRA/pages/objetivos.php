<?php
// pages/objetivos.php
// Gestão de objetivos financeiros:
// - Criar objetivos (nome + valor objetivo)
// - Atualizar valor atual (poupança acumulada)
// - Mostrar progresso em percentagem com barra visual

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = getPDO();
$utilizadorId = $_SESSION['utilizador_id'];

$mensagemSucesso = '';
$mensagemErro = '';

// 1) Processar ações: apagar objetivo
$acao = $_GET['acao'] ?? '';

if ($acao === 'apagar' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare('DELETE FROM objetivos WHERE id = :id AND utilizador_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $utilizadorId]);

    if ($stmt->rowCount() > 0) {
        $mensagemSucesso = 'Objetivo apagado com sucesso.';
    } else {
        $mensagemErro = 'Não foi possível apagar o objetivo.';
    }
}

// 2) Processar criação/atualização (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipoForm = $_POST['tipo_form'] ?? '';

    if ($tipoForm === 'novo') {
        // Criar novo objetivo
        $nome = trim($_POST['nome'] ?? '');
        $valorObjetivo = isset($_POST['valor_objetivo']) ? (float)str_replace(',', '.', $_POST['valor_objetivo']) : 0;

        if ($nome === '' || $valorObjetivo <= 0) {
            $mensagemErro = 'Preenche o nome e o valor objetivo com valores válidos.';
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO objetivos (utilizador_id, nome, valor_objetivo, valor_atual)
                VALUES (:uid, :nome, :valor_objetivo, 0)
            ');
            $ok = $stmt->execute([
                'uid' => $utilizadorId,
                'nome' => $nome,
                'valor_objetivo' => $valorObjetivo,
            ]);

            if ($ok) {
                $mensagemSucesso = 'Objetivo criado com sucesso.';
            } else {
                $mensagemErro = 'Não foi possível criar o objetivo.';
            }
        }
    } elseif ($tipoForm === 'atualizar') {
        // Atualizar valor atual de um objetivo existente
        $idObjetivo = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $valorAtual = isset($_POST['valor_atual']) ? (float)str_replace(',', '.', $_POST['valor_atual']) : 0;

        if ($idObjetivo <= 0 || $valorAtual < 0) {
            $mensagemErro = 'Dados inválidos ao atualizar o objetivo.';
        } else {
            // Obter valor anterior do objetivo
            $stmt = $pdo->prepare('SELECT valor_atual, nome FROM objetivos WHERE id = :id AND utilizador_id = :uid');
            $stmt->execute(['id' => $idObjetivo, 'uid' => $utilizadorId]);
            $objetivoAnterior = $stmt->fetch();
            
            if (!$objetivoAnterior) {
                $mensagemErro = 'Objetivo não encontrado.';
            } else {
                $valorAnterior = (float)$objetivoAnterior['valor_atual'];
                $diferenca = $valorAtual - $valorAnterior;
                
                // Iniciar transação
                $pdo->beginTransaction();
                
                try {
                    // Atualizar objetivo
                    $stmt = $pdo->prepare('
                        UPDATE objetivos
                        SET valor_atual = :valor_atual
                        WHERE id = :id AND utilizador_id = :uid
                    ');
                    $stmt->execute([
                        'valor_atual' => $valorAtual,
                        'id' => $idObjetivo,
                        'uid' => $utilizadorId,
                    ]);
                    
                    // Se aumentou o valor, criar despesa e descontar do rendimento
                    if ($diferenca > 0) {
                        // Obter categoria "Objetivos" ou criar uma categoria especial
                        $stmt = $pdo->prepare('SELECT id FROM categorias WHERE nome = "Objetivos" LIMIT 1');
                        $stmt->execute();
                        $categoriaObjetivos = $stmt->fetch();
                        
                        if (!$categoriaObjetivos) {
                            // Criar categoria "Objetivos" se não existir
                            $stmt = $pdo->prepare('INSERT INTO categorias (nome) VALUES ("Objetivos")');
                            $stmt->execute();
                            $categoriaId = $pdo->lastInsertId();
                        } else {
                            $categoriaId = $categoriaObjetivos['id'];
                        }
                        
                        // Criar despesa para o objetivo
                        $stmt = $pdo->prepare('
                            INSERT INTO despesas (utilizador_id, categoria_id, valor, data, descricao)
                            VALUES (:uid, :categoria_id, :valor, :data, :descricao)
                        ');
                        $stmt->execute([
                            'uid' => $utilizadorId,
                            'categoria_id' => $categoriaId,
                            'valor' => $diferenca,
                            'data' => date('Y-m-d'),
                            'descricao' => 'Poupança para objetivo: ' . $objetivoAnterior['nome'],
                        ]);
                        
                        // Criar rendimento negativo (ou ajuste) para descontar
                        $stmt = $pdo->prepare('
                            INSERT INTO rendimentos (utilizador_id, valor, tipo, data, descricao)
                            VALUES (:uid, :valor, :tipo, :data, :descricao)
                        ');
                        $stmt->execute([
                            'uid' => $utilizadorId,
                            'valor' => -$diferenca,
                            'tipo' => 'Ajuste - Objetivo',
                            'data' => date('Y-m-d'),
                            'descricao' => 'Desconto para objetivo: ' . $objetivoAnterior['nome'],
                        ]);
                    }
                    
                    $pdo->commit();
                    $mensagemSucesso = 'Objetivo atualizado com sucesso.';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $mensagemErro = 'Não foi possível atualizar o objetivo: ' . $e->getMessage();
                }
            }
        }
    }
}

// 3) Obter todos os objetivos do utilizador
$stmt = $pdo->prepare('
    SELECT id, nome, valor_objetivo, valor_atual
    FROM objetivos
    WHERE utilizador_id = :uid
    ORDER BY id DESC
');
$stmt->execute(['uid' => $utilizadorId]);
$objetivos = $stmt->fetchAll();

function calcular_percentagem_objetivo($atual, $objetivo)
{
    if ($objetivo <= 0) {
        return 0;
    }
    return min(100, ($atual / $objetivo) * 100);
}

$appTopBarTitle = 'Objetivos';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="ds-page-header">
    <h1>Objetivos financeiros</h1>
    <p>Metas de poupança e progresso ao longo do tempo.</p>
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
                <h5 class="card-title mb-3">Novo objetivo</h5>
                <form method="post" action="">
                    <input type="hidden" name="tipo_form" value="novo">
                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome do objetivo</label>
                        <input type="text" class="form-control" id="nome" name="nome"
                               placeholder="Ex: Viagem, Fundo de emergência" required>
                    </div>
                    <div class="mb-3">
                        <label for="valor_objetivo" class="form-label">Valor objetivo (€)</label>
                        <input type="number" step="0.01" min="0" class="form-control" id="valor_objetivo"
                               name="valor_objetivo" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Criar objetivo</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Os teus objetivos</h5>

                <?php if ($objetivos): ?>
                    <div class="table-responsive ds-table-wrap">
                        <table class="table align-middle">
                            <thead>
                            <tr>
                                <th>Objetivo</th>
                                <th style="width: 160px;">Valor objetivo (€)</th>
                                <th style="width: 160px;">Valor atual (€)</th>
                                <th>Progresso</th>
                                <th class="text-end">Ações</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($objetivos as $obj): ?>
                                <?php
                                $percent = calcular_percentagem_objetivo(
                                    (float)$obj['valor_atual'],
                                    (float)$obj['valor_objetivo']
                                );
                                // Barra de progresso sempre verde (usa cor de sucesso do tema)
                                $classeBarra = '';
                                $corBarra = 'var(--success-color)';
                                $corTexto = 'var(--bg-color)';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($obj['nome']); ?></td>
                                    <td><?php echo number_format((float)$obj['valor_objetivo'], 2, ',', ' '); ?> €</td>
                                    <td>
                                        <form method="post" class="d-flex gap-2 align-items-center"
                                              action="">
                                            <input type="hidden" name="tipo_form" value="atualizar">
                                            <input type="hidden" name="id"
                                                   value="<?php echo (int)$obj['id']; ?>">
                                            <input type="number" step="0.01" min="0"
                                                   class="form-control form-control-sm"
                                                   name="valor_atual"
                                                   value="<?php echo htmlspecialchars($obj['valor_atual']); ?>"
                                                   style="min-width: 120px;">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                Atualizar
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar <?php echo $classeBarra; ?>"
                                                 role="progressbar"
                                                 style="width: <?php echo min(100, $percent); ?>%; background-color: <?php echo $corBarra; ?>;"
                                                 aria-valuenow="<?php echo (int)$percent; ?>"
                                                 aria-valuemin="0"
                                                 aria-valuemax="100">
                                                <span class="small" style="color: <?php echo $corTexto; ?>;">
                                                    <?php echo number_format($percent, 0); ?>%
                                                </span>
                                            </div>
                                        </div>
                                        <?php if ($percent >= 100): ?>
                                            <span class="small" style="color: var(--success-color); font-weight: 600;">Objetivo concluído!</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="objetivos.php?acao=apagar&id=<?php echo (int)$obj['id']; ?>"
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Tens a certeza que queres apagar este objetivo?');">
                                            Apagar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Ainda não definiste qualquer objetivo financeiro.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

