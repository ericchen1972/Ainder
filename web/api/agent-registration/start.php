<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/lib/agent_registration.php';
require_once dirname(__DIR__, 2).'/lib/api.php';
require_once dirname(__DIR__, 2).'/lib/auth.php';
require_once dirname(__DIR__, 2).'/lib/config.php';
require_once dirname(__DIR__, 2).'/lib/database.php';
require_once dirname(__DIR__, 2).'/lib/session.php';

ainder_start_session();
ainder_require_post_json();
if (!ainder_pending_identity_is_valid($_SESSION, time())) {
    ainder_json_error('AUTH_REQUIRED', 'Pending Google identity expired.', 401);
}
ainder_require_json_csrf();

try {
    $body = ainder_json_body();
    $identity = $_SESSION['ainder_pending_identity'];
    $session = ainder_start_agent_registration(
        ainder_database(ainder_config()),
        (string) $identity['google_sub'],
        (string) ($body['idempotency_key'] ?? ''),
        new DateTimeImmutable('now')
    );
    ainder_json_success([
        'registration_id' => $session['id'],
        'expires_at' => (new DateTimeImmutable((string) $session['expires_at']))
            ->format(DATE_ATOM),
    ], 201);
} catch (InvalidArgumentException $error) {
    ainder_json_error($error->getMessage(), 'Registration request is invalid.', 422);
} catch (Throwable) {
    ainder_json_error('REGISTRATION_START_FAILED', 'Unable to start registration.', 503);
}
