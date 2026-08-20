<?php
require_once dirname(__DIR__) . '/includes/auth.php';

if (current_admin()) {
    redirect('dashboard.php');
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Enter username and password.';
    } elseif (!attempt_login($username, $password)) {
        $error = 'Invalid username or password.';
    } else {
        redirect('dashboard.php');
    }
}

$pageTitle = 'Admin login';
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
        <h1>Admin login</h1>
        <p class="subtitle">Website Monitoring System</p>
        <?php if ($error): ?>
            <div class="flash flash-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="on">
            <p>
                <label for="username">Username</label>
                <input id="username" name="username" type="text" value="<?php echo h($username); ?>" autocomplete="username" required>
            </p>
            <p>
                <label for="password">Password</label>
                <div class="password-wrap">
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                    <button type="button" class="toggle-pass" data-toggle-password="password">Show</button>
                </div>
            </p>
            <button class="btn" type="submit">Login</button>
        </form>
        <p class="help"><a href="<?php echo h(public_status_url()); ?>">View public status page</a> · <a href="forgot_password.php">Forgot password?</a></p>
        <p class="help">Change the admin password immediately after installation.</p>
    </div>
</div>
<script src="../assets/js/app.js"></script>
</body>
</html>
