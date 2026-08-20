<?php
/**
 * MySQL connection
 * Windows (XAMPP / php -S) uses local defaults.
 * Linux (cPanel) reads config/database.local.php (gitignored).
 * Copy database.local.php.example to database.local.php on the server.
 */

if (PHP_OS_FAMILY !== 'Windows' && is_file(__DIR__ . '/database.local.php')) {
    require __DIR__ . '/database.local.php';
}

if (!defined('DB_HOST')) {
    if (PHP_OS_FAMILY === 'Windows') {
        define('DB_HOST', 'localhost');
        define('DB_NAME', 'web_monitoring');
        define('DB_USER', 'root');
        define('DB_PASS', '123qwe');
    } else {
        die('Missing config/database.local.php. Copy database.local.php.example and fill in your hosting database details.');
    }
}

define('DB_CHARSET', 'utf8mb4');

function db(): mysqli
{
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    mysqli_report(MYSQLI_REPORT_OFF);

    $conn = @new mysqli(DB_HOST, DB_USER, DB_PASS);

    if ($conn->connect_error) {
        die('Cannot connect to MySQL. Check username/password in config/database.php. ' . $conn->connect_error);
    }

    $conn->set_charset(DB_CHARSET);

    if (!$conn->select_db(DB_NAME)) {
        die('Database "' . DB_NAME . '" does not exist. Import sql/schema.sql in phpMyAdmin, or open /install.php once.');
    }

    return $conn;
}
