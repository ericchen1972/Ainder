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
    $memberId = ainder_complete_agent_registration(
        ainder_database(ainder_config()),
        $identity,
        [
            'display_name' => trim((string) ($body['display_name'] ?? '')),
            'birth_date' => (string) ($body['birth_date'] ?? ''),
            'gender' => (string) ($body['gender'] ?? ''),
        ],
        [
            'profile_text' => trim((string) ($body['profile_text'] ?? '')),
            'agent_known_duration_days' => $body['agent_known_duration_days'] ?? null,
            'interaction_density' => (string) ($body['interaction_density'] ?? ''),
        ],
        (string) ($body['registration_id'] ?? ''),
        (string) ($body['idempotency_key'] ?? ''),
        is_array($body['upload_ids'] ?? null) ? $body['upload_ids'] : [],
        dirname(__DIR__, 2).'/uploads/profiles',
        new DateTimeImmutable('now')
    );
    unset(
        $_SESSION['ainder_pending_identity'],
        $_SESSION['ainder_pending_expires_at'],
        $_SESSION['ainder_form_csrf'],
        $_SESSION['ainder_form_flash']
    );
    $_SESSION['ainder_member_id'] = $memberId;
    session_regenerate_id(true);
    ainder_json_success([
        'member_id' => $memberId,
        'redirect_url' => '/ainder/app/',
    ], 201);
} catch (InvalidArgumentException $error) {
    ainder_json_error($error->getMessage(), 'Registration data is invalid.', 422);
} catch (RuntimeException $error) {
    ainder_json_error($error->getMessage(), 'Registration cannot be completed.', 409);
} catch (Throwable) {
    ainder_json_error('REGISTRATION_FAILED', 'Unable to create Ainder account.', 503);
}
