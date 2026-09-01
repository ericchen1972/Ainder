<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/auth.php';
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/database.php';
require_once dirname(__DIR__).'/lib/google.php';
require_once dirname(__DIR__).'/lib/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /ainder/');
    exit;
}

if (!ainder_google_csrf_is_valid(
    (string) ($_COOKIE['g_csrf_token'] ?? ''),
    (string) ($_POST['g_csrf_token'] ?? '')
)) {
    header('Location: /ainder/?login=failed');
    exit;
}

$config = ainder_config();
$payload = ainder_verify_google_token(
    (string) ($_POST['credential'] ?? ''),
    $config['google_client_id']
);
$identity = is_array($payload)
    ? ainder_normalize_google_identity($payload)
    : null;

if ($identity === null) {
    header('Location: /ainder/?login=failed');
    exit;
}

try {
    $database = ainder_database($config);
    $member = ainder_find_member($database, $identity['google_sub']);
} catch (Throwable) {
    http_response_code(503);
    exit('Ainder is temporarily unavailable.');
}

ainder_start_session();
session_regenerate_id(true);
unset(
    $_SESSION['ainder_member_id'],
    $_SESSION['ainder_pending_identity'],
    $_SESSION['ainder_pending_expires_at']
);

if (($member['status'] ?? null) === 'active') {
    $_SESSION['ainder_member_id'] = (int) $member['id'];
    ainder_record_login($database, (int) $member['id']);
} else {
    $_SESSION['ainder_pending_identity'] = $identity;
    $_SESSION['ainder_pending_expires_at'] = time() + 1800;
}

header('Location: '.ainder_login_destination($member));
exit;
