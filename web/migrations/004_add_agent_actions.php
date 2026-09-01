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
    $database->query(
        "CREATE TABLE IF NOT EXISTS agent_registration_sessions (
            id CHAR(32) NOT NULL,
            google_sub VARCHAR(255) NOT NULL,
            idempotency_key CHAR(64) NOT NULL,
            status ENUM('active', 'consumed', 'expired') NOT NULL DEFAULT 'active',
            member_id BIGINT UNSIGNED NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY agent_registration_identity_attempt_unique
                (google_sub, idempotency_key),
            KEY agent_registration_expiry_index (status, expires_at),
            CONSTRAINT agent_registration_member_foreign
                FOREIGN KEY (member_id) REFERENCES users (id)
                ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $database->query(
        "CREATE TABLE IF NOT EXISTS agent_registration_uploads (
            id CHAR(32) NOT NULL,
            registration_id CHAR(32) NOT NULL,
            sort_order TINYINT UNSIGNED NOT NULL,
            declared_mime VARCHAR(64) NOT NULL,
            declared_size INT UNSIGNED NOT NULL,
            processed_path VARCHAR(500) NULL,
            status ENUM('prepared', 'ready', 'consumed', 'failed')
                NOT NULL DEFAULT 'prepared',
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY agent_registration_upload_order_unique
                (registration_id, sort_order),
            KEY agent_registration_upload_expiry_index (status, expires_at),
            CONSTRAINT agent_registration_upload_session_foreign
                FOREIGN KEY (registration_id)
                REFERENCES agent_registration_sessions (id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $database->query(
        "CREATE TABLE IF NOT EXISTS candidate_evaluations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token_hash CHAR(64) NOT NULL,
            requester_user_id BIGINT UNSIGNED NOT NULL,
            candidate_user_id BIGINT UNSIGNED NOT NULL,
            expires_at DATETIME NOT NULL,
            consumed_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY candidate_evaluations_token_unique (token_hash),
            KEY candidate_evaluations_expiry_index (expires_at),
            CONSTRAINT candidate_evaluations_requester_foreign
                FOREIGN KEY (requester_user_id) REFERENCES users (id)
                ON DELETE CASCADE,
            CONSTRAINT candidate_evaluations_candidate_foreign
                FOREIGN KEY (candidate_user_id) REFERENCES users (id)
                ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $database->query(
        "CREATE TABLE IF NOT EXISTS likes (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            sender_user_id BIGINT UNSIGNED NOT NULL,
            recipient_user_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY likes_sender_recipient_unique
                (sender_user_id, recipient_user_id),
            CONSTRAINT likes_sender_foreign FOREIGN KEY (sender_user_id)
                REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT likes_recipient_foreign FOREIGN KEY (recipient_user_id)
                REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $database->query(
        "CREATE TABLE IF NOT EXISTS matches (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_low_id BIGINT UNSIGNED NOT NULL,
            user_high_id BIGINT UNSIGNED NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY matches_pair_unique (user_low_id, user_high_id),
            CONSTRAINT matches_low_foreign FOREIGN KEY (user_low_id)
                REFERENCES users (id) ON DELETE CASCADE,
            CONSTRAINT matches_high_foreign FOREIGN KEY (user_high_id)
                REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
} catch (Throwable) {
    http_response_code(503);
    exit('Migration failed.');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'migration' => '004_add_agent_actions',
    'tables' => [
        'agent_registration_sessions',
        'agent_registration_uploads',
        'candidate_evaluations',
        'likes',
        'matches',
    ],
], JSON_UNESCAPED_SLASHES);
