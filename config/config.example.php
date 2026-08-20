<?php
/**
 * Copy this file to config.local.php and replace the placeholders.
 * config.local.php is gitignored and must not be committed.
 */

define('APP_NAME', 'Website Monitor');
define('APP_TIMEZONE', 'Asia/Taipei');

// Forgot-password recovery key. Change this after install.
define('ADMIN_RESET_KEY', 'YOUR_RECOVERY_KEY');

// HTTP trigger key for cPanel cron (wget/curl) or browser:
// https://yourdomain.com/cron/monitor.php?key=YOUR_CRON_SECRET
define('CRON_KEY', 'YOUR_CRON_SECRET');

// Optional fallback. Prefer saving these in Admin → Settings instead.
define('TELEGRAM_BOT_TOKEN', '');
define('TELEGRAM_CHAT_ID', '');

define('CHECK_TIMEOUT', 10);
define('DEFAULT_SLOW_MS', 3000);
