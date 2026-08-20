<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$site = [
    'name' => '',
    'url' => '',
    'interval_minutes' => 5,
    'slow_threshold_ms' => (int) setting('slow_threshold_ms', DEFAULT_SLOW_MS),
];

if ($id) {
    $stmt = db()->prepare('SELECT * FROM websites WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$found) {
        flash_set('error', 'Website not found.');
        redirect('websites.php');
    }
    $site = $found;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $interval = (int) ($_POST['interval_minutes'] ?? 5);
    $slow = (int) ($_POST['slow_threshold_ms'] ?? DEFAULT_SLOW_MS);

    if ($name === '' || $url === '') {
        $error = 'Name and URL are required.';
    } elseif (!valid_url($url)) {
        $error = 'Enter a valid URL starting with http:// or https://';
    } elseif ($interval < 1) {
        $error = 'Interval must be at least 1 minute.';
    } elseif ($slow < 100) {
        $error = 'Slow threshold should be at least 100 ms.';
    } else {
        if ($id) {
            $stmt = db()->prepare(
                'UPDATE websites SET name = ?, url = ?, interval_minutes = ?, slow_threshold_ms = ? WHERE id = ?'
            );
            $stmt->bind_param('ssiii', $name, $url, $interval, $slow, $id);
            $stmt->execute();
            $stmt->close();
            flash_set('success', 'Website updated.');
        } else {
            $stmt = db()->prepare(
                'INSERT INTO websites (name, url, interval_minutes, slow_threshold_ms, status)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $unknown = 'UNKNOWN';
            $stmt->bind_param('ssiis', $name, $url, $interval, $slow, $unknown);
            $stmt->execute();
            $stmt->close();
            flash_set('success', 'Website added.');
        }
        redirect('websites.php');
    }

    $site = array_merge($site, [
        'name' => $name,
        'url' => $url,
        'interval_minutes' => $interval,
        'slow_threshold_ms' => $slow,
    ]);
}

$pageTitle = $id ? 'Edit website' : 'Add website';
$currentPage = 'websites';
include dirname(__DIR__) . '/includes/header.php';
?>
<h1><?php echo $id ? 'Edit website' : 'Add website'; ?></h1>
<p class="subtitle">Interval is how often the background auto-check should check this URL.</p>

<?php if ($error): ?>
    <div class="flash flash-error"><?php echo h($error); ?></div>
<?php endif; ?>

<div class="card">
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
        <div class="form-grid">
            <div class="full">
                <label for="name">Website name</label>
                <input id="name" name="name" type="text" value="<?php echo h($site['name']); ?>" required>
            </div>
            <div class="full">
                <label for="url">Website URL</label>
                <input id="url" name="url" type="url" value="<?php echo h($site['url']); ?>" placeholder="https://example.com" required>
            </div>
            <div>
                <label for="interval_minutes">Monitoring interval (minutes)</label>
                <input id="interval_minutes" name="interval_minutes" type="number" min="1" value="<?php echo (int) $site['interval_minutes']; ?>" required>
            </div>
            <div>
                <label for="slow_threshold_ms">Slow response threshold (ms)</label>
                <input id="slow_threshold_ms" name="slow_threshold_ms" type="number" min="100" value="<?php echo (int) $site['slow_threshold_ms']; ?>" required>
            </div>
        </div>
        <p class="actions" style="margin-top:16px;">
            <button class="btn" type="submit">Save</button>
            <a class="btn btn-secondary" href="websites.php">Cancel</a>
        </p>
    </form>
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
