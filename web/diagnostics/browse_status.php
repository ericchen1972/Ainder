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
        'male_view_candidates' => $count(
            $database,
            "SELECT COUNT(*) FROM users WHERE status = 'active' AND gender = 'female'"
        ),
        'female_view_candidates' => $count(
            $database,
            "SELECT COUNT(*) FROM users WHERE status = 'active' AND gender = 'male'"
        ),
        'demo_female_candidates' => $count(
            $database,
            "SELECT COUNT(*) FROM users "
            ."WHERE status = 'active' AND gender = 'female' AND is_demo = 1"
        ),
        'demo_male_candidates' => $count(
            $database,
            "SELECT COUNT(*) FROM users "
            ."WHERE status = 'active' AND gender = 'male' AND is_demo = 1"
        ),
        'basic_intro_column_exists' => $count(
            $database,
            "SELECT COUNT(*) FROM information_schema.COLUMNS "
            ."WHERE TABLE_SCHEMA = 'ainder' AND TABLE_NAME = 'users' "
            ."AND COLUMN_NAME = 'basic_intro'"
        ),
        'active_candidates_without_two_photos' => $count(
            $database,
            "SELECT COUNT(*) FROM (SELECT u.id FROM users u "
            ."LEFT JOIN user_photos p ON p.user_id = u.id "
            ."WHERE u.status = 'active' GROUP BY u.id "
            ."HAVING COUNT(p.id) < 2 OR COUNT(p.id) > 6) invalid_photo_counts"
        ),
    ];
} catch (Throwable) {
    http_response_code(503);
    exit('Diagnostic failed.');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_SLASHES);
