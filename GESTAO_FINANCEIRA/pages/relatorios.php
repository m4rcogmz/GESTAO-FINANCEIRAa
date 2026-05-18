<?php
// pages/relatorios.php
// Relatórios financeiros (versão simples):
// - Selecionar mês e ano
// - Mostrar totais de rendimentos, despesas e saldo
// - Tabela simples de movimentos (rendimentos + despesas)
// - Botão para exportar em PDF

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = getPDO();
$utilizadorId = $_SESSION['utilizador_id'];

// Mês/ano selecionados (por defeito, atuais)
$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');

// 1) Totais de rendimentos e despesas no período escolhido
$stmt = $pdo->prepare('
    SELECT 
        COALESCE(SUM(valor), 0) AS total_rendimentos
    FROM rendimentos
    WHERE utilizador_id = :uid
      AND MONTH(data) = :mes
      AND YEAR(data) = :ano
');
$stmt->execute(['uid' => $utilizadorId, 'mes' => $mes, 'ano' => $ano]);
$totalRendimentosPeriodo = (float)($stmt->fetch()['total_rendimentos'] ?? 0);

$stmt = $pdo->prepare('
    SELECT 
        COALESCE(SUM(valor), 0) AS total_despesas
    FROM despesas
    WHERE utilizador_id = :uid
      AND MONTH(data) = :mes
      AND YEAR(data) = :ano
');
$stmt->execute(['uid' => $utilizadorId, 'mes' => $mes, 'ano' => $ano]);
$totalDespesasPeriodo = (float)($stmt->fetch()['total_despesas'] ?? 0);

$saldoPeriodo = $totalRendimentosPeriodo - $totalDespesasPeriodo;

// 2) Movimentos simples (rendimentos + despesas) no período
$stmt = $pdo->prepare('
    SELECT data, "Rendimento" AS tipo, valor, descricao
    FROM rendimentos
    WHERE utilizador_id = :uid AND MONTH(data) = :mes AND YEAR(data) = :ano
    UNION ALL
    SELECT data, "Despesa" AS tipo, valor, descricao
    FROM despesas
    WHERE utilizador_id = :uid AND MONTH(data) = :mes AND YEAR(data) = :ano
    ORDER BY data DESC, tipo DESC
');
$stmt->execute(['uid' => $utilizadorId, 'mes' => $mes, 'ano' => $ano]);
$movimentos = $stmt->fetchAll();

$appTopBarTitle = 'Relatórios';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="ds-page-header">
    <h1>Relatórios</h1>
    <p>Escolhe o período e analisa totais e movimentos.</p>
</div>

<div class="mb-3">
    <form method="get" class="row g-2 align-items-end">
        <div class="col-auto">
            <label for="mes" class="form-label">Mês</label>
            <select name="mes" id="mes" class="form-select">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                    <option value="<?php echo $m; ?>" <?php echo $m === $mes ? 'selected' : ''; ?>>
                        <?php echo $m; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-auto">
            <label for="ano" class="form-label">Ano</label>
            <input type="number" name="ano" id="ano" class="form-control"
                   value="<?php echo $ano; ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary">Ver relatório</button>
        </div>
        <div class="col-auto">
            <a href="relatorio_pdf.php?mes=<?php echo $mes; ?>&ano=<?php echo $ano; ?>&download=1"
               class="btn btn-primary">Exportar PDF</a>
        </div>
    </form>
    <div class="mt-2">
        <a href="relatorios.php" class="btn btn-outline-secondary btn-sm">← Voltar atrás</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Total de rendimentos</h5>
                <p class="fs-4 mb-0" style="color: var(--primary-color);">
                    <?php echo number_format($totalRendimentosPeriodo, 2, ',', ' '); ?> €
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Total de despesas</h5>
                <p class="fs-4 mb-0" style="color: var(--danger-color); font-weight: 600;">
                    <?php echo number_format($totalDespesasPeriodo, 2, ',', ' '); ?> €
                </p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Saldo do período</h5>
                <p class="fs-4 mb-0" style="color: var(--primary-color);">
                    <?php echo number_format($saldoPeriodo, 2, ',', ' '); ?> €
                </p>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Movimentos do período</h5>
                <?php if ($movimentos): ?>
                    <div class="table-responsive ds-table-wrap">
                        <table class="table table-striped align-middle">
                            <thead>
                            <tr>
                                <th>Data</th>
                                <th>Tipo</th>
                                <th class="text-end">Valor (€)</th>
                                <th>Descrição</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($movimentos as $mov): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($mov['data'])); ?></td>
                                    <td>
                                        <span class="badge" style="background: var(--primary-color); color: var(--bg-color);">
                                            <?php echo htmlspecialchars($mov['tipo']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end" style="<?php echo $mov['tipo'] === 'Despesa' ? 'color: var(--danger-color); font-weight: 600;' : 'color: var(--success-color); font-weight: 600;'; ?>">
                                        <?php echo number_format((float)$mov['valor'], 2, ',', ' '); ?> €
                                    </td>
                                    <td><?php echo nl2br(htmlspecialchars($mov['descricao'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Não existem movimentos registados neste período.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

