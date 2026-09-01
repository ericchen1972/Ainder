<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/lib/api.php';
require_once dirname(__DIR__, 2).'/lib/config.php';
require_once dirname(__DIR__, 2).'/lib/database.php';
require_once dirname(__DIR__, 2).'/lib/matches.php';
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
    $matchId = (int) ($body['match_id'] ?? 0);
    $database = ainder_database(ainder_config());
    if (ainder_member_match($database, $memberId, $matchId) === null) {
        throw new RuntimeException('MATCH_NOT_FOUND');
    }
    $messages = ainder_list_match_messages($database, $memberId, $matchId);
    ainder_json_success(['messages' => $messages]);
} catch (RuntimeException $error) {
    ainder_json_error($error->getMessage(), 'Messages unavailable.', 404);
} catch (Throwable) {
    ainder_json_error('MESSAGES_UNAVAILABLE', 'Messages unavailable.', 503);
}
