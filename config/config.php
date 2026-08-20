<?php
/**
 * Application configuration
 * Copy values here after setup. Telegram values can also be set in Admin > Settings.
 */

define('APP_NAME', 'Website Monitor');
define('APP_TIMEZONE', 'Asia/Taipei');

date_default_timezone_set(APP_TIMEZONE);

// Used on the Forgot Password page. Change this after first login.
define('ADMIN_RESET_KEY', 'change-this-reset-key');

// HTTP trigger key for cPanel cron (wget/curl) or browser
define('CRON_KEY', 'sx-wm-7f3a9c2e4b18');

// Fallback Telegram credentials (Admin Settings in the database override these if filled)
define('TELEGRAM_BOT_TOKEN', '');
define('TELEGRAM_CHAT_ID', '');

// HTTP check timeout in seconds
define('CHECK_TIMEOUT', 10);

// Default slow-response threshold in milliseconds
define('DEFAULT_SLOW_MS', 3000);

// Local Windows only: start a hidden watcher. On cPanel use Cron Jobs instead.
if (PHP_SAPI !== 'cli' && !defined('MONITOR_DAEMON_PROCESS') && PHP_OS_FAMILY === 'Windows') {
    require_once dirname(__DIR__) . '/includes/daemon.php';
    monitor_daemon_ensure();
}
