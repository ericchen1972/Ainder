<?php

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$localPath = dirname(__DIR__).'/config.local.php';
if (!is_file($localPath)) {
    http_response_code(503);
    exit('Diagnostic configuration unavailable.');
}

$local = require $localPath;
$providedToken = (string) ($_POST['token'] ?? '');
$expectedToken = (string) ($local['migration_token'] ?? '');
if ($providedToken === ''
    || $expectedToken === ''
    || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    exit('Forbidden');
}

require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/database.php';

$count = static function (mysqli $database, string $sql): int {
    $row = $database->query($sql)->fetch_row();

    return (int) ($row[0] ?? 0);
};

try {
    $database = ainder_database(ainder_config());
    $result = [
        'demo_users' => $count(
            $database,
            'SELECT COUNT(*) FROM users WHERE is_demo = 1'
        ),
        'demo_photos' => $count(
            $database,
            'SELECT COUNT(*) FROM user_photos p '
            .'INNER JOIN users u ON u.id = p.user_id WHERE u.is_demo = 1'
        ),
        'demo_agent_profiles' => $count(
            $database,
            'SELECT COUNT(*) FROM agent_profiles a '
            .'INNER JOIN users u ON u.id = a.user_id WHERE u.is_demo = 1'
        ),
        'members_with_two_photos' => $count(
            $database,
            'SELECT COUNT(*) FROM ('
            .'SELECT u.id FROM users u INNER JOIN user_photos p ON p.user_id = u.id '
            .'WHERE u.is_demo = 1 GROUP BY u.id HAVING COUNT(p.id) = 2'
            .') demo_photo_counts'
        ),
        'fresh_profiles' => $count(
            $database,
            'SELECT COUNT(*) FROM agent_profiles a '
            .'INNER JOIN users u ON u.id = a.user_id '
            .'WHERE u.is_demo = 1 AND a.expires_at > NOW()'
        ),
        'non_unsplash_demo_photo_count' => $count(
            $database,
            "SELECT COUNT(*) FROM user_photos p "
            ."INNER JOIN users u ON u.id = p.user_id "
            ."WHERE u.is_demo = 1 AND (p.source_type <> 'unsplash' "
            ."OR p.file_path NOT LIKE 'https://images.unsplash.com/%')"
        ),
    ];
} catch (Throwable) {
    http_response_code(503);
    exit('Diagnostic failed.');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_SLASHES);
