<?php
/**
 * Background auto-checker.
 * Starts hidden from the website — no extra window and no Task Scheduler.
 */

define('MONITOR_DAEMON_PROCESS', true);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/daemon.php';
require_once dirname(__DIR__) . '/includes/monitor.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo 'This script runs in the background automatically.';
    exit;
}

$lock = @fopen(monitor_lock_path(), 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}

@file_put_contents(monitor_pid_path(), (string) getmypid());

ignore_user_abort(true);
set_time_limit(0);

function watch_log(string $line): void
{
    $msg = date('Y-m-d H:i:s') . ' ' . $line . PHP_EOL;
    $path = monitor_log_path();
    if (is_file($path) && filesize($path) > 1048576) {
        @file_put_contents($path, $msg);
        return;
    }
    @file_put_contents($path, $msg, FILE_APPEND);
}

register_shutdown_function(static function () use ($lock): void {
    @unlink(monitor_pid_path());
    if (is_resource($lock)) {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
});

watch_log('Auto monitor started (pid ' . getmypid() . ')');

$started = telegram_send_result(
    "MONITOR: Auto check started\nTime: " . date('Y-m-d H:i:s') . "\nInterval: per website setting"
);
if ($started['ok']) {
    watch_log('Telegram: start message sent');
} else {
    watch_log('Telegram: ' . ($started['error'] ?: 'not configured'));
}

while (true) {
    monitor_heartbeat_touch();
    @file_put_contents(monitor_pid_path(), (string) getmypid());
    try {
        $results = run_monitor(false);
        if ($results) {
            watch_log('checked ' . count($results) . ' site(s)');
            foreach ($results as $row) {
                $alert = $row['alert_type'] ? ' alert=' . $row['alert_type'] : '';
                $sent = !empty($row['alert_sent']) ? ' telegram=ok' : '';
                $change = !empty($row['status_changed'])
                    ? ' ' . ($row['previous_status'] ?? '?') . '→' . $row['status']
                    : '';
                watch_log('  - ' . $row['name'] . ' ' . $row['status'] . $change . $alert . $sent);
            }
        }
    } catch (Throwable $e) {
        watch_log('error: ' . $e->getMessage());
    }

    sleep(15);
}
