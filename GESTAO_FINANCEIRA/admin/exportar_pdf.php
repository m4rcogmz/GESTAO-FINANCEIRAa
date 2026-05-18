<?php
/**
 * Exportação admin em PDF (pré-visualização inline ou descarga).
 * ?tipo=utilizadores|resumo_financeiro
 * &download=1  → força descarga (attachment)
 * sem download → inline (abre no leitor PDF do browser)
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/AdminPdfExporter.php';

require_admin();

$pdo = getPDO();
$tipo = $_GET['tipo'] ?? '';
$download = isset($_GET['download']) && $_GET['download'] === '1';
$inline = !$download;

$gerado = date('d/m/Y H:i');

$weightsUtilizadores = [1.0, 3.2, 4.0, 2.0, 1.0, 2.8];
$weightsResumo = [1.0, 3.0, 4.0, 3.5, 3.5];

if ($tipo === 'utilizadores') {
    $pdf = new AdminPdfExporter('Admin - Utilizadores');
    $pdf->beginStyledReport(
        'Lista de utilizadores',
        'Administracao - Contas e permissoes na plataforma',
        'Gerado em: ' . $gerado
    );
    $pdf->tableHeaderRow(['ID', 'Nome', 'Email', 'Papel', 'Susp.', 'Registo'], $weightsUtilizadores);
    $q = $pdo->query('SELECT id, nome, email, role, conta_suspensa, data_registo FROM utilizadores ORDER BY id');
    $stripe = false;
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $pdf->tableDataRow([
            (string)$row['id'],
            $row['nome'],
            $row['email'],
            $row['role'] ?? 'user',
            (string)($row['conta_suspensa'] ?? 0),
            (string)($row['data_registo'] ?? ''),
        ], $weightsUtilizadores, $stripe);
        $stripe = !$stripe;
    }
    $pdf->output('utilizadores_' . date('Y-m-d') . '.pdf', $inline);
    exit;
}

if ($tipo === 'resumo_financeiro') {
    $gtr = (float)$pdo->query('SELECT COALESCE(SUM(valor), 0) FROM rendimentos')->fetchColumn();
    $gtd = (float)$pdo->query('SELECT COALESCE(SUM(valor), 0) FROM despesas')->fetchColumn();
    $gsaldo = $gtr - $gtd;

    $pdf = new AdminPdfExporter('Admin - Resumo financeiro');
    $pdf->beginStyledReport(
        'Resumo financeiro',
        'Por utilizador - Totais globais da plataforma',
        'Gerado em: ' . $gerado
    );
    $pdf->mutedLine(
        'Rendimentos (todos): ' . number_format($gtr, 2, ',', ' ') . ' EUR   |   '
        . 'Despesas (todas): ' . number_format($gtd, 2, ',', ' ') . ' EUR   |   '
        . 'Saldo global: ' . number_format($gsaldo, 2, ',', ' ') . ' EUR'
    );
    $pdf->divider();
    $pdf->tableHeaderRow(['ID', 'Nome', 'Email', 'Rendimentos', 'Despesas'], $weightsResumo);
    $sql = '
        SELECT u.id, u.nome, u.email,
            COALESCE((SELECT SUM(r.valor) FROM rendimentos r WHERE r.utilizador_id = u.id), 0) AS tr,
            COALESCE((SELECT SUM(d.valor) FROM despesas d WHERE d.utilizador_id = u.id), 0) AS td
        FROM utilizadores u
        ORDER BY u.id
    ';
    $stripe = false;
    foreach ($pdo->query($sql, PDO::FETCH_ASSOC) as $row) {
        $pdf->tableDataRow([
            (string)$row['id'],
            $row['nome'],
            $row['email'],
            number_format((float)$row['tr'], 2, ',', ' ') . ' EUR',
            number_format((float)$row['td'], 2, ',', ' ') . ' EUR',
        ], $weightsResumo, $stripe);
        $stripe = !$stripe;
    }
    $pdf->output('resumo_financeiro_' . date('Y-m-d') . '.pdf', $inline);
    exit;
}

header('HTTP/1.1 400 Bad Request');
header('Content-Type: text/plain; charset=utf-8');
echo 'Tipo de exportacao invalido.';
