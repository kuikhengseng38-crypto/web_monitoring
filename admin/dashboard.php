<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/monitor.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_check'])) {
    csrf_check();
    $results = run_monitor(true);
    $count = count($results);
    flash_set('success', 'Checked ' . $count . ' website(s). Alerts are sent only when status changes.');
    redirect('dashboard.php');
}

$q = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';

$sql = 'SELECT * FROM websites WHERE 1=1';
$types = '';
$params = [];

if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR url LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'ss';
    $params[] = $like;
    $params[] = $like;
}

if ($filter === 'up') {
    $sql .= " AND status = 'UP'";
} elseif ($filter === 'down') {
    $sql .= " AND status = 'DOWN'";
} elseif ($filter === 'today') {
    $sql .= ' AND DATE(last_checked) = CURDATE()';
} elseif ($filter === 'week') {
    $sql .= ' AND last_checked >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
}

$sql .= ' ORDER BY last_checked IS NULL DESC, last_checked DESC';

$stmt = db()->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$websites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stats = db()->query("SELECT
    COUNT(*) AS total,
    SUM(status = 'UP') AS up_count,
    SUM(status = 'DOWN') AS down_count
    FROM websites")->fetch_assoc();

$recentAlerts = db()->query(
    "SELECT l.*, w.name, w.url
     FROM logs l
     JOIN websites w ON w.id = l.website_id
     WHERE l.alert_type IS NOT NULL AND l.alert_type != ''
     ORDER BY l.checked_at DESC
     LIMIT 6"
)->fetch_all(MYSQLI_ASSOC);

$recentActivity = db()->query(
    "SELECT l.*, w.name, w.url
     FROM logs l
     JOIN websites w ON w.id = l.website_id
     ORDER BY l.checked_at DESC
     LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
$autoCheck = true;
$monitorRunning = monitor_is_running();
include dirname(__DIR__) . '/includes/header.php';
?>
<h1>Monitoring dashboard</h1>
<p class="subtitle">Live website status, latest activity, and Telegram alerts.</p>

<?php if ($monitorRunning): ?>
    <div class="flash flash-success">Background auto-check is running. Each website is checked using its own interval.</div>
<?php elseif (PHP_OS_FAMILY === 'Windows'): ?>
    <div class="flash flash-info">Starting background auto-check. Keep the website server running; checks continue even if you leave this page.</div>
<?php else: ?>
    <div class="flash flash-info">On cPanel, add a Cron Job in <a href="settings.php">Settings</a> so checks keep running 24/7. You can also click <strong>Check all websites now</strong>.</div>
<?php endif; ?>

<div class="cards">
    <div class="card">
        <div class="stat-label">Total websites</div>
        <div class="stat-value"><?php echo (int) $stats['total']; ?></div>
    </div>
    <div class="card">
        <div class="stat-label">UP</div>
        <div class="stat-value stat-up"><?php echo (int) $stats['up_count']; ?></div>
    </div>
    <div class="card">
        <div class="stat-label">DOWN</div>
        <div class="stat-value stat-down"><?php echo (int) $stats['down_count']; ?></div>
    </div>
    <div class="card">
        <div class="stat-label">Showing</div>
        <div class="stat-value"><?php echo count($websites); ?></div>
    </div>
</div>

<form method="post" class="toolbar">
    <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
    <button class="btn btn-success" type="submit" name="run_check" value="1">Check all websites now</button>
    <a class="btn btn-secondary" href="website_form.php">Add website</a>
</form>

<form method="get" class="toolbar">
    <div class="grow">
        <label for="q">Search name or URL</label>
        <input id="q" name="q" type="text" value="<?php echo h($q); ?>" placeholder="example.com">
    </div>
    <div>
        <label for="filter">Filter</label>
        <select id="filter" name="filter">
            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All websites</option>
            <option value="up" <?php echo $filter === 'up' ? 'selected' : ''; ?>>UP only</option>
            <option value="down" <?php echo $filter === 'down' ? 'selected' : ''; ?>>DOWN only</option>
            <option value="today" <?php echo $filter === 'today' ? 'selected' : ''; ?>>Checked today</option>
            <option value="week" <?php echo $filter === 'week' ? 'selected' : ''; ?>>Last 7 days</option>
        </select>
    </div>
    <button class="btn" type="submit">Apply</button>
</form>

<div class="card" style="padding:0; overflow:auto;">
    <table>
        <thead>
        <tr>
            <th>Website</th>
            <th>URL</th>
            <th>Status</th>
            <th>HTTP</th>
            <th>Response</th>
            <th>Last checked</th>
            <th>Interval</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$websites): ?>
            <tr><td colspan="8" class="empty">No websites match this filter. Add one to start monitoring.</td></tr>
        <?php endif; ?>
        <?php foreach ($websites as $site): ?>
            <tr>
                <td><?php echo h($site['name']); ?></td>
                <td class="url"><a href="<?php echo h($site['url']); ?>" target="_blank" rel="noopener"><?php echo h($site['url']); ?></a></td>
                <td><?php echo status_badge($site['is_slow'] && $site['status'] === 'UP' ? 'SLOW' : $site['status']); ?></td>
                <td><?php echo $site['http_code'] ? (int) $site['http_code'] : '—'; ?></td>
                <td><?php echo $site['response_time'] ? (int) $site['response_time'] . ' ms' : 'N/A'; ?></td>
                <td><?php echo h(format_datetime($site['last_checked'])); ?></td>
                <td><?php echo (int) $site['interval_minutes']; ?> min</td>
                <td class="actions">
                    <a class="btn btn-small btn-secondary" href="website_form.php?id=<?php echo (int) $site['id']; ?>">Edit</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="cards" style="margin-top:22px; grid-template-columns: 1fr 1fr;">
    <div class="card">
        <h2>Recent alerts</h2>
        <?php if (!$recentAlerts): ?>
            <p class="help">No status-change alerts yet.</p>
        <?php else: ?>
            <table>
                <tbody>
                <?php foreach ($recentAlerts as $alert): ?>
                    <tr>
                        <td>
                            <strong><?php echo h($alert['alert_type']); ?></strong>
                            <div><?php echo h($alert['name']); ?></div>
                        </td>
                        <td><?php echo status_badge($alert['status']); ?></td>
                        <td><?php echo h($alert['checked_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <div class="card">
        <h2>Latest monitoring activity</h2>
        <?php if (!$recentActivity): ?>
            <p class="help">No checks yet. Run a check or wait for the background auto-check.</p>
        <?php else: ?>
            <table>
                <tbody>
                <?php foreach ($recentActivity as $row): ?>
                    <tr>
                        <td><?php echo h($row['name']); ?></td>
                        <td><?php echo status_badge($row['status']); ?></td>
                        <td><?php echo $row['response_time'] ? (int) $row['response_time'] . ' ms' : 'N/A'; ?></td>
                        <td><?php echo h($row['checked_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
