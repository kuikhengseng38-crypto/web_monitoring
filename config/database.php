<?php
/**
 * MySQL connection.
 * Credentials live in database.local.php (gitignored).
 * Copy database.local.php.example to database.local.php.
 */

if (!is_file(__DIR__ . '/database.local.php')) {
    http_response_code(500);
    die('Missing config/database.local.php. Copy database.local.php.example and fill in your database details.');
}

require_once __DIR__ . '/database.local.php';

if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
    http_response_code(500);
    die('database.local.php is incomplete. Compare it with database.local.php.example.');
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
        die('Cannot connect to MySQL. Check config/database.local.php. ' . $conn->connect_error);
    }

    $conn->set_charset(DB_CHARSET);

    if (!$conn->select_db(DB_NAME)) {
        die('Database does not exist. Import sql/schema.sql in phpMyAdmin, or open /install.php once.');
    }

    return $conn;
}
