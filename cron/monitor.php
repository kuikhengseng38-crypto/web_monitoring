<?php
/**
 * Automatic monitoring engine.
 * CLI:  php cron/monitor.php
 * HTTP: https://yourdomain.com/cron/monitor.php?key=YOUR_CRON_SECRET
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/monitor.php';

$isCli = (PHP_SAPI === 'cli');
if (!$isCli) {
    $key = $_GET['key'] ?? '';
    if (!hash_equals(CRON_KEY, $key)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}

$force = $isCli && in_array('--all', $argv ?? [], true);
$results = run_monitor($force);
$checked = count($results);
$alerts = 0;
foreach ($results as $row) {
    if (!empty($row['alert_sent'])) {
        $alerts++;
    }
}

$line = date('Y-m-d H:i:s') . " checked={$checked} alerts={$alerts}\n";
if ($isCli) {
    echo $line;
    foreach ($results as $row) {
        echo '- ' . $row['name'] . ' ' . $row['status'] . "\n";
    }
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo $line;
}
