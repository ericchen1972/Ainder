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

    $columnExists = static function (
        mysqli $database,
        string $table,
        string $column
    ): bool {
        $schema = 'ainder';
        $statement = $database->prepare(
            'SELECT 1 FROM information_schema.COLUMNS '
            .'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? '
            .'LIMIT 1'
        );
        $statement->bind_param('sss', $schema, $table, $column);
        $statement->execute();

        return $statement->get_result()->fetch_row() !== null;
    };

    $addColumn = static function (
        string $table,
        string $column,
        string $sql
    ) use ($database, $columnExists): void {
        if (!$columnExists($database, $table, $column)) {
            $database->query($sql);
        }
    };

    $addColumn(
        'users',
        'basic_intro',
        "ALTER TABLE users ADD COLUMN basic_intro VARCHAR(50) NOT NULL DEFAULT '' AFTER gender"
    );
    $addColumn(
        'users',
        'is_demo',
        'ALTER TABLE users ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER basic_intro'
    );
    $addColumn(
        'user_photos',
        'source_type',
        "ALTER TABLE user_photos ADD COLUMN source_type ENUM('local', 'unsplash') NOT NULL DEFAULT 'local' AFTER sort_order"
    );
    $addColumn(
        'user_photos',
        'source_photo_id',
        'ALTER TABLE user_photos ADD COLUMN source_photo_id VARCHAR(64) NULL AFTER source_type'
    );
    $addColumn(
        'user_photos',
        'photographer_name',
        'ALTER TABLE user_photos ADD COLUMN photographer_name VARCHAR(160) NULL AFTER source_photo_id'
    );
    $addColumn(
        'user_photos',
        'photographer_url',
        'ALTER TABLE user_photos ADD COLUMN photographer_url VARCHAR(500) NULL AFTER photographer_name'
    );
    $addColumn(
        'user_photos',
        'source_page_url',
        'ALTER TABLE user_photos ADD COLUMN source_page_url VARCHAR(500) NULL AFTER photographer_url'
    );

    $database->query(
        "CREATE TABLE IF NOT EXISTS agent_profiles (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            profile_text TEXT NOT NULL,
            agent_known_duration_days SMALLINT UNSIGNED NOT NULL,
            interaction_density ENUM('low', 'medium', 'high') NOT NULL,
            generated_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY agent_profiles_user_unique (user_id),
            CONSTRAINT agent_profiles_user_foreign
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
    'migration' => '002_add_demo_members',
    'tables' => ['users', 'user_photos', 'agent_profiles'],
], JSON_UNESCAPED_SLASHES);
