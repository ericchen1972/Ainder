<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/lib/agent_profiles.php';
require_once dirname(__DIR__, 2).'/lib/api.php';
require_once dirname(__DIR__, 2).'/lib/config.php';
require_once dirname(__DIR__, 2).'/lib/database.php';
require_once dirname(__DIR__, 2).'/lib/session.php';

ainder_start_session();
ainder_require_post_json();
$memberId = (int) ($_SESSION['ainder_member_id'] ?? 0);
if ($memberId < 1) {
    ainder_json_error('AUTH_REQUIRED', 'Member login required.', 401);
}
ainder_require_json_csrf();

try {
    $body = ainder_json_body();
    $profile = ainder_upsert_agent_profile(
        ainder_database(ainder_config()),
        $memberId,
        [
            'profile_text' => trim((string) ($body['profile_text'] ?? '')),
            'agent_known_duration_days' => $body['agent_known_duration_days'] ?? null,
            'interaction_density' => (string) ($body['interaction_density'] ?? ''),
        ],
        new DateTimeImmutable('now')
    );
    ainder_json_success(['profile' => $profile]);
} catch (InvalidArgumentException) {
    ainder_json_error('PROFILE_INVALID', 'Agent Profile is invalid.', 422);
} catch (Throwable) {
    ainder_json_error('PROFILE_UPDATE_FAILED', 'Unable to update Agent Profile.', 503);
}
