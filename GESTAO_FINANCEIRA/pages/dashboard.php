<?php
// pages/dashboard.php
// Dashboard principal:
// - Saldo atual (rendimentos - despesas)
// - Total de rendimentos do mês atual
// - Total de despesas do mês atual
// - Gráfico circular de despesas por categoria
// - Gráfico de barras (entradas vs saídas)

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Garante que só utilizadores autenticados podem aceder
require_login();

$pdo = getPDO();
$utilizadorId = $_SESSION['utilizador_id'];

// Mês e ano atual
$mesAtual = (int)date('m');
$anoAtual = (int)date('Y');

// 1) Total de rendimentos (todos e do mês atual)
$stmt = $pdo->prepare('
    SELECT 
        SUM(valor) AS total_todos,
        SUM(CASE WHEN MONTH(data) = :mes AND YEAR(data) = :ano THEN valor ELSE 0 END) AS total_mes
    FROM rendimentos
    WHERE utilizador_id = :uid
');
$stmt->execute([
    'mes' => $mesAtual,
    'ano' => $anoAtual,
    'uid' => $utilizadorId,
]);
$rendimentosData = $stmt->fetch() ?: ['total_todos' => 0, 'total_mes' => 0];

$totalRendimentos = (float)($rendimentosData['total_todos'] ?? 0);
$totalRendimentosMes = (float)($rendimentosData['total_mes'] ?? 0);

// 2) Total de despesas (todas e do mês atual)
$stmt = $pdo->prepare('
    SELECT 
        SUM(valor) AS total_todos,
        SUM(CASE WHEN MONTH(data) = :mes AND YEAR(data) = :ano THEN valor ELSE 0 END) AS total_mes
    FROM despesas
    WHERE utilizador_id = :uid
');
$stmt->execute([
    'mes' => $mesAtual,
    'ano' => $anoAtual,
    'uid' => $utilizadorId,
]);
$despesasData = $stmt->fetch() ?: ['total_todos' => 0, 'total_mes' => 0];

$totalDespesas = (float)($despesasData['total_todos'] ?? 0);
$totalDespesasMes = (float)($despesasData['total_mes'] ?? 0);

// 3) Saldo atual (considerando todos os registos)
$saldoAtual = $totalRendimentos - $totalDespesas;

// 4) Despesas por categoria (mês atual) para gráfico circular
$stmt = $pdo->prepare('
    SELECT c.nome AS categoria, SUM(d.valor) AS total
    FROM despesas d
    INNER JOIN categorias c ON c.id = d.categoria_id
    WHERE d.utilizador_id = :uid
      AND MONTH(d.data) = :mes
      AND YEAR(d.data) = :ano
    GROUP BY c.id, c.nome
    ORDER BY c.nome
');
$stmt->execute([
    'uid' => $utilizadorId,
    'mes' => $mesAtual,
    'ano' => $anoAtual,
]);
$despesasPorCategoria = $stmt->fetchAll() ?: [];

// Preparar arrays para o Chart.js (PHP -> JSON)
$labelsCategorias = [];
$valoresCategorias = [];
foreach ($despesasPorCategoria as $linha) {
    $labelsCategorias[] = $linha['categoria'];
    $valoresCategorias[] = (float)$linha['total'];
}

// 5) Dados para gráfico de barras (entradas vs saídas no mês atual)
$entradasVsSaidasLabels = ['Rendimentos', 'Despesas'];
$entradasVsSaidasValores = [$totalRendimentosMes, $totalDespesasMes];

// 6) Objetivos financeiros
$stmt = $pdo->prepare('
    SELECT id, nome, valor_objetivo, valor_atual
    FROM objetivos
    WHERE utilizador_id = :uid
    ORDER BY id DESC
    LIMIT 5
');
$stmt->execute(['uid' => $utilizadorId]);
$objetivos = $stmt->fetchAll();

// 7) Estado dos orçamentos (categorias que ultrapassaram o limite)
$stmt = $pdo->prepare('
    SELECT c.nome AS categoria, o.limite, COALESCE(SUM(d.valor), 0) AS gasto
    FROM categorias c
    INNER JOIN orcamentos o ON o.categoria_id = c.id AND o.utilizador_id = :uid AND o.mes = :mes AND o.ano = :ano
    LEFT JOIN despesas d ON d.categoria_id = c.id AND d.utilizador_id = :uid AND MONTH(d.data) = :mes AND YEAR(d.data) = :ano
    GROUP BY c.id, c.nome, o.limite
    HAVING COALESCE(SUM(d.valor), 0) > o.limite
    ORDER BY (COALESCE(SUM(d.valor), 0) - o.limite) DESC
    LIMIT 5
');
$stmt->execute([
    'uid' => $utilizadorId,
    'mes' => $mesAtual,
    'ano' => $anoAtual,
]);
$orcamentosUltrapassados = $stmt->fetchAll();

$appTopBarTitle = 'Dashboard';
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="ds-page-header">
    <h1>Dashboard</h1>
    <p>
        Olá, <strong><?php echo htmlspecialchars($_SESSION['utilizador_nome'] ?? ''); ?></strong>
        — resumo financeiro do período atual.
    </p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="ds-stat">
            <div class="ds-stat__label">Saldo atual</div>
            <div class="ds-stat__value ds-stat__value--accent"><?php echo number_format($saldoAtual, 2, ',', ' '); ?> €</div>
            <p class="mb-0 mt-2 small text-muted">Rendimentos totais − despesas totais</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="ds-stat">
            <div class="ds-stat__label">Rendimentos (mês)</div>
            <div class="ds-stat__value ds-stat__value--accent"><?php echo number_format($totalRendimentosMes, 2, ',', ' '); ?> €</div>
            <p class="mb-0 mt-2 small text-muted"><?php echo $mesAtual . '/' . $anoAtual; ?></p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="ds-stat">
            <div class="ds-stat__label">Despesas (mês)</div>
            <div class="ds-stat__value ds-stat__value--danger"><?php echo number_format($totalDespesasMes, 2, ',', ' '); ?> €</div>
            <p class="mb-0 mt-2 small text-muted"><?php echo $mesAtual . '/' . $anoAtual; ?></p>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Despesas por categoria (mês atual)</h5>
                <?php if (!empty($labelsCategorias)): ?>
                    <div class="chart-canvas-wrap mt-2"><canvas id="graficoDespesasCategorias"></canvas></div>
                <?php else: ?>
                    <div class="ds-empty mt-3"><div class="ds-empty__icon"><i class="bi bi-pie-chart"></i></div><p class="mb-0">Sem despesas neste mês.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title">Entradas vs saídas (mês atual)</h5>
                <?php if ($totalRendimentosMes > 0 || $totalDespesasMes > 0): ?>
                    <div class="chart-canvas-wrap mt-2"><canvas id="graficoEntradasSaidas"></canvas></div>
                <?php else: ?>
                    <div class="ds-empty mt-3"><div class="ds-empty__icon"><i class="bi bi-bar-chart-line"></i></div><p class="mb-0">Sem movimentos neste mês.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Progresso dos Objetivos</h5>
                <?php if (!empty($objetivos)): ?>
                    <?php foreach ($objetivos as $obj): ?>
                        <?php
                        $percent = $obj['valor_objetivo'] > 0 ? min(100, ($obj['valor_atual'] / $obj['valor_objetivo']) * 100) : 0;
                        // Barra de progresso dos objetivos segue a cor de sucesso do tema (verde claro)
                        $corBarra = 'var(--success)';
                        ?>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="small"><?php echo htmlspecialchars($obj['nome']); ?></span>
                                <span class="small"><?php echo number_format($percent, 0); ?>%</span>
                            </div>
                            <div class="progress" style="height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo min(100, $percent); ?>%; background-color: <?php echo $corBarra; ?>;"></div>
                            </div>
                            <div class="d-flex justify-content-between mt-1">
                                <span class="small text-muted"><?php echo number_format((float)$obj['valor_atual'], 2, ',', ' '); ?> €</span>
                                <span class="small text-muted"><?php echo number_format((float)$obj['valor_objetivo'], 2, ',', ' '); ?> €</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="objetivos.php" class="btn btn-sm btn-outline-primary w-100 mt-2">Ver todos os objetivos</a>
                <?php else: ?>
                    <p class="text-muted mb-0">Ainda não definiste objetivos financeiros.</p>
                    <a href="objetivos.php" class="btn btn-sm btn-primary w-100 mt-2">Criar objetivo</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title mb-3">Estado dos Orçamentos</h5>
                <?php if (!empty($orcamentosUltrapassados)): ?>
                    <div class="alert alert-danger mb-3">
                        <strong>Atenção!</strong> Alguns orçamentos foram ultrapassados este mês.
                    </div>
                    <?php foreach ($orcamentosUltrapassados as $orc): ?>
                        <div class="mb-2 p-3 rounded-3" style="background: var(--nav-hover-bg); border: 1px solid var(--border-subtle); border-left: 3px solid var(--danger);">
                            <div class="d-flex justify-content-between">
                                <strong class="small"><?php echo htmlspecialchars($orc['categoria']); ?></strong>
                                <span class="small fw-semibold text-danger">
                                    +<?php echo number_format((float)$orc['gasto'] - (float)$orc['limite'], 2, ',', ' '); ?> €
                                </span>
                            </div>
                            <div class="small text-muted">
                                Limite: <?php echo number_format((float)$orc['limite'], 2, ',', ' '); ?> € | 
                                Gasto: <?php echo number_format((float)$orc['gasto'], 2, ',', ' '); ?> €
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <a href="orcamentos.php" class="btn btn-sm btn-outline-primary w-100 mt-2">Gerir orçamentos</a>
                <?php else: ?>
                    <p class="text-muted mb-0">Todos os orçamentos estão dentro dos limites definidos.</p>
                    <a href="orcamentos.php" class="btn btn-sm btn-primary w-100 mt-2">Ver orçamentos</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    const categoriasLabels = <?php echo json_encode($labelsCategorias, JSON_UNESCAPED_UNICODE); ?>;
    const categoriasValores = <?php echo json_encode($valoresCategorias, JSON_UNESCAPED_UNICODE); ?>;
    const entradasSaidasLabels = <?php echo json_encode($entradasVsSaidasLabels, JSON_UNESCAPED_UNICODE); ?>;
    const entradasSaidasValores = <?php echo json_encode($entradasVsSaidasValores, JSON_UNESCAPED_UNICODE); ?>;

    function hashStringToHue(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            hash = ((hash << 5) - hash) + str.charCodeAt(i);
            hash |= 0;
        }
        return Math.abs(hash) % 360;
    }

    function colorsForCategories(labels) {
        const pal = (typeof financeChartPalette === 'function') ? financeChartPalette() : ['#22d3ee', '#a78bfa', '#34d399', '#fbbf24', '#fb7185'];
        return labels.map(function (label, idx) {
            if (idx < pal.length) return pal[idx];
            const hue = hashStringToHue(label);
            return 'hsl(' + hue + ' 65% 52%)';
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const T = (typeof getFinanceChartTheme === 'function') ? getFinanceChartTheme() : {};

        const commonLegend = {
            position: 'bottom',
            labels: {
                color: T.textSecondary || '#94a3b8',
                padding: 14,
                usePointStyle: true
            }
        };

        if (categoriasLabels.length > 0) {
            const ctxPie = document.getElementById('graficoDespesasCategorias');
            if (ctxPie) {
                new Chart(ctxPie, {
                    type: 'doughnut',
                    data: {
                        labels: categoriasLabels,
                        datasets: [{
                            data: categoriasValores,
                            backgroundColor: colorsForCategories(categoriasLabels),
                            borderWidth: 0
                        }]
                    },
                    options: {
                        cutout: '58%',
                        plugins: { legend: commonLegend }
                    }
                });
            }
        }

        if (entradasSaidasValores[0] > 0 || entradasSaidasValores[1] > 0) {
            const ctxBar = document.getElementById('graficoEntradasSaidas');
            if (ctxBar) {
                new Chart(ctxBar, {
                    type: 'bar',
                    data: {
                        labels: entradasSaidasLabels,
                        datasets: [{
                            label: 'Valor (€)',
                            data: entradasSaidasValores,
                            backgroundColor: [T.success || '#34d399', T.danger || '#f87171'],
                            borderRadius: 8
                        }]
                    },
                    options: {
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { color: T.textSecondary }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: T.grid },
                                ticks: { color: T.textSecondary }
                            }
                        },
                        plugins: { legend: { display: false } }
                    }
                });
            }
        }
    });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

