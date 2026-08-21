<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

$name = trim((string) ($_SESSION['briefing_name'] ?? ''));
$storageKey = (string) ($_SESSION['briefing_storage_key'] ?? '');
unset($_SESSION['briefing_name'], $_SESSION['briefing_storage_key']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Briefing enviado | S3 Mídia</title>
<style>
*{box-sizing:border-box}body{margin:0;font-family:Inter,Arial,sans-serif;background:#f3f3f0;color:#111;display:grid;place-items:center;min-height:100vh;padding:20px}.card{max-width:640px;background:#fff;border-radius:28px;padding:44px;box-shadow:0 18px 60px rgba(0,0,0,.08);text-align:center}.mark{width:62px;height:62px;border-radius:18px;background:#050505;color:#fff;margin:0 auto 22px;display:grid;place-items:center;font-weight:900;font-size:22px}h1{font-size:38px;letter-spacing:-.04em;margin:0 0 10px}p{color:#666;line-height:1.6;margin:0 0 24px}a{display:inline-block;text-decoration:none;background:#111;color:#fff;padding:13px 18px;border-radius:13px;font-weight:700}small{display:block;margin-top:28px;color:#888}@media(max-width:560px){.card{padding:34px 24px}h1{font-size:31px}}
</style>
</head>
<body>
<main class="card">
  <div class="mark">S3</div>
  <h1>Briefing enviado.</h1>
  <p><?= $name !== '' ? 'Obrigado, ' . e($name) . '. ' : '' ?>A S3 Mídia recebeu suas informações. Elas servirão como base para o diagnóstico, o posicionamento e a construção do seu plano de marketing.</p>
  <a href="https://s3midiadigital.com.br/">Conhecer a S3 Mídia</a>
  <small>S3 Mídia — Marketing &amp; Publicidade</small>
</main>
<?php if ($storageKey !== ''): ?>
<script>try{localStorage.removeItem(<?= json_encode($storageKey, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>)}catch(e){}</script>
<?php endif; ?>
</body>
</html>
