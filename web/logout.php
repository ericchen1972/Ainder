<?php

declare(strict_types=1);

require_once __DIR__.'/lib/session.php';

ainder_start_session();
ainder_clear_session();

header('Location: /ainder/');
exit;
