<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/monitor.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$running = function_exists('monitor_daemon_is_alive')
    ? (monitor_daemon_is_alive() || monitor_is_running())
    : monitor_is_running();

$results = [];
if (!$running) {
    $results = run_monitor(false);
    monitor_heartbeat_touch();
    $running = true;
}

$sites = db()->query(
    'SELECT id, status, is_slow, last_checked, response_time FROM websites ORDER BY id'
);
$rows = $sites ? $sites->fetch_all(MYSQLI_ASSOC) : [];
$fingerprint = md5(json_encode($rows));

echo json_encode([
    'ok' => true,
    'checked' => count($results),
    'running' => $running,
    'time' => date('Y-m-d H:i:s'),
    'fingerprint' => $fingerprint,
    'results' => $results,
]);
