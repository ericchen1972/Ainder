<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/database.php';
require_once dirname(__DIR__).'/lib/photos.php';

$localPath = dirname(__DIR__).'/config.local.php';
if (!is_file($localPath)) {
    http_response_code(503);
    exit('Maintenance configuration unavailable.');
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

try {
    $database = ainder_database(ainder_config());
    $rows = $database->query(
        "SELECT u.id, u.processed_path, u.registration_id "
        ."FROM agent_registration_uploads u "
        ."INNER JOIN agent_registration_sessions s ON s.id = u.registration_id "
        ."WHERE u.status IN ('prepared', 'ready', 'failed') "
        ."AND u.expires_at < NOW() AND s.status <> 'consumed'"
    )->fetch_all(MYSQLI_ASSOC);

    $agentRoot = dirname(__DIR__).'/uploads/.agent';
    $safeRoot = rtrim($agentRoot, '/').'/';
    $paths = [];
    $directories = [];
    foreach ($rows as $row) {
        $path = trim((string) ($row['processed_path'] ?? ''));
        if ($path !== '' && str_starts_with($path, $safeRoot)) {
            $paths[] = $path;
            $directories[] = dirname($path);
        }
    }
    $removedFiles = count(array_filter($paths, 'is_file'));
    ainder_cleanup_photo_paths($paths);

    $database->begin_transaction();
    $database->query(
        "UPDATE agent_registration_sessions SET status = 'expired' "
        ."WHERE status = 'active' AND expires_at < NOW()"
    );
    $expiredSessions = $database->affected_rows;
    $database->query(
        "DELETE u FROM agent_registration_uploads u "
        ."INNER JOIN agent_registration_sessions s ON s.id = u.registration_id "
        ."WHERE u.status IN ('prepared', 'ready', 'failed') "
        ."AND u.expires_at < NOW() AND s.status <> 'consumed'"
    );
    $removedRows = $database->affected_rows;
    $database->commit();

    foreach (array_unique($directories) as $directory) {
        if (str_starts_with($directory.'/', $safeRoot) && is_dir($directory)) {
            @rmdir($directory);
        }
    }
} catch (Throwable) {
    if (isset($database)) {
        try {
            $database->rollback();
        } catch (Throwable) {
        }
    }
    http_response_code(503);
    exit('Cleanup failed.');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'expired_sessions' => $expiredSessions,
    'removed_upload_rows' => $removedRows,
    'removed_files' => $removedFiles,
], JSON_UNESCAPED_SLASHES);
