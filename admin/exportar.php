<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
require_admin();

header('Cache-Control: no-store, private');
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="briefings-s3-' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Cliente', 'E-mail', 'Telefone', 'Status', 'Criado em', 'Enviado em', 'Pergunta', 'Resposta'], ';');

$sql = "SELECT c.nome, c.email, c.telefone, c.status, c.criado_em, r.enviado_em, r.respostas_json
        FROM clientes c LEFT JOIN respostas r ON r.cliente_id = c.id
        ORDER BY c.criado_em DESC, r.enviado_em DESC";
foreach (db()->query($sql) as $row) {
    $data = !empty($row['respostas_json']) ? (json_decode((string) $row['respostas_json'], true) ?: []) : [];
    if ($data === []) {
        fputcsv($output, [$row['nome'], $row['email'], $row['telefone'], $row['status'], $row['criado_em'], '', '', ''], ';');
        continue;
    }
    foreach ($data as $question => $answer) {
        fputcsv($output, [$row['nome'], $row['email'], $row['telefone'], $row['status'], $row['criado_em'], $row['enviado_em'], $question, is_array($answer) ? implode(', ', array_map('strval', $answer)) : $answer], ';');
    }
}
fclose($output);
