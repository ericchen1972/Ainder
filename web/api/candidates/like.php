<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/lib/agent_actions.php';
require_once dirname(__DIR__, 2).'/lib/api.php';
require_once dirname(__DIR__, 2).'/lib/config.php';
require_once dirname(__DIR__, 2).'/lib/database.php';
require_once dirname(__DIR__, 2).'/lib/session.php';

$profileCodes = [
    'SELF_PROFILE_MISSING',
    'SELF_PROFILE_EXPIRED',
    'TARGET_PROFILE_MISSING',
];
ainder_start_session();
ainder_require_post_json();
$memberId = (int) ($_SESSION['ainder_member_id'] ?? 0);
if ($memberId < 1) {
    ainder_json_error('AUTH_REQUIRED', 'Member login required.', 401);
}
ainder_require_json_csrf();

try {
    $body = ainder_json_body();
    $result = ainder_send_agent_like(
        ainder_database(ainder_config()),
        $memberId,
        (int) ($body['candidate_id'] ?? 0),
        (string) ($body['evaluation_token'] ?? ''),
        (string) ($body['opinion'] ?? ''),
        new DateTimeImmutable('now')
    );
    ainder_json_success($result);
} catch (InvalidArgumentException $error) {
    ainder_json_error($error->getMessage(), 'Like request is invalid.', 422);
} catch (RuntimeException $error) {
    $code = in_array($error->getMessage(), $profileCodes, true)
        ? $error->getMessage()
        : 'LIKE_NOT_SENT';
    ainder_json_error($code, 'Like was not sent.', 409);
} catch (Throwable) {
    ainder_json_error('LIKE_NOT_SENT', 'Like was not sent.', 503);
}
