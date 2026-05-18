<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$pdo = getPDO();
$tipo = $_GET['tipo'] ?? '';

if ($tipo === 'utilizadores') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="utilizadores_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['id', 'nome', 'email', 'role', 'conta_suspensa', 'data_registo'], ';');
    $q = $pdo->query('SELECT id, nome, email, role, conta_suspensa, data_registo FROM utilizadores ORDER BY id');
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

if ($tipo === 'resumo_financeiro') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="resumo_financeiro_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['utilizador_id', 'nome', 'email', 'total_rendimentos', 'total_despesas'], ';');
    $sql = '
        SELECT u.id, u.nome, u.email,
            COALESCE((SELECT SUM(r.valor) FROM rendimentos r WHERE r.utilizador_id = u.id), 0) AS tr,
            COALESCE((SELECT SUM(d.valor) FROM despesas d WHERE d.utilizador_id = u.id), 0) AS td
        FROM utilizadores u
        ORDER BY u.id
    ';
    foreach ($pdo->query($sql, PDO::FETCH_ASSOC) as $row) {
        fputcsv($out, [
            $row['id'],
            $row['nome'],
            $row['email'],
            str_replace('.', ',', (string)$row['tr']),
            str_replace('.', ',', (string)$row['td']),
        ], ';');
    }
    fclose($out);
    exit;
}

header('HTTP/1.1 400 Bad Request');
echo 'Tipo de exportação inválido.';
