<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_error_page(string $title, string $message, int $status = 503, string $backUrl = '/'): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . e($title) . ' | S3 Mídia</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f3f3f0;color:#111;font-family:Inter,Arial,sans-serif;padding:22px;box-sizing:border-box}.card{max-width:620px;background:#fff;border-radius:26px;padding:42px;box-shadow:0 18px 60px rgba(0,0,0,.08)}h1{font-size:34px;margin:0 0 12px;letter-spacing:-.03em}p{color:#666;line-height:1.6;margin:0 0 22px}a{color:#111;font-weight:800}</style>';
    echo '<body><main class="card"><h1>' . e($title) . '</h1><p>' . e($message) . '</p><a href="' . e($backUrl) . '">Voltar</a></main></body></html>';
    exit;
}

$configCandidates = [
    dirname(__DIR__) . '/.private/briefing-config.php',
    dirname(__DIR__, 2) . '/.private/briefing-config.php',
    dirname(__DIR__) . '/config.local.php',
];

$config = null;
foreach ($configCandidates as $candidate) {
    if (is_file($candidate)) {
        $loaded = require $candidate;
        if (is_array($loaded)) {
            $config = $loaded;
            break;
        }
    }
}

if (!is_array($config)) {
    app_error_page('Configuração em andamento', 'O formulário estará disponível em instantes.');
}

function config(string $key, mixed $default = null): mixed
{
    global $config;
    return $config[$key] ?? $default;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = config('db', []);
    try {
        $pdo = new PDO(
            'mysql:host=' . ($db['host'] ?? 'localhost') . ';dbname=' . ($db['name'] ?? '') . ';charset=utf8mb4',
            (string) ($db['user'] ?? ''),
            (string) ($db['pass'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        ensure_schema($pdo);
        return $pdo;
    } catch (Throwable $exception) {
        error_log('Briefing database error: ' . $exception->getMessage());
        app_error_page('Serviço temporariamente indisponível', 'Não foi possível acessar o formulário agora. Tente novamente em alguns minutos.');
    }
}

function ensure_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS clientes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        nome VARCHAR(180) NOT NULL,
        email VARCHAR(254) NULL,
        telefone VARCHAR(40) NULL,
        token CHAR(48) NOT NULL,
        status ENUM('pendente','concluido') NOT NULL DEFAULT 'pendente',
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        concluido_em TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uk_clientes_token (token),
        KEY idx_clientes_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS respostas (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        cliente_id BIGINT UNSIGNED NOT NULL,
        respostas_json LONGTEXT NOT NULL,
        enviado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip_hash CHAR(64) NULL,
        user_agent VARCHAR(500) NULL,
        PRIMARY KEY (id),
        KEY idx_respostas_cliente (cliente_id, enviado_em),
        CONSTRAINT fk_respostas_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ready = true;
}

function csrf_token(string $scope = 'public'): string
{
    $key = 'csrf_' . $scope;
    if (empty($_SESSION[$key])) {
        $_SESSION[$key] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION[$key];
}

function csrf_validate(?string $token, string $scope = 'public'): bool
{
    $expected = $_SESSION['csrf_' . $scope] ?? '';
    return is_string($token) && is_string($expected) && $expected !== '' && hash_equals($expected, $token);
}

function client_by_token(string $token, bool $lock = false): ?array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
        return null;
    }
    $sql = 'SELECT * FROM clientes WHERE token = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
    $stmt = db()->prepare($sql);
    $stmt->execute([$token]);
    $client = $stmt->fetch();
    return $client ?: null;
}

function base_url(): string
{
    return rtrim((string) config('base_url', 'https://briefing.s3midiadigital.com.br'), '/');
}

function admin_authenticated(): bool
{
    $lastSeen = (int) ($_SESSION['admin_last_seen'] ?? 0);
    if (empty($_SESSION['admin_authenticated']) || $lastSeen < time() - 7200) {
        unset($_SESSION['admin_authenticated'], $_SESSION['admin_last_seen']);
        return false;
    }
    $_SESSION['admin_last_seen'] = time();
    return true;
}

function verify_admin_password(string $password): bool
{
    $salt = (string) config('admin_password_salt', '');
    $expected = (string) config('admin_password_hash', '');
    $iterations = max(100000, (int) config('admin_password_iterations', 240000));
    if ($salt === '' || $expected === '') {
        return false;
    }
    $actual = hash_pbkdf2('sha256', $password, $salt, $iterations, 64);
    return hash_equals($expected, $actual);
}

function require_admin(): void
{
    if (!admin_authenticated()) {
        header('Location: /admin/');
        exit;
    }
}
