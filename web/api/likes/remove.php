<?php

declare(strict_types=1);

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
    $likeId = (int) ($body['like_id'] ?? 0);
    if ($likeId < 1) {
        throw new InvalidArgumentException('LIKE_ID_INVALID');
    }

    $database = ainder_database(ainder_config());
    $database->begin_transaction();
    $like = $database->prepare(
        'SELECT l.id, l.sender_user_id, l.recipient_user_id, '
        .'EXISTS(SELECT 1 FROM likes reciprocal '
        .'WHERE reciprocal.sender_user_id = l.recipient_user_id '
        .'AND reciprocal.recipient_user_id = l.sender_user_id) '
        .'AS reciprocal_exists '
        .'FROM likes l WHERE l.id = ? AND l.recipient_user_id = ? '
        .'FOR UPDATE'
    );
    $like->bind_param('ii', $likeId, $memberId);
    $like->execute();
    $row = $like->get_result()->fetch_assoc();
    if (!is_array($row)) {
        throw new RuntimeException('LIKE_NOT_FOUND');
    }
    if ((int) $row['reciprocal_exists'] === 1) {
        throw new RuntimeException('RECIPROCAL_LIKE_EXISTS');
    }

    $delete = $database->prepare(
        'DELETE FROM likes WHERE id = ? AND recipient_user_id = ?'
    );
    $delete->bind_param('ii', $likeId, $memberId);
    $delete->execute();
    $database->commit();

    ainder_json_success(['removed_like_id' => $likeId]);
} catch (InvalidArgumentException $error) {
    ainder_json_error($error->getMessage(), 'Like removal is invalid.', 422);
} catch (RuntimeException $error) {
    if (isset($database) && $database instanceof mysqli) {
        $database->rollback();
    }
    ainder_json_error($error->getMessage(), 'Like was not removed.', 409);
} catch (Throwable) {
    if (isset($database) && $database instanceof mysqli) {
        $database->rollback();
    }
    ainder_json_error('LIKE_NOT_REMOVED', 'Like was not removed.', 503);
}
