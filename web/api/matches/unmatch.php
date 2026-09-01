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
    $database->begin_transaction();
    $match = ainder_member_match($database, $memberId, $matchId);
    if ($match === null) {
        throw new RuntimeException('MATCH_NOT_FOUND');
    }
    $lowId = (int) $match['user_low_id'];
    $highId = (int) $match['user_high_id'];

    $deleteLikes = $database->prepare(
        'DELETE FROM likes WHERE '
        .'(sender_user_id = ? AND recipient_user_id = ?) OR '
        .'(sender_user_id = ? AND recipient_user_id = ?)'
    );
    $deleteLikes->bind_param('iiii', $lowId, $highId, $highId, $lowId);
    $deleteLikes->execute();
    $deleteMatch = $database->prepare(
        'DELETE FROM matches WHERE id = ? '
        .'AND (user_low_id = ? OR user_high_id = ?)'
    );
    $deleteMatch->bind_param('iii', $matchId, $memberId, $memberId);
    $deleteMatch->execute();
    $database->commit();

    ainder_json_success([
        'status' => 'UNMATCHED',
        'match_id' => $matchId,
    ]);
} catch (RuntimeException $error) {
    if (isset($database) && $database instanceof mysqli) {
        $database->rollback();
    }
    ainder_json_error($error->getMessage(), 'Match was not removed.', 404);
} catch (Throwable) {
    if (isset($database) && $database instanceof mysqli) {
        $database->rollback();
    }
    ainder_json_error('UNMATCH_FAILED', 'Match was not removed.', 503);
}
