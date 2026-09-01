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
    $result = ainder_create_candidate_evaluation(
        ainder_database(ainder_config()),
        $memberId,
        (int) ($body['candidate_id'] ?? 0),
        new DateTimeImmutable('now')
    );
    ainder_json_success($result);
} catch (InvalidArgumentException $error) {
    ainder_json_error($error->getMessage(), 'Candidate is invalid.', 422);
} catch (RuntimeException $error) {
    $code = in_array($error->getMessage(), $profileCodes, true)
        ? $error->getMessage()
        : 'EVALUATION_FAILED';
    ainder_json_error($code, 'Candidate cannot be evaluated.', 409);
} catch (Throwable) {
    ainder_json_error('EVALUATION_FAILED', 'Candidate cannot be evaluated.', 503);
}
