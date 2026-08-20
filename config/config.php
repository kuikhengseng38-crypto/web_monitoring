<?php
/**
 * Loads local secrets from config.local.php (gitignored).
 * Copy config.example.php to config.local.php before first run.
 */

if (!is_file(__DIR__ . '/config.local.php')) {
    http_response_code(500);
    die('Missing config/config.local.php. Copy config.example.php to config.local.php and fill in your values.');
}

require_once __DIR__ . '/config.local.php';

if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'Asia/Taipei');
}
date_default_timezone_set(APP_TIMEZONE);

if (!defined('ADMIN_RESET_KEY') || !defined('CRON_KEY')) {
    http_response_code(500);
    die('config.local.php is incomplete. Compare it with config.example.php.');
}

if (PHP_SAPI !== 'cli' && !defined('MONITOR_DAEMON_PROCESS') && PHP_OS_FAMILY === 'Windows') {
    require_once dirname(__DIR__) . '/includes/daemon.php';
    monitor_daemon_ensure();
}
