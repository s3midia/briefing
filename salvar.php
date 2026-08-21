<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: /');
    exit;
}

if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 200000) {
    app_error_page('Envio muito grande', 'Revise as respostas e tente novamente.', 413);
}

$token = strtolower(trim((string) ($_POST['client_token'] ?? '')));
$backUrl = preg_match('/^[a-f0-9]{48}$/', $token) ? '/?c=' . rawurlencode($token) : '/';

if (!empty($_POST['bot-field'])) {
    $_SESSION['briefing_name'] = 'cliente';
    header('Location: /obrigado.php');
    exit;
}

if (!csrf_validate(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null, 'public')) {
    app_error_page('Sessão expirada', 'Volte ao formulário, confira as respostas salvas e envie novamente.', 419, $backUrl);
}

$ignored = ['client_token', 'csrf_token', 'bot-field'];
$answers = [];
$totalLength = 0;

foreach ($_POST as $key => $value) {
    if (in_array($key, $ignored, true) || !is_string($key)) {
        continue;
    }
    $cleanKey = mb_substr(trim($key), 0, 180);
    if ($cleanKey === '') {
        continue;
    }

    $values = is_array($value) ? $value : [$value];
    $cleanValues = [];
    foreach (array_slice($values, 0, 30) as $item) {
        if (!is_string($item)) {
            continue;
        }
        $clean = mb_substr(trim(str_replace("\0", '', $item)), 0, 5000);
        $totalLength += mb_strlen($clean);
        $cleanValues[] = $clean;
    }
    if ($cleanValues !== []) {
        $answers[$cleanKey] = count($cleanValues) === 1 ? $cleanValues[0] : $cleanValues;
    }
}

if (count($answers) > 120 || $totalLength > 120000) {
    app_error_page('Respostas muito extensas', 'Reduza um pouco o conteúdo e tente novamente.', 422, $backUrl);
}

$required = [];

foreach ($required as $field) {
    if (!isset($answers[$field]) || !is_string($answers[$field]) || trim($answers[$field]) === '') {
        redirect_form_error($token, 'Preencha este campo obrigatório antes de enviar.', $field);
    }
}
if (($answers['Consentimento'] ?? '') !== 'Sim') {
    redirect_form_error($token, 'Marque a autorização antes de enviar o briefing.', 'Consentimento');
}

$pdo = db();
try {
    $pdo->beginTransaction();
    $client = client_by_token($token, true);
    if (!$client) {
        $pdo->rollBack();
        app_error_page('Link inválido', 'Peça à S3 Mídia um novo link individual.', 404);
    }
    if (($client['status'] ?? '') === 'concluido') {
        $pdo->rollBack();
        app_error_page('Briefing já recebido', 'Estas respostas já foram enviadas com sucesso.', 409);
    }

    $json = json_encode($answers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $ipKey = (string) config('ip_hash_key', '');
    $ipHash = $ipKey !== '' ? hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $ipKey) : null;
    $userAgent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);

    $insert = $pdo->prepare('INSERT INTO respostas (cliente_id, respostas_json, ip_hash, user_agent) VALUES (?, ?, ?, ?)');
    $insert->execute([(int) $client['id'], $json, $ipHash, $userAgent]);

    $update = $pdo->prepare("UPDATE clientes SET status = 'concluido', concluido_em = CURRENT_TIMESTAMP WHERE id = ?");
    $update->execute([(int) $client['id']]);
    $pdo->commit();

    $_SESSION['briefing_name'] = (string) $client['nome'];
    $_SESSION['briefing_storage_key'] = 's3-briefing-' . hash('sha256', $token) . '-v2';
    unset($_SESSION['csrf_public']);
    header('Location: /obrigado.php', true, 303);
    exit;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Briefing submit error: ' . $exception->getMessage());
    app_error_page('Não foi possível enviar', 'Suas respostas continuam salvas neste dispositivo. Tente novamente em alguns minutos.', 500, $backUrl);
}
