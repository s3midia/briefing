<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

header('Cache-Control: no-store, private');

$action = (string) ($_POST['action'] ?? '');
$notice = '';
$error = '';

if (!admin_authenticated()) {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $action === 'login') {
        $now = time();
        $attempts = array_values(array_filter((array) ($_SESSION['login_attempts'] ?? []), fn($time) => is_int($time) && $time > $now - 900));
        if (count($attempts) >= 5) {
            $error = 'Muitas tentativas. Aguarde 15 minutos para tentar novamente.';
        } elseif (!csrf_validate(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null, 'admin_login')) {
            $error = 'A sessão expirou. Atualize a página e tente novamente.';
        } elseif (verify_admin_password((string) ($_POST['password'] ?? ''))) {
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_last_seen'] = time();
            unset($_SESSION['login_attempts'], $_SESSION['csrf_admin_login']);
            header('Location: /admin/');
            exit;
        } else {
            $attempts[] = $now;
            $_SESSION['login_attempts'] = $attempts;
            $error = 'Senha incorreta.';
        }
    }
    ?>
    <!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Painel de briefings | S3 Mídia</title>
    <style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f1f1ee;font-family:Inter,Arial,sans-serif;color:#111;padding:20px}.card{width:min(440px,100%);background:#fff;border-radius:24px;padding:34px;box-shadow:0 18px 60px #00000012}.logo{font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:.16em}h1{font-size:32px;letter-spacing:-.04em;margin:28px 0 8px}p{color:#6b6b67;line-height:1.5;margin:0 0 24px}label{display:block;font-weight:800;font-size:14px;margin-bottom:8px}input{width:100%;padding:14px;border:1px solid #d8d8d2;border-radius:12px;font-size:16px}button{width:100%;border:0;border-radius:12px;padding:14px;background:#111;color:#fff;font-weight:800;font-size:15px;margin-top:14px;cursor:pointer}.error{background:#fff0f0;color:#a21d1d;border-radius:10px;padding:11px;margin-bottom:16px;font-size:14px}</style></head>
    <body><main class="card"><div class="logo">S3 Mídia</div><h1>Painel de briefings</h1><p>Acesso reservado para criação de clientes e consulta das respostas.</p>
    <?php if ($error !== ''): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
    <form method="post"><input type="hidden" name="action" value="login"><input type="hidden" name="csrf_token" value="<?= e(csrf_token('admin_login')) ?>"><label for="password">Senha do painel</label><input id="password" name="password" type="password" autocomplete="current-password" required autofocus><button type="submit">Entrar com segurança</button></form></main></body></html>
    <?php
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_validate(isset($_POST['csrf_token']) ? (string) $_POST['csrf_token'] : null, 'admin')) {
        $error = 'A sessão expirou. Atualize a página e tente novamente.';
    } elseif ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: /admin/');
        exit;
    } elseif ($action === 'create_client') {
        $name = mb_substr(trim((string) ($_POST['nome'] ?? '')), 0, 180);
        $email = mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 254);
        $phone = mb_substr(trim((string) ($_POST['telefone'] ?? '')), 0, 40);
        if ($name === '') {
            $error = 'Informe o nome do cliente.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'O e-mail informado não é válido.';
        } else {
            $token = bin2hex(random_bytes(24));
            $stmt = db()->prepare('INSERT INTO clientes (nome, email, telefone, token) VALUES (?, ?, ?, ?)');
            $stmt->execute([$name, $email !== '' ? $email : null, $phone !== '' ? $phone : null, $token]);
            $_SESSION['admin_notice'] = 'Cliente criado. O link individual já está pronto para copiar.';
            header('Location: /admin/');
            exit;
        }
    } elseif ($action === 'reopen') {
        $id = filter_var($_POST['client_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id) {
            $error = 'Cliente inválido.';
        } else {
            $stmt = db()->prepare("UPDATE clientes SET status = 'pendente', concluido_em = NULL WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['admin_notice'] = 'Briefing reaberto. O mesmo link pode ser usado novamente.';
            header('Location: /admin/?ver=' . $id);
            exit;
        }
    }
}

$notice = (string) ($_SESSION['admin_notice'] ?? '');
unset($_SESSION['admin_notice']);

$clients = db()->query("SELECT c.*, (SELECT MAX(r.id) FROM respostas r WHERE r.cliente_id = c.id) AS resposta_id FROM clientes c ORDER BY c.criado_em DESC, c.id DESC")->fetchAll();
$selectedClient = null;
$responses = [];
$viewId = filter_var($_GET['ver'] ?? null, FILTER_VALIDATE_INT);
if ($viewId) {
    $stmt = db()->prepare('SELECT * FROM clientes WHERE id = ? LIMIT 1');
    $stmt->execute([$viewId]);
    $selectedClient = $stmt->fetch() ?: null;
    if ($selectedClient) {
        $stmt = db()->prepare('SELECT * FROM respostas WHERE cliente_id = ? ORDER BY enviado_em DESC, id DESC');
        $stmt->execute([$viewId]);
        $responses = $stmt->fetchAll();
    }
}
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Painel de briefings | S3 Mídia</title>
<style>
:root{--ink:#121212;--muted:#6b6d68;--line:#deded8;--bg:#f2f2ef;--green:#137a4c;--amber:#a15b00}*{box-sizing:border-box}body{margin:0;background:var(--bg);font-family:Inter,Arial,sans-serif;color:var(--ink)}header{background:#111;color:#fff;padding:18px max(20px,calc((100vw - 1180px)/2));display:flex;align-items:center;justify-content:space-between;gap:16px}.brand{font-weight:900;letter-spacing:.08em}.logout{border:1px solid #ffffff4d;background:transparent;color:#fff;padding:9px 12px;border-radius:10px;cursor:pointer}.wrap{width:min(1180px,calc(100% - 32px));margin:30px auto 60px}.head{display:flex;align-items:end;justify-content:space-between;gap:20px;margin-bottom:22px}.head h1{font-size:38px;letter-spacing:-.05em;margin:0}.head p{color:var(--muted);margin:8px 0 0}.grid{display:grid;grid-template-columns:360px 1fr;gap:22px;align-items:start}.card{background:#fff;border:1px solid var(--line);border-radius:20px;padding:24px;box-shadow:0 8px 30px #00000008}.card h2{font-size:20px;margin:0 0 18px}.field{margin-bottom:14px}.field label{display:block;font-size:13px;font-weight:800;margin-bottom:7px}.field input{width:100%;border:1px solid var(--line);border-radius:11px;padding:12px;font-size:15px}.primary,.copy{border:0;background:#111;color:#fff;border-radius:11px;padding:12px 15px;font-weight:800;cursor:pointer}.primary{width:100%}.copy{padding:8px 11px;font-size:12px}.notice,.error{padding:13px 15px;border-radius:12px;margin-bottom:18px}.notice{background:#eaf8f0;color:#11633e}.error{background:#fff0f0;color:#a21d1d}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse;min-width:690px}th,td{text-align:left;padding:14px 10px;border-bottom:1px solid #ecece7;vertical-align:middle}th{font-size:12px;text-transform:uppercase;color:#777;letter-spacing:.04em}td{font-size:14px}.client{font-weight:800}.sub{color:var(--muted);font-size:12px;margin-top:3px}.status{display:inline-block;border-radius:999px;padding:6px 9px;font-size:11px;font-weight:900;text-transform:uppercase}.pending{background:#fff4df;color:var(--amber)}.done{background:#e7f6ed;color:var(--green)}.actions{display:flex;gap:8px;align-items:center}.link{color:#111;font-weight:800;text-decoration:none;font-size:13px}.detail{margin-top:22px}.detail-top{display:flex;align-items:flex-start;justify-content:space-between;gap:15px}.detail h2{margin:0}.answer{padding:16px 0;border-bottom:1px solid #ecece7}.answer dt{font-weight:900;font-size:13px;margin-bottom:6px}.answer dd{margin:0;color:#4d4f4b;white-space:pre-wrap;line-height:1.5}.response-title{margin:25px 0 3px;font-size:15px}.empty{color:var(--muted);padding:18px 0}.reopen{border:1px solid var(--line);background:#fff;border-radius:10px;padding:9px 11px;font-weight:800;cursor:pointer}.export{display:inline-block;background:#fff;border:1px solid var(--line);color:#111;text-decoration:none;padding:10px 13px;border-radius:11px;font-weight:800;font-size:13px}@media(max-width:880px){.grid{grid-template-columns:1fr}.head{align-items:flex-start;flex-direction:column}.head h1{font-size:31px}.wrap{width:min(100% - 22px,1180px)}.card{padding:18px}}
</style></head>
<body><header><div class="brand">S3 MÍDIA · BRIEFINGS</div><form method="post"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf_token" value="<?= e(csrf_token('admin')) ?>"><button class="logout" type="submit">Sair</button></form></header>
<main class="wrap"><div class="head"><div><h1>Clientes e respostas</h1><p>Crie um link individual para cada pessoa e acompanhe os envios.</p></div><a class="export" href="/admin/exportar.php">Exportar planilha CSV</a></div>
<?php if ($notice !== ''): ?><div class="notice"><?= e($notice) ?></div><?php endif; ?><?php if ($error !== ''): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<div class="grid"><section class="card"><h2>Novo cliente</h2><form method="post"><input type="hidden" name="action" value="create_client"><input type="hidden" name="csrf_token" value="<?= e(csrf_token('admin')) ?>"><div class="field"><label for="nome">Nome completo *</label><input id="nome" name="nome" required></div><div class="field"><label for="email">E-mail</label><input id="email" name="email" type="email"></div><div class="field"><label for="telefone">WhatsApp / telefone</label><input id="telefone" name="telefone" type="tel"></div><button class="primary" type="submit">Criar link individual</button></form></section>
<section class="card"><h2>Todos os clientes</h2><div class="table-wrap"><table><thead><tr><th>Cliente</th><th>Status</th><th>Criado</th><th>Ações</th></tr></thead><tbody>
<?php if ($clients === []): ?><tr><td colspan="4" class="empty">Nenhum cliente criado ainda.</td></tr><?php endif; ?>
<?php foreach ($clients as $client): $link = base_url() . '/?c=' . $client['token']; ?><tr><td><div class="client"><?= e((string) $client['nome']) ?></div><div class="sub"><?= e((string) ($client['email'] ?? '')) ?></div></td><td><span class="status <?= $client['status'] === 'concluido' ? 'done' : 'pending' ?>"><?= $client['status'] === 'concluido' ? 'Concluído' : 'Pendente' ?></span></td><td><?= e(date('d/m/Y H:i', strtotime((string) $client['criado_em']))) ?></td><td><div class="actions"><button class="copy" type="button" data-copy="<?= e($link) ?>">Copiar link</button><a class="link" href="?ver=<?= (int) $client['id'] ?>">Ver</a></div></td></tr><?php endforeach; ?>
</tbody></table></div></section></div>
<?php if ($selectedClient): ?><section class="card detail"><div class="detail-top"><div><h2><?= e((string) $selectedClient['nome']) ?></h2><div class="sub"><?= e((string) ($selectedClient['email'] ?? '')) ?><?= !empty($selectedClient['telefone']) ? ' · ' . e((string) $selectedClient['telefone']) : '' ?></div></div><?php if ($selectedClient['status'] === 'concluido'): ?><form method="post"><input type="hidden" name="action" value="reopen"><input type="hidden" name="client_id" value="<?= (int) $selectedClient['id'] ?>"><input type="hidden" name="csrf_token" value="<?= e(csrf_token('admin')) ?>"><button class="reopen" type="submit">Reabrir formulário</button></form><?php endif; ?></div>
<?php if ($responses === []): ?><p class="empty">Este cliente ainda não enviou respostas.</p><?php endif; ?>
<?php foreach ($responses as $index => $response): $data = json_decode((string) $response['respostas_json'], true) ?: []; ?><h3 class="response-title">Envio de <?= e(date('d/m/Y \à\s H:i', strtotime((string) $response['enviado_em']))) ?><?= count($responses) > 1 ? ' · versão ' . (count($responses) - $index) : '' ?></h3><dl><?php foreach ($data as $question => $answer): ?><div class="answer"><dt><?= e((string) $question) ?></dt><dd><?= e(is_array($answer) ? implode(', ', array_map('strval', $answer)) : (string) $answer) ?></dd></div><?php endforeach; ?></dl><?php endforeach; ?>
</section><?php endif; ?></main>
<script>document.querySelectorAll('[data-copy]').forEach(button=>button.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(button.dataset.copy);const old=button.textContent;button.textContent='Copiado!';setTimeout(()=>button.textContent=old,1600)}catch(e){prompt('Copie o link:',button.dataset.copy)}}));</script></body></html>
