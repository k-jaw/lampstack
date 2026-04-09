<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Create and return a mysqli connection using the configured constants.
 *
 * @throws RuntimeException if the connection fails.
 */
function db_connect(): mysqli
{
    $mysqli = @new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME, DB_PORT);
    if ($mysqli->connect_errno !== 0) {
        throw new RuntimeException('Database connection failed: ' . $mysqli->connect_error);
    }

    // Always use UTF-8 for consistent text handling.
    if (!$mysqli->set_charset('utf8mb4')) {
        throw new RuntimeException('Failed to set charset: ' . $mysqli->error);
    }

    return $mysqli;
}
