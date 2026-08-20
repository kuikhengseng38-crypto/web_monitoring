<?php

function public_status_payload(): array
{
    $historyDays = 90;
    $historyOffset = $historyDays - 1;

    $sites = db()->query(
        'SELECT id, name, url, status, last_checked, response_time, http_code, is_slow, interval_minutes
         FROM websites
         ORDER BY name ASC'
    )->fetch_all(MYSQLI_ASSOC);

    $ids = array_map(static fn($row) => (int) $row['id'], $sites);
    $uptime = [];
    $history = [];

    if ($ids) {
        $in = implode(',', $ids);
        $uRows = db()->query(
            "SELECT website_id,
                    SUM(CASE WHEN status = 'DOWN' THEN 0 ELSE 1 END) AS up_count,
                    COUNT(*) AS total
             FROM logs
             WHERE checked_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
               AND website_id IN ($in)
             GROUP BY website_id"
        );
        if ($uRows) {
            while ($row = $uRows->fetch_assoc()) {
                $total = (int) $row['total'];
                $uptime[(int) $row['website_id']] = $total > 0
                    ? round(((int) $row['up_count'] / $total) * 100, 2)
                    : null;
            }
        }

        $hRows = db()->query(
            "SELECT website_id, DATE(checked_at) AS day_key,
                    SUM(status = 'DOWN') AS down_count
             FROM logs
             WHERE checked_at >= DATE_SUB(CURDATE(), INTERVAL {$historyOffset} DAY)
               AND website_id IN ($in)
             GROUP BY website_id, DATE(checked_at)"
        );
        if ($hRows) {
            while ($row = $hRows->fetch_assoc()) {
                $history[(int) $row['website_id']][$row['day_key']] = ((int) $row['down_count'] > 0) ? 'down' : 'up';
            }
        }
    }

    $days = [];
    for ($i = $historyOffset; $i >= 0; $i--) {
        $days[] = date('Y-m-d', strtotime('-' . $i . ' days'));
    }

    $services = [];
    $up = 0;
    $down = 0;
    $unknown = 0;
    foreach ($sites as $site) {
        $status = strtoupper((string) $site['status']);
        if ($status === 'UP' && (int) $site['is_slow'] === 1) {
            $status = 'SLOW';
        }
        if ($status === 'UP' || $status === 'SLOW') {
            $up++;
        } elseif ($status === 'DOWN') {
            $down++;
        } else {
            $unknown++;
        }

        $bars = [];
        foreach ($days as $day) {
            $bars[] = [
                'date' => $day,
                'state' => $history[(int) $site['id']][$day] ?? 'none',
            ];
        }

        $services[] = [
            'name' => $site['name'],
            'url' => $site['url'],
            'status' => $status ?: 'UNKNOWN',
            'last_checked' => $site['last_checked'],
            'response_time' => $site['response_time'] ? (int) $site['response_time'] : null,
            'http_code' => $site['http_code'] ? (int) $site['http_code'] : null,
            'uptime_24h' => $uptime[(int) $site['id']] ?? null,
            'history' => $bars,
        ];
    }

    $overall = 'unknown';
    $overallLabel = 'No services yet';
    if ($sites) {
        if ($down === 0 && $unknown === 0) {
            $overall = 'operational';
            $overallLabel = 'All systems operational';
        } elseif ($down > 0 && $down < count($sites)) {
            $overall = 'partial';
            $overallLabel = 'Partial outage';
        } elseif ($down > 0) {
            $overall = 'major';
            $overallLabel = 'Major outage';
        } else {
            $overall = 'unknown';
            $overallLabel = 'Status unknown';
        }
    }

    return [
        'updated' => date('Y-m-d H:i:s'),
        'overall' => $overall,
        'overall_label' => $overallLabel,
        'counts' => [
            'total' => count($sites),
            'up' => $up,
            'down' => $down,
        ],
        'services' => $services,
        'history_days' => $historyDays,
        'refresh_seconds' => 30,
    ];
}
