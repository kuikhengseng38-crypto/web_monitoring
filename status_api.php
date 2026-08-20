<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/monitor.php';
require_once __DIR__ . '/includes/status_data.php';

if (PHP_OS_FAMILY !== 'Windows') {
    try {
        run_monitor(false);
    } catch (Throwable $e) {
        // Keep the status page working even if a check fails.
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode(public_status_payload());
