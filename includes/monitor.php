<?php

require_once __DIR__ . '/telegram.php';

/**
 * Check a single URL. Returns status, HTTP code, and response time in milliseconds.
 */
function check_url(string $url, int $timeoutSeconds = CHECK_TIMEOUT): array
{
    $start = microtime(true);
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => $timeoutSeconds,
        CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_NOBODY => false,
        CURLOPT_USERAGENT => 'WebsiteMonitor/1.0',
        CURLOPT_HEADER => false,
    ]);

    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $ms = (int) round((microtime(true) - $start) * 1000);

    $connected = ($errno === 0 && $httpCode > 0);
    $treat4xxAsDown = ((string) setting('treat_4xx_as_down', '1')) !== '0';
    $status = 'DOWN';
    if ($connected && $httpCode >= 200 && $httpCode < 400) {
        $status = 'UP';
    } elseif ($connected && $httpCode >= 400 && $httpCode < 500 && !$treat4xxAsDown) {
        $status = 'UP';
    }

    return [
        'status' => $status,
        'http_code' => $httpCode,
        'response_time' => $ms,
        'error' => $errno,
    ];
}

function due_websites(): array
{
    $now = date('Y-m-d H:i:s');
    $stmt = db()->prepare(
        'SELECT * FROM websites
         WHERE last_checked IS NULL
            OR TIMESTAMPDIFF(SECOND, last_checked, ?) >= (interval_minutes * 60)
         ORDER BY id ASC'
    );
    $stmt->bind_param('s', $now);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function all_websites(): array
{
    $result = db()->query('SELECT * FROM websites ORDER BY id ASC');
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

function monitor_website(array $website): array
{
    $timeout = (int) setting('check_timeout', CHECK_TIMEOUT);
    if ($timeout < 3) {
        $timeout = 3;
    }

    $check = check_url($website['url'], $timeout);
    $newStatus = $check['status'];
    $responseTime = $check['response_time'] !== null ? (int) $check['response_time'] : 0;
    $checkedAt = date('Y-m-d H:i:s');
    $previousStatus = strtoupper((string) ($website['status'] ?: 'UNKNOWN'));

    $slowThreshold = (int) ($website['slow_threshold_ms'] ?: setting('slow_threshold_ms', DEFAULT_SLOW_MS));
    $isSlow = ($newStatus === 'UP' && $responseTime > 0 && $responseTime > $slowThreshold);
    $wasSlow = (int) ($website['is_slow'] ?? 0) === 1;

    $previousDisplay = ($wasSlow && $previousStatus === 'UP') ? 'SLOW' : $previousStatus;
    $newDisplay = $isSlow ? 'SLOW' : $newStatus;
    $statusChanged = ($previousDisplay !== $newDisplay);

    $alertType = '';
    if ($statusChanged) {
        if ($newDisplay === 'DOWN') {
            $alertType = 'DOWN';
        } elseif ($newDisplay === 'SLOW') {
            $alertType = 'SLOW';
        } elseif ($previousDisplay === 'DOWN' || $previousDisplay === 'SLOW') {
            $alertType = 'RECOVERY';
        } else {
            $alertType = 'STATUS';
        }
    }

    $stmt = db()->prepare(
        'UPDATE websites
         SET status = ?, last_checked = ?, response_time = ?, http_code = ?, is_slow = ?
         WHERE id = ?'
    );
    $isSlowInt = $isSlow ? 1 : 0;
    $httpCode = $check['http_code'];
    $website['http_code'] = $httpCode;
    $websiteId = (int) $website['id'];
    $stmt->bind_param('ssiiii', $newStatus, $checkedAt, $responseTime, $httpCode, $isSlowInt, $websiteId);
    $stmt->execute();
    $stmt->close();

    $displayStatus = $newDisplay;
    $changeFlag = $statusChanged ? 1 : 0;
    $logStmt = db()->prepare(
        'INSERT INTO logs (website_id, status, response_time, http_code, checked_at, is_status_change, alert_type)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $logStmt->bind_param(
        'isiisis',
        $websiteId,
        $displayStatus,
        $responseTime,
        $httpCode,
        $checkedAt,
        $changeFlag,
        $alertType
    );
    $logStmt->execute();
    $logStmt->close();

    $alertSent = false;
    if ($statusChanged) {
        $message = telegram_alert_message($alertType, $website, $newDisplay, $responseTime, $checkedAt, $previousDisplay);
        $sent = telegram_send_result($message);
        if (empty($sent['ok'])) {
            $sent = telegram_send_result($message);
        }
        $alertSent = !empty($sent['ok']);
    }

    return [
        'id' => $websiteId,
        'name' => $website['name'],
        'status' => $newDisplay,
        'response_time' => $responseTime,
        'status_changed' => $statusChanged,
        'previous_status' => $previousDisplay,
        'alert_type' => $alertType,
        'alert_sent' => $alertSent,
    ];
}

function run_monitor(bool $forceAll = false): array
{
    $sites = $forceAll ? all_websites() : due_websites();
    $results = [];
    foreach ($sites as $site) {
        $results[] = monitor_website($site);
    }
    return $results;
}
