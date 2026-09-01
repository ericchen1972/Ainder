<?php

declare(strict_types=1);

require_once __DIR__.'/agent_profiles.php';

function ainder_evaluation_token(): string
{
    return bin2hex(random_bytes(32));
}

function ainder_evaluation_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function ainder_match_pair(int $firstUserId, int $secondUserId): array
{
    return [min($firstUserId, $secondUserId), max($firstUserId, $secondUserId)];
}

function ainder_find_action_member(mysqli $database, int $userId): ?array
{
    $statement = $database->prepare(
        'SELECT id, display_name, gender, status, is_demo '
        .'FROM users WHERE id = ? LIMIT 1'
    );
    $statement->bind_param('i', $userId);
    $statement->execute();
    $member = $statement->get_result()->fetch_assoc();

    return is_array($member) ? $member : null;
}

function ainder_require_action_members(
    mysqli $database,
    int $requesterId,
    int $candidateId
): array {
    if ($requesterId < 1 || $candidateId < 1 || $requesterId === $candidateId) {
        throw new InvalidArgumentException('CANDIDATE_INVALID');
    }
    $requester = ainder_find_action_member($database, $requesterId);
    $candidate = ainder_find_action_member($database, $candidateId);
    if (!is_array($requester)
        || !is_array($candidate)
        || $requester['status'] !== 'active'
        || $candidate['status'] !== 'active'
        || $requester['gender'] === $candidate['gender']) {
        throw new InvalidArgumentException('CANDIDATE_INVALID');
    }

    return [$requester, $candidate];
}

function ainder_require_action_profiles(
    mysqli $database,
    int $requesterId,
    int $candidateId,
    DateTimeImmutable $now
): array {
    $selfProfile = ainder_find_agent_profile($database, $requesterId);
    $targetProfile = ainder_find_agent_profile($database, $candidateId);
    $gate = ainder_profile_gate($selfProfile, $targetProfile, $now);
    if ($gate !== null) {
        throw new RuntimeException($gate);
    }

    return [$selfProfile, $targetProfile];
}

function ainder_create_candidate_evaluation(
    mysqli $database,
    int $requesterId,
    int $candidateId,
    DateTimeImmutable $now
): array {
    [, $candidate] = ainder_require_action_members(
        $database,
        $requesterId,
        $candidateId
    );
    [, $targetProfile] = ainder_require_action_profiles(
        $database,
        $requesterId,
        $candidateId,
        $now
    );
    $token = ainder_evaluation_token();
    $tokenHash = ainder_evaluation_token_hash($token);
    $expires = $now->modify('+10 minutes')->format('Y-m-d H:i:s');
    $statement = $database->prepare(
        'INSERT INTO candidate_evaluations '
        .'(token_hash, requester_user_id, candidate_user_id, expires_at) '
        .'VALUES (?, ?, ?, ?)'
    );
    $statement->bind_param(
        'siis',
        $tokenHash,
        $requesterId,
        $candidateId,
        $expires
    );
    $statement->execute();

    return [
        'candidate' => [
            'id' => (int) $candidate['id'],
            'display_name' => (string) $candidate['display_name'],
            'profile_text' => (string) $targetProfile['profile_text'],
            'agent_known_duration_days' => (int) $targetProfile['agent_known_duration_days'],
            'interaction_density' => (string) $targetProfile['interaction_density'],
        ],
        'evaluation_token' => $token,
        'expires_at' => $expires,
    ];
}

function ainder_send_agent_like(
    mysqli $database,
    int $requesterId,
    int $candidateId,
    string $token,
    DateTimeImmutable $now
): array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new InvalidArgumentException('EVALUATION_TOKEN_INVALID');
    }
    $database->begin_transaction();
    try {
        [$requester, $candidate] = ainder_require_action_members(
            $database,
            $requesterId,
            $candidateId
        );
        ainder_require_action_profiles(
            $database,
            $requesterId,
            $candidateId,
            $now
        );
        $tokenHash = ainder_evaluation_token_hash($token);
        $evaluation = $database->prepare(
            'SELECT id, expires_at, consumed_at FROM candidate_evaluations '
            .'WHERE token_hash = ? AND requester_user_id = ? '
            .'AND candidate_user_id = ? FOR UPDATE'
        );
        $evaluation->bind_param(
            'sii',
            $tokenHash,
            $requesterId,
            $candidateId
        );
        $evaluation->execute();
        $evaluationRow = $evaluation->get_result()->fetch_assoc();
        if (!is_array($evaluationRow)
            || $evaluationRow['consumed_at'] !== null
            || new DateTimeImmutable((string) $evaluationRow['expires_at']) <= $now) {
            throw new RuntimeException('EVALUATION_TOKEN_INVALID');
        }

        $like = $database->prepare(
            'INSERT INTO likes (sender_user_id, recipient_user_id) '
            .'VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        );
        $like->bind_param('ii', $requesterId, $candidateId);
        $like->execute();
        $consume = $database->prepare(
            'UPDATE candidate_evaluations SET consumed_at = ? WHERE id = ?'
        );
        $consumedAt = $now->format('Y-m-d H:i:s');
        $evaluationId = (int) $evaluationRow['id'];
        $consume->bind_param('si', $consumedAt, $evaluationId);
        $consume->execute();

        $matched = false;
        if ((int) $requester['is_demo'] !== 1
            && (int) $candidate['is_demo'] !== 1) {
            $reciprocal = $database->prepare(
                'SELECT id FROM likes WHERE sender_user_id = ? '
                .'AND recipient_user_id = ? LIMIT 1'
            );
            $reciprocal->bind_param('ii', $candidateId, $requesterId);
            $reciprocal->execute();
            if ($reciprocal->get_result()->fetch_assoc() !== null) {
                [$lowId, $highId] = ainder_match_pair($requesterId, $candidateId);
                $match = $database->prepare(
                    'INSERT INTO matches (user_low_id, user_high_id) '
                    .'VALUES (?, ?) ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
                );
                $match->bind_param('ii', $lowId, $highId);
                $match->execute();
                $matched = true;
            }
        }

        $database->commit();

        return ['liked' => true, 'matched' => $matched];
    } catch (Throwable $error) {
        $database->rollback();
        throw $error;
    }
}
