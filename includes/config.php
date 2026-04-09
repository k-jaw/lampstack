<?php
declare(strict_types=1);

// Database connection settings. Update these to match your VM's MySQL credentials.
// The values can be overridden with environment variables for containerized development.
/** @return string|false */
function config_env(string $key)
{
    $value = getenv($key);
    return $value !== false && $value !== '' ? $value : false;
}

if (!defined('DB_HOST')) {
    define('DB_HOST', config_env('DB_HOST') ?: '127.0.0.1');
}
if (!defined('DB_PORT')) {
    $port = config_env('DB_PORT');
    define('DB_PORT', $port !== false ? (int) $port : 3306);
}
if (!defined('DB_NAME')) {
    define('DB_NAME', config_env('DB_NAME') ?: 'lamp_issue_tracker');
}
if (!defined('DB_USER')) {
    define('DB_USER', config_env('DB_USER') ?: 'lamp_user');
}
if (!defined('DB_PASSWORD')) {
    define('DB_PASSWORD', config_env('DB_PASSWORD') ?: 'lamp_pass');
}

// Email notification settings.
