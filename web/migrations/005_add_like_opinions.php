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
    $database = new mysqli($mysqlhost, $mysqluser, $mysqlpasswd, 'ainder');
    $database->set_charset('utf8mb4');

    $schema = 'ainder';
    $table = 'likes';
    $column = 'agent_opinion';
    $statement = $database->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        .'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? '
        .'LIMIT 1'
    );
    $statement->bind_param('sss', $schema, $table, $column);
    $statement->execute();

    if ($statement->get_result()->fetch_row() === null) {
        $database->query(
            'ALTER TABLE likes ADD COLUMN agent_opinion TEXT NULL '
            .'AFTER recipient_user_id'
        );
    }
} catch (Throwable) {
    http_response_code(503);
    exit('Migration failed.');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'migration' => '005_add_like_opinions',
], JSON_UNESCAPED_SLASHES);
