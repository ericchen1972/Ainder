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

function ainder_form_csrf_token(): string
{
    if (!isset($_SESSION['ainder_form_csrf'])) {
        $_SESSION['ainder_form_csrf'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['ainder_form_csrf'];
}

function ainder_form_csrf_is_valid(string $submittedToken): bool
{
    return isset($_SESSION['ainder_form_csrf'])
        && $submittedToken !== ''
        && hash_equals((string) $_SESSION['ainder_form_csrf'], $submittedToken);
}

function ainder_set_form_flash(array $errors, array $input): void
{
    $_SESSION['ainder_form_flash'] = [
        'errors' => $errors,
        'input' => $input,
    ];
}

function ainder_pull_form_flash(): array
{
    $flash = $_SESSION['ainder_form_flash'] ?? [
        'errors' => [],
        'input' => [],
    ];
    unset($_SESSION['ainder_form_flash']);

    return is_array($flash) ? $flash : ['errors' => [], 'input' => []];
}
