<?php

declare(strict_types=1);

function ainder_test_account_scenarios(): array
{
    return [
        'grace' => [
            'label' => 'Grace Liu',
            'member_google_sub' => 'demo:010',
            'sender_google_sub' => 'demo:001',
            'agent_opinion' => "Grace's warmth, creativity, and respect for emotional boundaries look promising. Ethan's steady listening may suit her need for gentleness, while both should be careful not to postpone difficult conversations.",
        ],
        'john' => [
            'label' => 'John Carter',
            'member_google_sub' => 'demo:011',
            'sender_google_sub' => 'demo:020',
            'agent_opinion' => "John's reliability, humor, and active lifestyle look compatible with Evelyn's practical and health-conscious approach. They may connect through shared routines, as long as solutions do not replace emotional listening.",
        ],
    ];
}

function ainder_test_account_scenario(string $slug): ?array
{
    $scenarios = ainder_test_account_scenarios();

    return $scenarios[$slug] ?? null;
}

function ainder_test_account_cards(mysqli $database): array
{
    $statement = $database->prepare(
        'SELECT u.display_name, p.file_path AS photo_path '
        .'FROM users u INNER JOIN user_photos p '
        .'ON p.user_id = u.id AND p.sort_order = 1 '
        .'WHERE u.google_sub = ? AND u.status = \'active\' LIMIT 1'
    );
    $cards = [];

    foreach (ainder_test_account_scenarios() as $slug => $scenario) {
        $googleSub = (string) $scenario['member_google_sub'];
        $statement->bind_param('s', $googleSub);
        $statement->execute();
        $row = $statement->get_result()->fetch_assoc();
        if (!is_array($row)
            || trim((string) $row['display_name']) !== $scenario['label']
            || trim((string) $row['photo_path']) === '') {
            continue;
        }
        $cards[] = [
            'slug' => $slug,
            'label' => (string) $scenario['label'],
            'photo_path' => (string) $row['photo_path'],
        ];
    }

    return $cards;
}

function ainder_locked_test_member(mysqli $database, string $googleSub): array
{
    $statement = $database->prepare(
        'SELECT id, status, gender FROM users '
        .'WHERE google_sub = ? LIMIT 1 FOR UPDATE'
    );
    $statement->bind_param('s', $googleSub);
    $statement->execute();
    $member = $statement->get_result()->fetch_assoc();
    if (!is_array($member) || $member['status'] !== 'active') {
        throw new RuntimeException('TEST_MEMBER_UNAVAILABLE');
    }

    return $member;
}

function ainder_reset_test_account(mysqli $database, array $scenario): int
{
    $memberGoogleSub = trim((string) ($scenario['member_google_sub'] ?? ''));
    $senderGoogleSub = trim((string) ($scenario['sender_google_sub'] ?? ''));
    $agentOpinion = trim((string) ($scenario['agent_opinion'] ?? ''));
    if ($memberGoogleSub === ''
        || $senderGoogleSub === ''
        || $memberGoogleSub === $senderGoogleSub
        || $agentOpinion === ''
        || mb_strlen($agentOpinion) > 1000) {
        throw new InvalidArgumentException('TEST_SCENARIO_INVALID');
    }

    $database->begin_transaction();
    try {
        $member = ainder_locked_test_member($database, $memberGoogleSub);
        $sender = ainder_locked_test_member($database, $senderGoogleSub);
        $memberId = (int) $member['id'];
        $senderId = (int) $sender['id'];
        if ($memberId < 1
            || $senderId < 1
            || $memberId === $senderId
            || $member['gender'] === $sender['gender']) {
            throw new RuntimeException('TEST_SCENARIO_INVALID');
        }

        $profiles = $database->prepare(
            'SELECT COUNT(*) AS profile_count FROM agent_profiles '
            .'WHERE user_id IN (?, ?)'
        );
        $profiles->bind_param('ii', $memberId, $senderId);
        $profiles->execute();
        $profileCount = (int) $profiles->get_result()->fetch_assoc()['profile_count'];
        if ($profileCount !== 2) {
            throw new RuntimeException('TEST_PROFILE_UNAVAILABLE');
        }

        $photos = $database->prepare(
            'SELECT COUNT(DISTINCT user_id) AS photo_count FROM user_photos '
            .'WHERE user_id IN (?, ?) AND sort_order = 1'
        );
        $photos->bind_param('ii', $memberId, $senderId);
        $photos->execute();
        $photoCount = (int) $photos->get_result()->fetch_assoc()['photo_count'];
        if ($photoCount !== 2) {
            throw new RuntimeException('TEST_PHOTO_UNAVAILABLE');
        }

        $evaluations = $database->prepare(
            'DELETE FROM candidate_evaluations '
            .'WHERE requester_user_id = ? OR candidate_user_id = ?'
        );
        $evaluations->bind_param('ii', $memberId, $memberId);
        $evaluations->execute();

        $matches = $database->prepare(
            'DELETE FROM matches WHERE user_low_id = ? OR user_high_id = ?'
        );
        $matches->bind_param('ii', $memberId, $memberId);
        $matches->execute();

        $likes = $database->prepare(
            'DELETE FROM likes '
            .'WHERE sender_user_id = ? OR recipient_user_id = ?'
        );
        $likes->bind_param('ii', $memberId, $memberId);
        $likes->execute();

        $incomingLike = $database->prepare(
            'INSERT INTO likes '
            .'(sender_user_id, recipient_user_id, agent_opinion) '
            .'VALUES (?, ?, ?)'
        );
        $incomingLike->bind_param('iis', $senderId, $memberId, $agentOpinion);
        $incomingLike->execute();

        $database->commit();

        return $memberId;
    } catch (Throwable $error) {
        $database->rollback();
        throw $error;
    }
}
