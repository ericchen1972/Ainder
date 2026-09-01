<?php

declare(strict_types=1);

function ainder_validate_demo_photo(array $photo): array
{
    $errors = [];
    $imageUrl = filter_var(
        (string) ($photo['file_path'] ?? ''),
        FILTER_VALIDATE_URL
    );
    $imageHost = is_string($imageUrl)
        ? parse_url($imageUrl, PHP_URL_HOST)
        : null;
    $imageScheme = is_string($imageUrl)
        ? parse_url($imageUrl, PHP_URL_SCHEME)
        : null;

    if (($photo['source_type'] ?? null) !== 'unsplash') {
        $errors[] = 'Demo photo source must be Unsplash.';
    }
    if ($imageScheme !== 'https' || $imageHost !== 'images.unsplash.com') {
        $errors[] = 'Demo photo URL is not allowlisted.';
    }

    foreach ([
        'source_photo_id',
        'photographer_name',
        'photographer_url',
        'source_page_url',
    ] as $field) {
        if (trim((string) ($photo[$field] ?? '')) === '') {
            $errors[] = "Missing {$field}.";
        }
    }

    foreach (['photographer_url', 'source_page_url'] as $field) {
        $attributionUrl = filter_var(
            (string) ($photo[$field] ?? ''),
            FILTER_VALIDATE_URL
        );
        $host = is_string($attributionUrl)
            ? parse_url($attributionUrl, PHP_URL_HOST)
            : null;
        $scheme = is_string($attributionUrl)
            ? parse_url($attributionUrl, PHP_URL_SCHEME)
            : null;

        if ($scheme !== 'https' || $host !== 'unsplash.com') {
            $errors[] = "Invalid {$field}.";
        }
    }

    return $errors;
}

function ainder_agent_profile_is_fresh(
    array $profile,
    DateTimeImmutable $now
): bool {
    $value = trim((string) ($profile['expires_at'] ?? ''));
    if ($value === '') {
        return false;
    }

    $expiresAt = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);
    $dateErrors = DateTimeImmutable::getLastErrors();

    if (!$expiresAt
        || ($dateErrors !== false
            && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
        return false;
    }

    return $expiresAt > $now;
}

function ainder_profiles_allow_evaluation(
    array $requesterProfile,
    array $candidateProfile,
    DateTimeImmutable $now
): bool {
    return ainder_agent_profile_is_fresh($requesterProfile, $now)
        && trim((string) ($candidateProfile['profile_text'] ?? '')) !== '';
}

function ainder_public_candidate_payload(array $member, array $photos): array
{
    return [
        'id' => (int) ($member['id'] ?? 0),
        'display_name' => (string) ($member['display_name'] ?? ''),
        'birth_date' => (string) ($member['birth_date'] ?? ''),
        'gender' => (string) ($member['gender'] ?? ''),
        'basic_intro' => (string) ($member['basic_intro'] ?? ''),
        'is_demo' => (int) ($member['is_demo'] ?? 0) === 1,
        'photos' => array_values($photos),
    ];
}

function ainder_can_create_match(array $left, array $right): bool
{
    return (int) ($left['is_demo'] ?? 0) !== 1
        && (int) ($right['is_demo'] ?? 0) !== 1;
}
