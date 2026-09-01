<?php

declare(strict_types=1);

function ainder_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('AINDERSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/ainder/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function ainder_clear_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            '',
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}
