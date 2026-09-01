<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/session.php';

ainder_start_session();

if (!isset($_SESSION['ainder_member_id'])) {
    header('Location: /ainder/');
    exit;
}

$title = 'Ainder';
$message = 'Your Ainder home is coming next.';
require dirname(__DIR__).'/placeholder.php';
