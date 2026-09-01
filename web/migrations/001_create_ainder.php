<?php

declare(strict_types=1);

$localPath = dirname(__DIR__).'/config.local.php';
if (!is_file($localPath)) {
    http_response_code(503);
    exit('Migration configuration unavailable.');
}

$local = require $localPath;
$providedToken = PHP_SAPI === 'cli'
    ? (string) ($argv[1] ?? '')
    : (string) ($_POST['token'] ?? '');
$expectedToken = (string) ($local['migration_token'] ?? '');

if ($expectedToken === ''
    || $providedToken === ''
    || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    exit('Forbidden');
}

if (!defined('SWEETY_MYSQL_CONFIG_ONLY')) {
    define('SWEETY_MYSQL_CONFIG_ONLY', true);
}

require dirname(__DIR__, 2).'/mysql.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $database = new mysqli($mysqlhost, $mysqluser, $mysqlpasswd);
    $database->set_charset('utf8mb4');
    $database->query(
        'CREATE DATABASE IF NOT EXISTS ainder '
        .'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    $database->select_db('ainder');
    $database->query(
        "CREATE TABLE IF NOT EXISTS users (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            google_sub VARCHAR(255) NOT NULL,
            email VARCHAR(320) NOT NULL,
            display_name VARCHAR(120) NOT NULL,
            birth_date DATE NOT NULL,
            gender ENUM('male', 'female') NOT NULL,
            avatar_url TEXT NULL,
            status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
            last_login_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY users_google_sub_unique (google_sub),
            KEY users_email_index (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci"
    );
    $database->query(
        "CREATE TABLE IF NOT EXISTS user_photos (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            sort_order TINYINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_photos_user_sort_unique (user_id, sort_order),
            CONSTRAINT user_photos_user_foreign
                FOREIGN KEY (user_id) REFERENCES users (id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci"
    );
} catch (Throwable) {
    http_response_code(503);
    exit('Migration failed.');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'database' => 'ainder',
    'tables' => ['users', 'user_photos'],
], JSON_UNESCAPED_SLASHES);
