<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getPDO();

$totalUtilizadores = (int)$pdo->query('SELECT COUNT(*) FROM utilizadores')->fetchColumn();
$novos7d = (int)$pdo->query("SELECT COUNT(*) FROM utilizadores WHERE data_registo >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

$totalRendimentos = (float)$pdo->query('SELECT COALESCE(SUM(valor), 0) FROM rendimentos')->fetchColumn();
$totalDespesas = (float)$pdo->query('SELECT COALESCE(SUM(valor), 0) FROM despesas')->fetchColumn();
$totalObjetivos = (int)$pdo->query('SELECT COUNT(*) FROM objetivos')->fetchColumn();
$totalCategorias = (int)$pdo->query('SELECT COUNT(*) FROM categorias')->fetchColumn();

$stmt = $pdo->query("
    SELECT DATE_FORMAT(data_registo, '%Y-%m') AS ym, COUNT(*) AS cnt
    FROM utilizadores
    WHERE data_registo >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY ym
    ORDER BY ym ASC
");
$registosPorMes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labelsMes = [];
$valsNovos = [];
foreach ($registosPorMes as $r) {
    $labelsMes[] = $r['ym'];
    $valsNovos[] = (int)$r['cnt'];
}

$atividade = [];

$st = $pdo->query('
    SELECT nome, email, data_registo AS dt, "registo" AS tipo
    FROM utilizadores
    ORDER BY data_registo DESC
    LIMIT 6
');
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $atividade[] = [
        'tipo' => 'Novo utilizador',
        'texto' => $row['nome'] . ' (' . $row['email'] . ')',
        'data' => $row['dt'],
    ];
}

$st = $pdo->query('
    SELECT d.valor, d.data AS dt, d.descricao, u.nome AS uname
    FROM despesas d
    INNER JOIN utilizadores u ON u.id = d.utilizador_id
    ORDER BY d.data DESC, d.id DESC
    LIMIT 6
');
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $atividade[] = [
        'tipo' => 'Despesa',
        'texto' => ($row['descricao'] ?: 'Sem descrição') . ' — ' . $row['uname'] . ' (' . number_format((float)$row['valor'], 2, ',', ' ') . ' €)',
        'data' => $row['dt'],
    ];
}

usort($atividade, static function ($a, $b) {
    return strtotime((string)$b['data']) <=> strtotime((string)$a['data']);
});
$atividade = array_slice($atividade, 0, 10);

$adminPageTitle = 'Dashboard administrativo';
$adminNavActive = 'dashboard';
$adminTopBarTitle = 'Resumo global';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="ds-page-header">
    <h1><?php echo htmlspecialchars($adminPageTitle); ?></h1>
    <p>Visão geral da plataforma, métricas e atividade recente.</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="admin-stat-card">
            <div class="label">Utilizadores</div>
            <div class="value"><?php echo $totalUtilizadores; ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="admin-stat-card">
            <div class="label">Novos (7 dias)</div>
            <div class="value"><?php echo $novos7d; ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="admin-stat-card">
            <div class="label">Rendimentos (total)</div>
            <div class="value" style="font-size: 1.25rem;"><?php echo number_format($totalRendimentos, 0, ',', ' '); ?> €</div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="admin-stat-card">
            <div class="label">Despesas (total)</div>
            <div class="value" style="font-size: 1.25rem; color: var(--danger-color);"><?php echo number_format($totalDespesas, 0, ',', ' '); ?> €</div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="admin-stat-card">
            <div class="label">Objetivos</div>
            <div class="value"><?php echo $totalObjetivos; ?></div>
        </div>
    </div>
    <div class="col-6 col-lg-4 col-xl-2">
        <div class="admin-stat-card">
            <div class="label">Categorias</div>
            <div class="value"><?php echo $totalCategorias; ?></div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Registos de utilizadores por mês</h5>
                <?php if (!empty($labelsMes)): ?>
                    <div class="chart-canvas-wrap"><canvas id="chartRegistos" height="120"></canvas></div>
                <?php else: ?>
                    <div class="ds-empty mt-2"><div class="ds-empty__icon"><i class="bi bi-graph-up"></i></div><p class="mb-0 small">Sem dados de registos neste intervalo.</p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
            <div class="card-body">
                <h5 class="card-title mb-3">Rendimentos vs despesas (plataforma)</h5>
                <div class="chart-canvas-wrap"><canvas id="chartPlat" height="200"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h5 class="card-title mb-3">Atividade recente</h5>
        <div class="table-responsive ds-table-wrap">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Detalhe</th>
                        <th>Data</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($atividade)): ?>
                        <tr><td colspan="3" class="text-muted">Sem atividade para mostrar.</td></tr>
                    <?php else: ?>
                        <?php foreach ($atividade as $a): ?>
                            <tr>
                                <td><span class="badge" style="background: var(--hover-color); color: var(--primary-color);"><?php echo htmlspecialchars($a['tipo']); ?></span></td>
                                <td><?php echo htmlspecialchars($a['texto']); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime((string)$a['data']))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const T = (typeof getFinanceChartTheme === 'function') ? getFinanceChartTheme() : {};
    const labelsMes = <?php echo json_encode($labelsMes, JSON_UNESCAPED_UNICODE); ?>;
    const valsNovos = <?php echo json_encode($valsNovos, JSON_UNESCAPED_UNICODE); ?>;

    if (labelsMes.length && document.getElementById('chartRegistos')) {
        const rgb = T.accent || '#22d3ee';
        new Chart(document.getElementById('chartRegistos'), {
            type: 'line',
            data: {
                labels: labelsMes,
                datasets: [{
                    label: 'Novos utilizadores',
                    data: valsNovos,
                    borderColor: rgb,
                    backgroundColor: T.accentMuted || 'rgba(34,211,238,0.12)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { color: T.textSecondary }, grid: { display: false } },
                    y: { beginAtZero: true, ticks: { color: T.textSecondary }, grid: { color: T.grid } }
                }
            }
        });
    }

    const ctxP = document.getElementById('chartPlat');
    if (ctxP) {
        new Chart(ctxP, {
            type: 'doughnut',
            data: {
                labels: ['Rendimentos', 'Despesas'],
                datasets: [{
                    data: [<?php echo json_encode($totalRendimentos); ?>, <?php echo json_encode($totalDespesas); ?>],
                    backgroundColor: [T.success || '#34d399', T.danger || '#f87171'],
                    borderWidth: 0
                }]
            },
            options: {
                cutout: '55%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: T.textSecondary, padding: 14, usePointStyle: true }
                    }
                }
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
