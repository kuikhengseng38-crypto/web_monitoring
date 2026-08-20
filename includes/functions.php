<?php

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function app_base_path(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    $folder = basename($dir);

    if ($folder === 'admin' || $folder === 'cron') {
        $dir = rtrim(str_replace('\\', '/', dirname($dir)), '/');
    }

    if ($dir === '' || $dir === '/' || $dir === '.') {
        return '/';
    }

    return $dir . '/';
}

function public_status_url(): string
{
    return app_base_path();
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid request token. Please go back and try again.');
    }
}

function setting(string $key, $default = '')
{
    $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$result || $result['setting_value'] === null || $result['setting_value'] === '') {
        return $default;
    }

    return $result['setting_value'];
}

function setting_set(string $key, string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
    $stmt->close();
}

function format_datetime(?string $datetime): string
{
    if (!$datetime) {
        return 'Never';
    }
    return date('Y-m-d H:i:s', strtotime($datetime));
}

function status_badge(string $status): string
{
    $status = strtoupper($status);
    if ($status === 'UP') {
        return '<span class="badge badge-up">UP</span>';
    }
    if ($status === 'DOWN') {
        return '<span class="badge badge-down">DOWN</span>';
    }
    if ($status === 'SLOW') {
        return '<span class="badge badge-slow">SLOW</span>';
    }
    return '<span class="badge badge-unknown">UNKNOWN</span>';
}

function monitor_heartbeat_path(): string
{
    $dir = dirname(__DIR__) . '/storage';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $dir . '/heartbeat.txt';
}

function monitor_heartbeat_touch(): void
{
    @file_put_contents(monitor_heartbeat_path(), (string) time());
}

function monitor_is_running(int $maxAgeSeconds = 90): bool
{
    if (function_exists('monitor_daemon_is_alive') && monitor_daemon_is_alive()) {
        return true;
    }

    $path = monitor_heartbeat_path();
    if (!is_file($path)) {
        return false;
    }
    $stamp = (int) file_get_contents($path);
    return $stamp > 0 && (time() - $stamp) <= $maxAgeSeconds;
}

function valid_url(string $url): bool
{
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $scheme = parse_url($url, PHP_URL_SCHEME);
    return in_array($scheme, ['http', 'https'], true);
}
