<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/database.php';
require_once dirname(__DIR__).'/lib/session.php';
require_once dirname(__DIR__).'/lib/test_accounts.php';

ainder_start_session();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
    || !ainder_form_csrf_is_valid((string) ($_POST['csrf_token'] ?? ''))) {
    header('Location: /ainder/?login=test-failed');
    exit;
}

$scenario = ainder_test_account_scenario(
    trim((string) ($_POST['account_slug'] ?? ''))
);
if ($scenario === null) {
    header('Location: /ainder/?login=test-failed');
    exit;
}

try {
    $database = ainder_database(ainder_config());
    $memberId = ainder_reset_test_account($database, $scenario);
    ainder_record_login($database, $memberId);
} catch (Throwable) {
    http_response_code(503);
    exit('Ainder is temporarily unavailable.');
}

session_regenerate_id(true);
unset(
    $_SESSION['ainder_pending_identity'],
    $_SESSION['ainder_pending_expires_at']
);
$_SESSION['ainder_member_id'] = $memberId;

header('Location: /ainder/app/');
exit;
