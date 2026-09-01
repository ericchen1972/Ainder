<?php

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$localPath = dirname(__DIR__).'/config.local.php';
if (!is_file($localPath)) {
    http_response_code(503);
    exit('Seed configuration unavailable.');
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
require_once dirname(__DIR__).'/lib/demo.php';

try {
    $database = ainder_database(ainder_config());
    $manifest = require __DIR__.'/demo_members.php';
    $counts = ainder_seed_demo_members(
        $database,
        $manifest,
        new DateTimeImmutable('now')
    );
} catch (Throwable) {
    http_response_code(503);
    exit('Seed failed.');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'users' => $counts['users'],
    'photos' => $counts['photos'],
    'agent_profiles' => $counts['agent_profiles'],
], JSON_UNESCAPED_SLASHES);
