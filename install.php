<?php
/**
 * One-time installer: creates tables and the first admin account.
 * Open in browser after copying the *.example.php config files.
 */

require_once __DIR__ . '/config/database.php';

mysqli_report(MYSQLI_REPORT_OFF);

$conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);
if ($conn->connect_error) {
    die('Cannot connect to MySQL: ' . htmlspecialchars($conn->connect_error));
}

$conn->set_charset(DB_CHARSET);

if (!$conn->select_db(DB_NAME)) {
    @$conn->query(
        'CREATE DATABASE `' . $conn->real_escape_string(DB_NAME) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    if (!$conn->select_db(DB_NAME)) {
        die('Cannot use the configured database. Create it in MySQL / cPanel first, then set it in config/database.local.php.');
    }
}

$sqlFile = __DIR__ . '/sql/schema.sql';
if (!is_readable($sqlFile)) {
    die('Missing sql/schema.sql');
}

$sql = file_get_contents($sqlFile);
if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
}

if ($conn->errno) {
    die('Install failed: ' . htmlspecialchars($conn->error));
}

function install_admin_count(mysqli $conn): int
{
    $result = $conn->query('SELECT COUNT(*) AS total FROM admins');
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    $result->free();
    return (int) ($row['total'] ?? 0);
}

$error = '';
$adminCount = install_admin_count($conn);

if ($adminCount === 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Enter a username and password.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare('INSERT INTO admins (username, password) VALUES (?, ?)');
        $stmt->bind_param('ss', $username, $hash);
        if (!$stmt->execute()) {
            $error = 'Could not create admin account.';
        }
        $stmt->close();
        if ($error === '') {
            $adminCount = 1;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $adminCount > 0 ? 'Install complete' : 'Create admin'; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <?php if ($adminCount > 0): ?>
            <h1>Database ready</h1>
            <p>Tables were created. Change the admin password immediately after you log in.</p>
            <p><a class="btn" href="admin/login.php">Go to login</a></p>
            <p class="help">Delete install.php after this.</p>
        <?php else: ?>
            <h1>Create admin</h1>
            <p class="subtitle">Choose a username and password for the first admin account. Do not reuse a production password in public docs.</p>
            <?php if ($error): ?>
                <div class="flash flash-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <form method="post">
                <p>
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" required>
                </p>
                <p>
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required>
                </p>
                <p>
                    <label for="confirm_password">Confirm password</label>
                    <input id="confirm_password" name="confirm_password" type="password" required>
                </p>
                <button class="btn" type="submit">Create admin</button>
            </form>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
