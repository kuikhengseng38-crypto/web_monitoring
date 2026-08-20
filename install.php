<?php
/**
 * One-time installer: creates database + tables using sql/schema.sql
 * Open in browser: http://localhost:8000/install.php
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
        die('Cannot use database "' . htmlspecialchars(DB_NAME) . '". Create it in cPanel MySQL Databases first.');
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

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Install complete</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <h1>Database ready</h1>
        <p>Tables were created in <strong><?php echo htmlspecialchars(DB_NAME); ?></strong>.</p>
        <p>Login: <strong>admin</strong> / <strong>admin123</strong></p>
        <p><a class="btn" href="admin/login.php">Go to login</a></p>
        <p class="help">You can delete install.php after this.</p>
    </div>
</div>
</body>
</html>
