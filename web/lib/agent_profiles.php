<?php

declare(strict_types=1);

function ainder_profile_expiry(DateTimeImmutable $generatedAt): DateTimeImmutable
{
    return $generatedAt->modify('+3 months');
}

function ainder_validate_agent_profile(array $profile): array
{
    $errors = [];
    $text = trim((string) ($profile['profile_text'] ?? ''));
    $duration = filter_var(
        $profile['agent_known_duration_days'] ?? null,
        FILTER_VALIDATE_INT
    );
    if ($text === '' || mb_strlen($text) > 4000) {
        $errors[] = 'PROFILE_TEXT_INVALID';
    }
    if ($duration === false || $duration < 0 || $duration > 65535) {
        $errors[] = 'PROFILE_DURATION_INVALID';
    }
    if (!in_array(
        $profile['interaction_density'] ?? '',
        ['low', 'medium', 'high'],
        true
    )) {
        $errors[] = 'PROFILE_DENSITY_INVALID';
    }

    return $errors;
}

function ainder_profile_gate(
    ?array $selfProfile,
    ?array $targetProfile,
    DateTimeImmutable $now
): ?string {
    if ($selfProfile === null) {
        return 'SELF_PROFILE_MISSING';
    }
    if (new DateTimeImmutable((string) $selfProfile['expires_at']) <= $now) {
        return 'SELF_PROFILE_EXPIRED';
    }

    return $targetProfile === null ? 'TARGET_PROFILE_MISSING' : null;
}

function ainder_find_agent_profile(mysqli $database, int $userId): ?array
{
    $statement = $database->prepare(
        'SELECT user_id, profile_text, agent_known_duration_days, '
        .'interaction_density, generated_at, expires_at '
        .'FROM agent_profiles WHERE user_id = ? LIMIT 1'
    );
    $statement->bind_param('i', $userId);
    $statement->execute();
    $profile = $statement->get_result()->fetch_assoc();

    return is_array($profile) ? $profile : null;
}

function ainder_upsert_agent_profile(
    mysqli $database,
    int $userId,
    array $profile,
    DateTimeImmutable $generatedAt
): array {
    $errors = ainder_validate_agent_profile($profile);
    if ($errors !== []) {
        throw new InvalidArgumentException($errors[0]);
    }

    $text = trim((string) $profile['profile_text']);
    $duration = (int) $profile['agent_known_duration_days'];
    $density = (string) $profile['interaction_density'];
    $generated = $generatedAt->format('Y-m-d H:i:s');
    $expires = ainder_profile_expiry($generatedAt)->format('Y-m-d H:i:s');
    $statement = $database->prepare(
        'INSERT INTO agent_profiles '
        .'(user_id, profile_text, agent_known_duration_days, '
        .'interaction_density, generated_at, expires_at) '
        .'VALUES (?, ?, ?, ?, ?, ?) '
        .'ON DUPLICATE KEY UPDATE profile_text = VALUES(profile_text), '
        .'agent_known_duration_days = VALUES(agent_known_duration_days), '
        .'interaction_density = VALUES(interaction_density), '
        .'generated_at = VALUES(generated_at), expires_at = VALUES(expires_at)'
    );
    $statement->bind_param(
        'isisss',
        $userId,
        $text,
        $duration,
        $density,
        $generated,
        $expires
    );
    $statement->execute();

    return ['generated_at' => $generated, 'expires_at' => $expires];
}
