<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/auth.php';
require_once dirname(__DIR__).'/lib/session.php';

ainder_start_session();

if (!ainder_pending_identity_is_valid($_SESSION, time())) {
    unset(
        $_SESSION['ainder_pending_identity'],
        $_SESSION['ainder_pending_expires_at']
    );
    header('Location: /ainder/');
    exit;
}

$title = 'Complete your profile';
$message = 'Profile setup is coming next.';
require dirname(__DIR__).'/placeholder.php';
