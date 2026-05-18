<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getPDO();

$totalLogs = 0;
$logs30d = 0;
$ultimos = [];

try {
    $totalLogs = (int)$pdo->query('SELECT COUNT(*) FROM relatorio_acessos_log')->fetchColumn();
    $logs30d = (int)$pdo->query("SELECT COUNT(*) FROM relatorio_acessos_log WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    $stmt = $pdo->query('SELECT COUNT(DISTINCT utilizador_id) AS c FROM relatorio_acessos_log');
    $unicos = (int)$stmt->fetchColumn();
    $st = $pdo->query('
        SELECT l.id, l.mes, l.ano, l.criado_em, u.nome, u.email
        FROM relatorio_acessos_log l
        INNER JOIN utilizadores u ON u.id = l.utilizador_id
        ORDER BY l.criado_em DESC
        LIMIT 40
    ');
    $ultimos = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $unicos = 0;
}

$adminPageTitle = 'Relatórios e exportação';
$adminNavActive = 'relatorios';
$adminTopBarTitle = 'Relatórios';
require_once __DIR__ . '/../includes/admin_header.php';
?>

<div class="ds-page-header">
    <h1>Relatórios &amp; dados</h1>
    <p>Estatísticas de utilização e exportação em PDF (pré-visualização no browser).</p>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="admin-stat-card">
            <div class="label">Total de acessos registados</div>
            <div class="value"><?php echo $totalLogs; ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-stat-card">
            <div class="label">Últimos 30 dias</div>
            <div class="value"><?php echo $logs30d; ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="admin-stat-card">
            <div class="label">Utilizadores distintos</div>
            <div class="value"><?php echo $unicos; ?></div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-body">
        <h5 class="card-title mb-2">Exportar dados</h5>
        <p class="text-muted small mb-0">
            Dois relatórios de administrador. Em cada linha: pré-visualização PDF no browser, descarga do PDF e ficheiro <strong>CSV</strong> (abre no Excel).
        </p>
        <div class="table-responsive mt-3 rounded-3 border">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col" style="min-width: 11rem;">Relatório</th>
                        <th scope="col" class="d-none d-lg-table-cell">Descrição</th>
                        <th scope="col" class="text-lg-end text-nowrap">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="fw-semibold" style="color: var(--heading-color);">Utilizadores</td>
                        <td class="d-none d-lg-table-cell small text-muted">Lista com papel e estado da conta.</td>
                        <td class="text-lg-end">
                            <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                                <a class="btn btn-sm btn-outline-primary" href="exportar_pdf.php?tipo=utilizadores" target="_blank" rel="noopener" title="Abrir PDF neste separador ou num novo"><i class="bi bi-file-earmark-pdf"></i> Ver PDF</a>
                                <a class="btn btn-sm btn-primary" href="exportar_pdf.php?tipo=utilizadores&amp;download=1"><i class="bi bi-download"></i> Descarregar PDF</a>
                                <a class="btn btn-sm btn-success" href="exportar.php?tipo=utilizadores" title="CSV com separador ; (compatível com Excel)"><i class="bi bi-file-earmark-spreadsheet"></i> Descarregar Excel</a>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td class="fw-semibold" style="color: var(--heading-color);">Resumo financeiro</td>
                        <td class="d-none d-lg-table-cell small text-muted">Totais de rendimentos e despesas por utilizador.</td>
                        <td class="text-lg-end">
                            <div class="d-flex flex-wrap gap-2 justify-content-lg-end">
                                <a class="btn btn-sm btn-outline-primary" href="exportar_pdf.php?tipo=resumo_financeiro" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf"></i> Ver PDF</a>
                                <a class="btn btn-sm btn-primary" href="exportar_pdf.php?tipo=resumo_financeiro&amp;download=1"><i class="bi bi-download"></i> Descarregar PDF</a>
                                <a class="btn btn-sm btn-success" href="exportar.php?tipo=resumo_financeiro"><i class="bi bi-file-earmark-spreadsheet"></i> Descarregar Excel</a>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <h5 class="card-title mb-3">Últimos acessos ao relatório (impressão / PDF)</h5>
        <div class="table-responsive ds-table-wrap">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Utilizador</th>
                            <th>Período</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ultimos)): ?>
                            <tr><td colspan="3" class="text-muted">Sem registos (abre um relatório na app para gerar estatísticas).</td></tr>
                        <?php else: ?>
                            <?php foreach ($ultimos as $r): ?>
                                <tr>
                                    <td class="small"><?php echo htmlspecialchars($r['criado_em']); ?></td>
                                    <td><?php echo htmlspecialchars($r['nome']); ?><br><span class="text-muted small"><?php echo htmlspecialchars($r['email']); ?></span></td>
                                    <td><?php echo (int)$r['mes']; ?> / <?php echo (int)$r['ano']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
