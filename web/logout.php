<?php

declare(strict_types=1);

require_once __DIR__.'/lib/session.php';

ainder_start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !ainder_form_csrf_is_valid((string) ($_POST['csrf_token'] ?? ''))) {
    header('Location: /ainder/app/');
    exit;
}

ainder_clear_session();

header('Location: /ainder/');
exit;
