<?php

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$localPath = dirname(__DIR__).'/config.local.php';
if (!is_file($localPath)) {
    http_response_code(503);
    exit('Seed configuration unavailable.');
}

$local = require $localPath;
$providedToken = (string) ($_POST['token'] ?? '');
$expectedToken = (string) ($local['migration_token'] ?? '');
if ($providedToken === ''
    || $expectedToken === ''
    || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    exit('Forbidden');
}

require_once dirname(__DIR__).'/lib/agent_actions.php';
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/database.php';

try {
    $senderName = trim((string) ($_POST['sender_display_name'] ?? ''));
    $recipientName = trim((string) ($_POST['recipient_display_name'] ?? ''));
    $agentOpinion = ainder_normalize_agent_opinion(
        (string) ($_POST['agent_opinion'] ?? '')
    );
    if ($senderName === '' || $recipientName === '') {
        throw new InvalidArgumentException('MEMBER_NAME_REQUIRED');
    }

    $database = ainder_database(ainder_config());
    $findMember = static function (string $name) use ($database): array {
        $statement = $database->prepare(
            "SELECT id, is_demo FROM users WHERE display_name = ? "
            ."AND status = 'active'"
        );
        $statement->bind_param('s', $name);
        $statement->execute();
        $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
        if (count($rows) !== 1) {
            throw new RuntimeException('MEMBER_NOT_UNIQUE');
        }

        return $rows[0];
    };

    $sender = $findMember($senderName);
    $recipient = $findMember($recipientName);
    if ((int) $sender['is_demo'] !== 1
        || (int) $recipient['is_demo'] === 1) {
        throw new RuntimeException('DEMO_DIRECTION_INVALID');
    }

    $senderId = (int) $sender['id'];
    $recipientId = (int) $recipient['id'];
    $profiles = $database->prepare(
        'SELECT COUNT(*) AS profile_count FROM agent_profiles '
        .'WHERE user_id IN (?, ?)'
    );
    $profiles->bind_param('ii', $senderId, $recipientId);
    $profiles->execute();
    $profileCount = (int) $profiles->get_result()->fetch_assoc()['profile_count'];
    if ($profileCount !== 2) {
        throw new RuntimeException('PROFILE_REQUIRED');
    }

    $reciprocal = $database->prepare(
        'SELECT id FROM likes WHERE sender_user_id = ? '
        .'AND recipient_user_id = ? LIMIT 1'
    );
    $reciprocal->bind_param('ii', $recipientId, $senderId);
    $reciprocal->execute();
    if ($reciprocal->get_result()->fetch_assoc() !== null) {
        throw new RuntimeException('RECIPROCAL_LIKE_EXISTS');
    }

    $like = $database->prepare(
        'INSERT INTO likes '
        .'(sender_user_id, recipient_user_id, agent_opinion) '
        .'VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE '
        .'agent_opinion = VALUES(agent_opinion), id = LAST_INSERT_ID(id)'
    );
    $like->bind_param('iis', $senderId, $recipientId, $agentOpinion);
    $like->execute();

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'like_id' => $database->insert_id,
        'sender_user_id' => $senderId,
        'recipient_user_id' => $recipientId,
        'reciprocal' => false,
    ], JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $error) {
    http_response_code(422);
    exit($error->getMessage());
} catch (RuntimeException $error) {
    http_response_code(409);
    exit($error->getMessage());
} catch (Throwable) {
    http_response_code(503);
    exit('Seed failed.');
}
