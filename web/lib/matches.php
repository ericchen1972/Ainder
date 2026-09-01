<?php

declare(strict_types=1);

function ainder_normalize_message(string $message): string
{
    $normalized = trim($message);
    if ($normalized === '') {
        throw new InvalidArgumentException('MESSAGE_REQUIRED');
    }
    if (mb_strlen($normalized) > 2000) {
        throw new InvalidArgumentException('MESSAGE_INVALID');
    }

    return $normalized;
}

function ainder_member_match(
    mysqli $database,
    int $memberId,
    int $matchId
): ?array {
    $statement = $database->prepare(
        'SELECT id, user_low_id, user_high_id FROM matches '
        .'WHERE id = ? AND (user_low_id = ? OR user_high_id = ?) LIMIT 1'
    );
    $statement->bind_param('iii', $matchId, $memberId, $memberId);
    $statement->execute();
    $match = $statement->get_result()->fetch_assoc();

    return is_array($match) ? $match : null;
}

function ainder_list_matches(
    mysqli $database,
    int $memberId,
    DateTimeImmutable $now
): array {
    $statement = $database->prepare(
        'SELECT m.id AS match_id, u.id AS candidate_id, u.display_name, '
        .'u.birth_date, p.file_path AS photo_path, '
        .'own_like.agent_opinion '
        .'FROM matches m INNER JOIN users u ON u.id = '
        .'CASE WHEN m.user_low_id = ? THEN m.user_high_id ELSE m.user_low_id END '
        .'INNER JOIN user_photos p ON p.user_id = u.id AND p.sort_order = 1 '
        .'INNER JOIN likes own_like ON own_like.sender_user_id = ? '
        .'AND own_like.recipient_user_id = u.id '
        .'WHERE m.user_low_id = ? OR m.user_high_id = ? '
        .'ORDER BY m.created_at DESC, m.id DESC'
    );
    $statement->bind_param('iiii', $memberId, $memberId, $memberId, $memberId);
    $statement->execute();

    $matches = [];
    foreach ($statement->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $birthDate = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            (string) ($row['birth_date'] ?? '')
        );
        $age = $birthDate ? $birthDate->diff($now)->y : 0;
        $name = trim((string) ($row['display_name'] ?? ''));
        $photo = trim((string) ($row['photo_path'] ?? ''));
        $opinion = trim((string) ($row['agent_opinion'] ?? ''));
        if ($name === '' || $age < 18 || $photo === '' || $opinion === '') {
            continue;
        }
        $matches[] = [
            'match_id' => (int) $row['match_id'],
            'candidate_id' => (int) $row['candidate_id'],
            'display_name' => $name,
            'age' => $age,
            'photo_path' => $photo,
            'agent_opinion' => $opinion,
        ];
    }

    return $matches;
}

function ainder_list_match_messages(
    mysqli $database,
    int $memberId,
    int $matchId
): array {
    if (ainder_member_match($database, $memberId, $matchId) === null) {
        throw new RuntimeException('MATCH_NOT_FOUND');
    }
    $statement = $database->prepare(
        'SELECT id, match_id, sender_user_id, body, created_at '
        .'FROM messages WHERE match_id = ? ORDER BY created_at, id LIMIT 200'
    );
    $statement->bind_param('i', $matchId);
    $statement->execute();

    return array_map(
        static fn (array $row): array => [
            'id' => (int) $row['id'],
            'match_id' => (int) $row['match_id'],
            'sender_user_id' => (int) $row['sender_user_id'],
            'body' => (string) $row['body'],
            'created_at' => (string) $row['created_at'],
            'is_mine' => (int) $row['sender_user_id'] === $memberId,
        ],
        $statement->get_result()->fetch_all(MYSQLI_ASSOC)
    );
}

function ainder_send_match_message(
    mysqli $database,
    int $memberId,
    int $matchId,
    string $message
): array {
    if (ainder_member_match($database, $memberId, $matchId) === null) {
        throw new RuntimeException('MATCH_NOT_FOUND');
    }
    $body = ainder_normalize_message($message);
    $statement = $database->prepare(
        'INSERT INTO messages (match_id, sender_user_id, body) VALUES (?, ?, ?)'
    );
    $statement->bind_param('iis', $matchId, $memberId, $body);
    $statement->execute();

    return [
        'id' => (int) $database->insert_id,
        'match_id' => $matchId,
        'sender_user_id' => $memberId,
        'body' => $body,
        'is_mine' => true,
    ];
}
