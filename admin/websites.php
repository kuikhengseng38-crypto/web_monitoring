<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_check();
    $id = (int) $_POST['delete_id'];
    $stmt = db()->prepare('DELETE FROM websites WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    flash_set('success', 'Website deleted.');
    redirect('websites.php');
}

$q = trim($_GET['q'] ?? '');
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
$sql .= ' ORDER BY name ASC';
$stmt = db()->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$websites = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$pageTitle = 'Websites';
$currentPage = 'websites';
include dirname(__DIR__) . '/includes/header.php';
?>
<h1>Manage websites</h1>
<p class="subtitle">Add the sites the background auto-check should check.</p>

<form method="get" class="toolbar">
    <div class="grow">
        <label for="q">Search</label>
        <input id="q" name="q" value="<?php echo h($q); ?>" placeholder="Name or URL">
    </div>
    <button class="btn" type="submit">Search</button>
    <a class="btn btn-success" href="website_form.php">Add website</a>
</form>

<div class="card" style="padding:0; overflow:auto;">
    <table>
        <thead>
        <tr>
            <th>Name</th>
            <th>URL</th>
            <th>Interval</th>
            <th>Status</th>
            <th>Slow threshold</th>
            <th></th>
        </tr>
        </thead>
        <tbody>
        <?php if (!$websites): ?>
            <tr><td colspan="6" class="empty">No websites yet.</td></tr>
        <?php endif; ?>
        <?php foreach ($websites as $site): ?>
            <tr>
                <td><?php echo h($site['name']); ?></td>
                <td class="url"><?php echo h($site['url']); ?></td>
                <td><?php echo (int) $site['interval_minutes']; ?> min</td>
                <td><?php echo status_badge($site['is_slow'] ? 'SLOW' : $site['status']); ?></td>
                <td><?php echo (int) $site['slow_threshold_ms']; ?> ms</td>
                <td class="actions">
                    <a class="btn btn-small btn-secondary" href="website_form.php?id=<?php echo (int) $site['id']; ?>">Edit</a>
                    <form method="post" data-confirm="Delete this website and its logs?" style="display:inline;">
                        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
                        <input type="hidden" name="delete_id" value="<?php echo (int) $site['id']; ?>">
                        <button class="btn btn-small btn-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
