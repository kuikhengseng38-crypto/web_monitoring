<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/telegram.php';
require_login();

$error = '';
$testResult = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $adminId = (int) $_SESSION['admin_id'];
        $stmt = db()->prepare('SELECT password FROM admins WHERE id = ?');
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row || !password_verify($current, $row['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $update = db()->prepare('UPDATE admins SET password = ? WHERE id = ?');
            $update->bind_param('si', $hash, $adminId);
            $update->execute();
            $update->close();
            flash_set('success', 'Password changed.');
            redirect('settings.php');
        }
    } else {
        $token = trim($_POST['telegram_bot_token'] ?? '');
        $chatId = trim($_POST['telegram_chat_id'] ?? '');
        $timeout = max(3, (int) ($_POST['check_timeout'] ?? 10));
        $slow = max(100, (int) ($_POST['slow_threshold_ms'] ?? DEFAULT_SLOW_MS));
        $treat4xx = isset($_POST['treat_4xx_as_down']) ? '1' : '0';

        setting_set('telegram_bot_token', $token);
        setting_set('telegram_chat_id', $chatId);
        setting_set('check_timeout', (string) $timeout);
        setting_set('slow_threshold_ms', (string) $slow);
        setting_set('treat_4xx_as_down', $treat4xx);

        if ($action === 'test') {
            $sent = telegram_send_result("TEST: Website Monitor\nStatus: connected\nTime: " . date('Y-m-d H:i:s'));
            $testResult = $sent['ok']
                ? 'Test message sent. Check Telegram.'
                : ('Could not send: ' . ($sent['error'] ?: 'unknown error'));
        } else {
            flash_set('success', 'Settings saved.');
            redirect('settings.php');
        }
    }
}

$token = setting('telegram_bot_token', TELEGRAM_BOT_TOKEN);
$chatId = setting('telegram_chat_id', TELEGRAM_CHAT_ID);
$timeout = setting('check_timeout', CHECK_TIMEOUT);
$slow = setting('slow_threshold_ms', DEFAULT_SLOW_MS);
$treat4xx = ((string) setting('treat_4xx_as_down', '1')) !== '0';
$telegramError = telegram_last_error();

$cronFile = realpath(dirname(__DIR__) . '/cron/monitor.php') ?: dirname(__DIR__) . '/cron/monitor.php';
$cronCli = '* * * * * php ' . $cronFile;
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$host = $_SERVER['HTTP_HOST'] ?? 'yourdomain.com';
$adminDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/admin'));
$webRoot = rtrim(dirname($adminDir), '/');
$cronUrl = ($https ? 'https' : 'http') . '://' . $host . $webRoot . '/cron/monitor.php?key=' . rawurlencode(CRON_KEY);

$pageTitle = 'Settings';
$currentPage = 'settings';
include dirname(__DIR__) . '/includes/header.php';
?>
<h1>Settings</h1>
<p class="subtitle">Telegram bot, check timeout, and admin password.</p>

<?php if ($error): ?>
    <div class="flash flash-error"><?php echo h($error); ?></div>
<?php endif; ?>
<?php if ($testResult): ?>
    <div class="flash flash-info"><?php echo h($testResult); ?></div>
<?php endif; ?>
<?php if ($telegramError && !$testResult): ?>
    <div class="flash flash-error">Last Telegram error: <?php echo h($telegramError); ?></div>
<?php endif; ?>

<div class="card">
    <h2>Telegram alerts</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
        <div class="form-grid">
            <div class="full">
                <label for="telegram_bot_token">Bot token</label>
                <div class="password-wrap">
                    <input id="telegram_bot_token" name="telegram_bot_token" type="password" value="<?php echo h($token); ?>">
                    <button type="button" class="toggle-pass" data-toggle-password="telegram_bot_token">Show</button>
                </div>
            </div>
            <div class="full">
                <label for="telegram_chat_id">Chat ID</label>
                <input id="telegram_chat_id" name="telegram_chat_id" type="text" value="<?php echo h($chatId); ?>">
            </div>
            <div>
                <label for="check_timeout">HTTP timeout (seconds)</label>
                <input id="check_timeout" name="check_timeout" type="number" min="3" value="<?php echo h((string) $timeout); ?>">
            </div>
            <div>
                <label for="slow_threshold_ms">Default slow threshold (ms)</label>
                <input id="slow_threshold_ms" name="slow_threshold_ms" type="number" min="100" value="<?php echo h((string) $slow); ?>">
            </div>
            <div class="full">
                <label>
                    <input type="checkbox" name="treat_4xx_as_down" value="1" <?php echo $treat4xx ? 'checked' : ''; ?>>
                    Treat HTTP 4xx (404, 403) as DOWN
                </label>
                <p class="help">A 404 page means the URL is missing, so it should not show as UP.</p>
            </div>
        </div>
        <p class="actions" style="margin-top:16px;">
            <button class="btn" type="submit" name="action" value="save">Save settings</button>
            <button class="btn btn-secondary" type="submit" name="action" value="test">Send test message</button>
        </p>
    </form>
</div>

<div class="card" style="margin-top:16px;">
    <h2>Change password</h2>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo h(csrf_token()); ?>">
        <input type="hidden" name="action" value="password">
        <div class="form-grid">
            <div class="full">
                <label for="current_password">Current password</label>
                <div class="password-wrap">
                    <input id="current_password" name="current_password" type="password" required>
                    <button type="button" class="toggle-pass" data-toggle-password="current_password">Show</button>
                </div>
            </div>
            <div>
                <label for="new_password">New password</label>
                <div class="password-wrap">
                    <input id="new_password" name="new_password" type="password" required>
                    <button type="button" class="toggle-pass" data-toggle-password="new_password">Show</button>
                </div>
            </div>
            <div>
                <label for="confirm_password">Confirm new password</label>
                <input id="confirm_password" name="confirm_password" type="password" required>
            </div>
        </div>
        <p style="margin-top:16px;"><button class="btn" type="submit">Update password</button></p>
    </form>
</div>

<div class="card" style="margin-top:16px;">
    <h2>cPanel Cron Job</h2>
    <p class="help" style="margin-top:0;">On cPanel go to <strong>Cron Jobs</strong> and run this every 1 minute. Each website still uses its own interval.</p>
    <label for="cron_cli">Command</label>
    <textarea id="cron_cli" readonly rows="2"><?php echo h($cronCli); ?></textarea>
    <p class="help">If PHP CLI is not allowed, use this URL instead (wget / curl):</p>
    <input type="text" readonly value="<?php echo h($cronUrl); ?>">
</div>
<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
