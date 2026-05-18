<?php
// pages/relatorio_pdf.php
// Gera relatório em HTML (impressão) ou PDF real com download=1.

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo = getPDO();
$utilizadorId = $_SESSION['utilizador_id'];
$utilizadorNome = $_SESSION['utilizador_nome'] ?? 'Utilizador';

// Determinar tema do utilizador (mesma lógica do header)
$temaSessaoRaw = $_SESSION['utilizador_tema'] ?? 'dark';
$mapTemasAntigos = [
    'escuro'    => 'dark',
    'claro'     => 'light',
    'neon-azul' => 'dark',
    'roxo'      => 'purple',
    'vermelho'  => 'red',
    'cinza'     => 'gray',
];

if (isset($mapTemasAntigos[$temaSessaoRaw])) {
    $temaSessao = $mapTemasAntigos[$temaSessaoRaw];
} else {
    $temaSessao = $temaSessaoRaw;
}

$temasPermitidos = ['dark', 'light', 'purple', 'red', 'gray'];
if (!in_array($temaSessao, $temasPermitidos, true)) {
    $temaSessao = 'dark';
}

$classeTema = 'theme-' . $temaSessao;

$mes = isset($_GET['mes']) ? (int)$_GET['mes'] : (int)date('m');
$ano = isset($_GET['ano']) ? (int)$_GET['ano'] : (int)date('Y');
$download = isset($_GET['download']) && $_GET['download'] == '1';

// Totais
$stmt = $pdo->prepare('
    SELECT COALESCE(SUM(valor), 0) AS total_r
    FROM rendimentos
    WHERE utilizador_id = :uid AND MONTH(data) = :mes AND YEAR(data) = :ano
');
$stmt->execute(['uid' => $utilizadorId, 'mes' => $mes, 'ano' => $ano]);
$totalR = (float)($stmt->fetch()['total_r'] ?? 0);

$stmt = $pdo->prepare('
    SELECT COALESCE(SUM(valor), 0) AS total_d
    FROM despesas
    WHERE utilizador_id = :uid AND MONTH(data) = :mes AND YEAR(data) = :ano
');
$stmt->execute(['uid' => $utilizadorId, 'mes' => $mes, 'ano' => $ano]);
$totalD = (float)($stmt->fetch()['total_d'] ?? 0);

$saldo = $totalR - $totalD;

// Movimentos
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

// Nomes dos meses em português
$meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
    'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$mesNome = $meses[$mes] ?? (string)$mes;

// Registo simples para estatísticas na área admin
try {
    $pdo->prepare('INSERT INTO relatorio_acessos_log (utilizador_id, mes, ano) VALUES (:uid, :mes, :ano)')
        ->execute(['uid' => $utilizadorId, 'mes' => $mes, 'ano' => $ano]);
} catch (Throwable $e) {
    // tabela pode não existir em bases muito antigas
}

// Download = PDF real (antes: headers PDF + redirect para HTML → leitor PDF vazio)
if ($download) {
    require_once __DIR__ . '/../includes/AdminPdfExporter.php';
    $pdf = new AdminPdfExporter('Relatorio ' . $mesNome . ' ' . $ano);
    $pdf->beginStyledReport(
        'Relatorio financeiro',
        $mesNome . ' de ' . $ano . ' - ' . $utilizadorNome,
        'Gerado em: ' . date('d/m/Y H:i')
    );
    $pdf->mutedLine(
        'Total rendimentos: ' . number_format($totalR, 2, ',', ' ') . ' EUR   |   '
        . 'Total despesas: ' . number_format($totalD, 2, ',', ' ') . ' EUR   |   '
        . 'Saldo do periodo: ' . number_format($saldo, 2, ',', ' ') . ' EUR'
    );
    $pdf->divider();
    $wMov = [2.0, 2.2, 2.4, 5.4];
    $pdf->tableHeaderRow(['Data', 'Tipo', 'Valor', 'Descricao'], $wMov);
    $stripe = false;
    foreach ($movimentos as $mov) {
        $pdf->tableDataRow([
            date('d/m/Y', strtotime((string)$mov['data'])),
            (string)$mov['tipo'],
            number_format((float)$mov['valor'], 2, ',', ' ') . ' EUR',
            (string)($mov['descricao'] ?? '-'),
        ], $wMov, $stripe);
        $stripe = !$stripe;
    }
    $pdf->output('relatorio_' . $mes . '_' . $ano . '.pdf', false);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório Financeiro - <?php echo $mesNome . ' ' . $ano; ?></title>
    <link rel="stylesheet" href="../assets/css/themes.css">
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
        }
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background: var(--bg-color);
            color: var(--text-color);
        }
        .relatorio-container {
            background: var(--card-color);
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
            border: 1px solid var(--border-color);
        }
        h1 {
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 30px;
            font-size: 28px;
        }
        .info-section {
            margin-bottom: 25px;
            padding: 15px;
            background: var(--hover-color);
            border-radius: 8px;
            border-left: 3px solid var(--primary-color);
        }
        .info-section p {
            margin: 5px 0;
            font-size: 14px;
            color: var(--text-color);
        }
        .totais-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 25px 0;
        }
        .total-card {
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            background: var(--hover-color);
            border: 1px solid var(--border-color);
        }
        .total-card h3 {
            margin: 0 0 5px 0;
            font-size: 14px;
            font-weight: normal;
            color: var(--text-muted);
        }
        .total-card .valor {
            font-size: 24px;
            font-weight: bold;
            color: var(--primary-color);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background: var(--hover-color);
            color: var(--text-color);
            padding: 12px;
            text-align: left;
            font-weight: bold;
            border-bottom: 2px solid var(--primary-color);
        }
        td {
            padding: 10px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }
        tr:hover {
            background: var(--hover-color);
        }
        .tipo-rendimento {
            color: var(--success-color);
            font-weight: bold;
        }
        .tipo-despesa {
            color: var(--danger-color);
            font-weight: bold;
        }
        .valor-rendimento {
            color: var(--success-color);
            font-weight: 600;
        }
        .valor-despesa {
            color: var(--danger-color);
            font-weight: 600;
        }
        .btn-print {
            background: var(--primary-color);
            color: var(--bg-color);
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            margin: 20px auto;
            display: block;
            transition: all 0.3s ease;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
        }
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
        }
        .btn-download {
            background: var(--primary-color);
            color: var(--bg-color);
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            margin: 10px auto;
            display: block;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
        }
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.3);
            color: var(--bg-color);
        }
    </style>
</head>
<body class="<?php echo htmlspecialchars($classeTema); ?>">
<div class="relatorio-container">
    <h1>Relatório Financeiro</h1>
    
    <div class="info-section">
        <p><strong>Utilizador:</strong> <?php echo htmlspecialchars($utilizadorNome); ?></p>
        <p><strong>Período:</strong> <?php echo htmlspecialchars($mesNome . ' de ' . $ano); ?></p>
        <p><strong>Data do relatório:</strong> <?php echo date('d/m/Y H:i'); ?></p>
    </div>

    <div class="totais-grid">
        <div class="total-card">
            <h3>Total de Rendimentos</h3>
            <div class="valor"><?php echo number_format($totalR, 2, ',', ' '); ?> €</div>
        </div>
        <div class="total-card">
            <h3>Total de Despesas</h3>
            <div class="valor" style="color: var(--danger-color);"><?php echo number_format($totalD, 2, ',', ' '); ?> €</div>
        </div>
        <div class="total-card">
            <h3>Saldo do Período</h3>
            <div class="valor"><?php echo number_format($saldo, 2, ',', ' '); ?> €</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Data</th>
                <th>Tipo</th>
                <th>Valor</th>
                <th>Descrição</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($movimentos)): ?>
                <tr>
                    <td colspan="4" style="text-align: center; padding: 20px; color: #b0b0b0;">
                        Não existem movimentos registados neste período.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($movimentos as $mov): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($mov['data'])); ?></td>
                        <td>
                            <span class="<?php echo $mov['tipo'] === 'Rendimento' ? 'tipo-rendimento' : 'tipo-despesa'; ?>">
                                <?php echo htmlspecialchars($mov['tipo']); ?>
                            </span>
                        </td>
                        <td class="<?php echo $mov['tipo'] === 'Rendimento' ? 'valor-rendimento' : 'valor-despesa'; ?>">
                            <?php echo number_format((float)$mov['valor'], 2, ',', ' '); ?> €
                        </td>
                        <td><?php echo htmlspecialchars($mov['descricao'] ?? '-'); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="no-print" style="text-align: center; margin-top: 30px;">
        <button class="btn-print" onclick="window.print()">🖨️ Exportar PDF</button>
        <a href="relatorios.php?mes=<?php echo $mes; ?>&ano=<?php echo $ano; ?>" class="btn-download" style="margin-top: 10px;">
            ← Voltar atrás
        </a>
        <p style="color: var(--text-muted); font-size: 12px; margin-top: 15px;">
            Para guardar como PDF, clica no botão acima e escolhe "Guardar como PDF" na janela de impressão.
        </p>
    </div>
</div>
</body>
</html>
