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
    $config = ainder_config();
    $upload = ainder_prepare_agent_upload(
        ainder_database($config),
        (string) ($body['registration_id'] ?? ''),
        (string) $identity['google_sub'],
        (int) ($body['sort_order'] ?? 0),
        (string) ($body['mime_type'] ?? ''),
        (int) ($body['byte_size'] ?? 0),
        new DateTimeImmutable('now')
    );
    $expires = new DateTimeImmutable((string) $upload['expires_at']);
    ainder_json_success([
        'upload_id' => $upload['id'],
        'upload_url' => ainder_signed_upload_url(
            $config,
            (string) $upload['id'],
            $expires->getTimestamp()
        ),
        'method' => 'PUT',
        'headers' => ['Content-Type' => (string) $body['mime_type']],
        'expires_at' => $expires->format(DATE_ATOM),
    ], 201);
} catch (InvalidArgumentException $error) {
    ainder_json_error($error->getMessage(), 'Photo request is invalid.', 422);
} catch (RuntimeException $error) {
    ainder_json_error($error->getMessage(), 'Registration session is unavailable.', 409);
} catch (Throwable) {
    ainder_json_error('UPLOAD_PREPARE_FAILED', 'Unable to prepare photo upload.', 503);
}
