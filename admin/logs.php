<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_login();

$q = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$changesOnly = isset($_GET['changes']) && $_GET['changes'] === '1';

$sql = 'SELECT l.*, w.name, w.url
        FROM logs l
        JOIN websites w ON w.id = l.website_id
        WHERE 1=1';
$types = '';
$params = [];

if ($q !== '') {
    $sql .= ' AND (w.name LIKE ? OR w.url LIKE ?)';
    $like = '%' . $q . '%';
    $types .= 'ss';
    $params[] = $like;
    $params[] = $like;
}

if ($filter === 'up') {
    $sql .= " AND l.status IN ('UP', 'SLOW')";
} elseif ($filter === 'down') {
    $sql .= " AND l.status = 'DOWN'";
} elseif ($filter === 'today') {
    $sql .= ' AND DATE(l.checked_at) = CURDATE()';
} elseif ($filter === 'week') {
    $sql .= ' AND l.checked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
}

if ($changesOnly) {
    $sql .= ' AND l.is_status_change = 1';
}

$sql .= ' ORDER BY l.checked_at DESC LIMIT 300';

$stmt = db()->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Monitoring logs';
$currentPage = 'logs';
include dirname(__DIR__) . '/includes/header.php';
?>
<h1>Monitoring logs</h1>
<p class="subtitle">Full check history. Use “status changes only” to review UP/DOWN transitions.</p>

<form method="get" class="toolbar">
    <div class="grow">
        <label for="q">Search name or URL</label>
        <input id="q" name="q" value="<?php echo h($q); ?>">
    </div>
    <div>
        <label for="filter">Filter</label>
        <select id="filter" name="filter">
            <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All</option>
            <option value="up" <?php echo $filter === 'up' ? 'selected' : ''; ?>>UP only</option>
            <option value="down" <?php echo $filter === 'down' ? 'selected' : ''; ?>>DOWN only</option>
            <option value="today" <?php echo $filter === 'today' ? 'selected' : ''; ?>>Today</option>
            <option value="week" <?php echo $filter === 'week' ? 'selected' : ''; ?>>Last 7 days</option>
        </select>
    </div>
    <div>
        <label for="changes">History</label>
        <select id="changes" name="changes">
            <option value="0" <?php echo !$changesOnly ? 'selected' : ''; ?>>All checks</option>
            <option value="1" <?php echo $changesOnly ? 'selected' : ''; ?>>Status changes only</option>
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
            <th>Response</th>
            <th>HTTP</th>
            <th>Checked</th>
            <th>Change / alert</th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$logs): ?>
            <tr><td colspan="7" class="empty">No logs found.</td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $row): ?>
            <tr>
                <td><?php echo h($row['name']); ?></td>
                <td class="url"><?php echo h($row['url']); ?></td>
                <td><?php echo status_badge($row['status']); ?></td>
                <td><?php echo $row['response_time'] ? (int) $row['response_time'] . ' ms' : 'N/A'; ?></td>
                <td><?php echo $row['http_code'] ? (int) $row['http_code'] : '—'; ?></td>
                <td><?php echo h($row['checked_at']); ?></td>
                <td>
                    <?php if ($row['is_status_change']): ?>Status changed<?php endif; ?>
                    <?php if ($row['alert_type']): ?> · <?php echo h($row['alert_type']); ?><?php endif; ?>
                    <?php if (!$row['is_status_change'] && !$row['alert_type']): ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
