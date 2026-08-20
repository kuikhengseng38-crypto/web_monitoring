<?php
/**
 * MySQL connection
 * Windows (XAMPP / php -S) uses local defaults.
 * Linux (cPanel) uses the hosting database.
 */

if (PHP_OS_FAMILY === 'Windows') {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'web_monitoring');
    define('DB_USER', 'root');
    define('DB_PASS', '123qwe');
} else {
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'synergy1_kuikhengseng_web_monitoring');
    define('DB_USER', 'synergy1_shaoxi');
    define('DB_PASS', 'p07e&61#5e9^c]Y}');
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
