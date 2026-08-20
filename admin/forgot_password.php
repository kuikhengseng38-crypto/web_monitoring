<?php
require_once dirname(__DIR__) . '/includes/auth.php';

if (current_admin()) {
    redirect('dashboard.php');
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $resetKey = trim($_POST['reset_key'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($username === '' || $resetKey === '' || $newPassword === '') {
        $error = 'Fill in all fields.';
    } elseif (!hash_equals(ADMIN_RESET_KEY, $resetKey)) {
        $error = 'Reset key is incorrect. Check config/config.php.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($newPassword !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = db()->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$admin) {
            $error = 'Admin username not found.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = db()->prepare('UPDATE admins SET password = ? WHERE id = ?');
            $update->bind_param('si', $hash, $admin['id']);
            $update->execute();
            $update->close();
            $message = 'Password updated. You can log in now.';
        }
    }
}

$pageTitle = 'Forgot password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($pageTitle); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <h1>Reset password</h1>
        <p class="subtitle">Use the reset key from config/config.php</p>
        <?php if ($error): ?>
            <div class="flash flash-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="flash flash-success"><?php echo h($message); ?></div>
        <?php endif; ?>
        <form method="post">
            <p>
                <label for="username">Admin username</label>
                <input id="username" name="username" type="text" required>
            </p>
            <p>
                <label for="reset_key">Reset key</label>
                <div class="password-wrap">
                    <input id="reset_key" name="reset_key" type="password" required>
                    <button type="button" class="toggle-pass" data-toggle-password="reset_key">Show</button>
                </div>
            </p>
            <p>
                <label for="new_password">New password</label>
                <div class="password-wrap">
                    <input id="new_password" name="new_password" type="password" required>
                    <button type="button" class="toggle-pass" data-toggle-password="new_password">Show</button>
                </div>
            </p>
            <p>
                <label for="confirm_password">Confirm password</label>
                <input id="confirm_password" name="confirm_password" type="password" required>
            </p>
            <button class="btn" type="submit">Reset password</button>
        </form>
        <p class="help"><a href="login.php">Back to login</a></p>
    </div>
</div>
<script src="../assets/js/app.js"></script>
</body>
</html>
