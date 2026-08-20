<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/status_data.php';

$data = public_status_payload();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Status page — <?php echo h(APP_NAME); ?></title>
    <link rel="stylesheet" href="assets/css/status.css">
</head>
<body data-refresh="<?php echo (int) $data['refresh_seconds']; ?>">
<header class="hero hero-<?php echo h($data['overall']); ?>" id="hero">
    <div class="wrap">
        <div class="hero-top">
            <strong><?php echo h(APP_NAME); ?></strong>
            <a class="admin-link" href="admin/login.php">Admin login</a>
        </div>
        <h1 id="overall-label"><?php echo h($data['overall_label']); ?></h1>
        <p class="meta">
            Last updated <span id="updated"><?php echo h($data['updated']); ?></span>
            · Next update in <span id="countdown"><?php echo (int) $data['refresh_seconds']; ?></span> sec
        </p>
    </div>
</header>

<main class="wrap">
    <section class="panel">
        <div class="panel-head">
            <h2>Services</h2>
            <span id="counts"><?php echo (int) $data['counts']['up']; ?> UP · <?php echo (int) $data['counts']['down']; ?> DOWN</span>
        </div>
        <div id="services">
            <?php if (!$data['services']): ?>
                <p class="empty">No websites are being monitored yet.</p>
            <?php endif; ?>
            <?php foreach ($data['services'] as $service): ?>
                <article class="service">
                    <div class="service-row">
                        <div>
                            <h3><?php echo h($service['name']); ?></h3>
                            <a class="service-url" href="<?php echo h($service['url']); ?>" target="_blank" rel="noopener"><?php echo h($service['url']); ?></a>
                        </div>
                        <span class="pill pill-<?php echo h(strtolower($service['status'])); ?>"><?php echo h($service['status']); ?></span>
                    </div>
                    <div class="bars" title="Last <?php echo (int) $data['history_days']; ?> days">
                        <?php foreach ($service['history'] as $bar): ?>
                            <i class="bar bar-<?php echo h($bar['state']); ?>" title="<?php echo h($bar['date'] . ' ' . $bar['state']); ?>"></i>
                        <?php endforeach; ?>
                    </div>
                    <p class="service-meta">
                        24h uptime: <?php echo $service['uptime_24h'] === null ? '—' : h((string) $service['uptime_24h']) . '%'; ?>
                        · Last check: <?php echo $service['last_checked'] ? h($service['last_checked']) : 'Never'; ?>
                        <?php if ($service['http_code']): ?> · HTTP <?php echo (int) $service['http_code']; ?><?php endif; ?>
                    </p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
    <p class="foot">Public status page · no login required</p>
</main>
<script src="assets/js/status.js"></script>
</body>
</html>
