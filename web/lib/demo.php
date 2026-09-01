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
        'is_demo' => (int) ($member['is_demo'] ?? 0) === 1,
        'photos' => array_values($photos),
    ];
}

function ainder_can_create_match(array $left, array $right): bool
{
    return (int) ($left['is_demo'] ?? 0) !== 1
        && (int) ($right['is_demo'] ?? 0) !== 1;
}

function ainder_validate_demo_manifest(
    array $manifest,
    DateTimeImmutable $now
): array {
    $errors = [];
    $googleSubs = [];
    $photoIds = [];
    $cohorts = [];
    $expectedCohorts = [
        'asian_male' => 5,
        'asian_female' => 5,
        'western_male' => 5,
        'western_female' => 5,
    ];

    if (count($manifest) !== 20) {
        $errors[] = 'Demo manifest must contain exactly 20 members.';
    }

    foreach ($manifest as $index => $member) {
        $label = 'Member '.($index + 1);
        $googleSub = (string) ($member['google_sub'] ?? '');
        if (preg_match('/^demo:\d{3}$/D', $googleSub) !== 1) {
            $errors[] = "{$label} has an invalid Demo identity.";
        } elseif (isset($googleSubs[$googleSub])) {
            $errors[] = "{$label} has a duplicate Demo identity.";
        }
        $googleSubs[$googleSub] = true;

        $email = (string) ($member['email'] ?? '');
        if (preg_match('/^[A-Za-z0-9._+\-]+@ainder\.invalid$/D', $email) !== 1) {
            $errors[] = "{$label} has an invalid Demo email.";
        }

        foreach (['display_name'] as $field) {
            $value = trim((string) ($member[$field] ?? ''));
            if ($value === '' || preg_match('/^[\x20-\x7E]+$/D', $value) !== 1) {
                $errors[] = "{$label} has invalid English {$field}.";
            }
        }
        $gender = (string) ($member['gender'] ?? '');
        if (!in_array($gender, ['male', 'female'], true)) {
            $errors[] = "{$label} has an invalid gender.";
        }

        $birthDate = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            (string) ($member['birth_date'] ?? '')
        );
        $birthErrors = DateTimeImmutable::getLastErrors();
        if (!$birthDate
            || ($birthErrors !== false
                && ($birthErrors['warning_count'] > 0 || $birthErrors['error_count'] > 0))
            || $birthDate > $now) {
            $errors[] = "{$label} has an invalid birth date.";
        } else {
            $age = $birthDate->diff($now)->y;
            if ($age < 25 || $age > 55) {
                $errors[] = "{$label} age is outside 25 to 55.";
            }
        }

        if ((int) ($member['is_demo'] ?? 0) !== 1) {
            $errors[] = "{$label} is not marked Demo.";
        }

        $cohort = (string) ($member['cohort'] ?? '');
        if (!array_key_exists($cohort, $expectedCohorts)) {
            $errors[] = "{$label} has an invalid cohort.";
        } else {
            $cohorts[$cohort] = ($cohorts[$cohort] ?? 0) + 1;
        }

        $photos = is_array($member['photos'] ?? null) ? $member['photos'] : [];
        if (count($photos) !== 2) {
            $errors[] = "{$label} must have exactly two photos.";
        }
        foreach ($photos as $photo) {
            foreach (ainder_validate_demo_photo($photo) as $photoError) {
                $errors[] = "{$label}: {$photoError}";
            }
            $photoId = (string) ($photo['source_photo_id'] ?? '');
            if ($photoId !== '' && isset($photoIds[$photoId])) {
                $errors[] = "{$label} has a duplicate photo ID.";
            }
            if ($photoId !== '') {
                $photoIds[$photoId] = true;
            }
        }

        $profile = is_array($member['agent_profile'] ?? null)
            ? $member['agent_profile']
            : [];
        $profileText = trim((string) ($profile['profile_text'] ?? ''));
        if ($profileText === ''
            || preg_match('/^[\x20-\x7E]+$/D', $profileText) !== 1) {
            $errors[] = "{$label} has invalid English Agent Profile text.";
        }

        $knownDays = (int) ($profile['agent_known_duration_days'] ?? 0);
        if ($knownDays < 90 || $knownDays > 730) {
            $errors[] = "{$label} Agent known duration is invalid.";
        }
        if (!in_array(
            $profile['interaction_density'] ?? '',
            ['low', 'medium', 'high'],
            true
        )) {
            $errors[] = "{$label} interaction density is invalid.";
        }
    }

    foreach ($expectedCohorts as $cohort => $expectedCount) {
        if (($cohorts[$cohort] ?? 0) !== $expectedCount) {
            $errors[] = "Cohort {$cohort} must contain {$expectedCount} members.";
        }
    }

    return $errors;
}

function ainder_seed_demo_members(
    mysqli $database,
    array $manifest,
    DateTimeImmutable $now
): array {
    $errors = ainder_validate_demo_manifest($manifest, $now);
    if ($errors !== []) {
        throw new InvalidArgumentException('Demo manifest is invalid.');
    }

    $counts = ['users' => 0, 'photos' => 0, 'agent_profiles' => 0];
    $database->begin_transaction();

    try {
        foreach ($manifest as $member) {
            $userId = ainder_upsert_demo_user($database, $member);
            ainder_replace_demo_photos($database, $userId, $member['photos']);
            ainder_replace_demo_agent_profile(
                $database,
                $userId,
                $member['agent_profile'],
                $now
            );

            $counts['users']++;
            $counts['photos'] += count($member['photos']);
            $counts['agent_profiles']++;
        }

        $database->commit();

        return $counts;
    } catch (Throwable $error) {
        $database->rollback();
        throw $error;
    }
}

function ainder_upsert_demo_user(mysqli $database, array $member): int
{
    $googleSub = (string) ($member['google_sub'] ?? '');
    if (preg_match('/^demo:\d{3}$/D', $googleSub) !== 1
        || (int) ($member['is_demo'] ?? 0) !== 1) {
        throw new InvalidArgumentException('Invalid Demo identity.');
    }

    $existingStatement = $database->prepare(
        'SELECT id, is_demo FROM users WHERE google_sub = ? FOR UPDATE'
    );
    $existingStatement->bind_param('s', $googleSub);
    $existingStatement->execute();
    $existing = $existingStatement->get_result()->fetch_assoc();
    if (is_array($existing) && (int) $existing['is_demo'] !== 1) {
        throw new RuntimeException('Demo identity collides with a real member.');
    }

    $email = (string) $member['email'];
    $displayName = (string) $member['display_name'];
    $birthDate = (string) $member['birth_date'];
    $gender = (string) $member['gender'];
    $isDemo = 1;

    $statement = $database->prepare(
        'INSERT INTO users '
        .'(google_sub, email, display_name, birth_date, gender, is_demo) '
        .'VALUES (?, ?, ?, ?, ?, ?) '
        .'ON DUPLICATE KEY UPDATE '
        .'id = LAST_INSERT_ID(id), email = VALUES(email), '
        .'display_name = VALUES(display_name), birth_date = VALUES(birth_date), '
        .'gender = VALUES(gender), is_demo = 1, status = \'active\''
    );
    $statement->bind_param(
        'sssssi',
        $googleSub,
        $email,
        $displayName,
        $birthDate,
        $gender,
        $isDemo
    );
    $statement->execute();

    $userId = (int) $database->insert_id;
    if ($userId < 1) {
        throw new RuntimeException('Demo member upsert did not return an ID.');
    }

    return $userId;
}

function ainder_replace_demo_photos(
    mysqli $database,
    int $userId,
    array $photos
): void {
    $deleteStatement = $database->prepare(
        'DELETE FROM user_photos WHERE user_id = ?'
    );
    $deleteStatement->bind_param('i', $userId);
    $deleteStatement->execute();

    $insertStatement = $database->prepare(
        'INSERT INTO user_photos '
        .'(user_id, file_path, sort_order, source_type, source_photo_id, '
        .'photographer_name, photographer_url, source_page_url) '
        .'VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );

    foreach (array_values($photos) as $index => $photo) {
        $filePath = (string) $photo['file_path'];
        $sortOrder = $index + 1;
        $sourceType = (string) $photo['source_type'];
        $sourcePhotoId = (string) $photo['source_photo_id'];
        $photographerName = (string) $photo['photographer_name'];
        $photographerUrl = (string) $photo['photographer_url'];
        $sourcePageUrl = (string) $photo['source_page_url'];
        $insertStatement->bind_param(
            'isisssss',
            $userId,
            $filePath,
            $sortOrder,
            $sourceType,
            $sourcePhotoId,
            $photographerName,
            $photographerUrl,
            $sourcePageUrl
        );
        $insertStatement->execute();
    }
}

function ainder_replace_demo_agent_profile(
    mysqli $database,
    int $userId,
    array $profile,
    DateTimeImmutable $now
): void {
    $deleteStatement = $database->prepare(
        'DELETE FROM agent_profiles WHERE user_id = ?'
    );
    $deleteStatement->bind_param('i', $userId);
    $deleteStatement->execute();

    $profileText = (string) $profile['profile_text'];
    $knownDays = (int) $profile['agent_known_duration_days'];
    $density = (string) $profile['interaction_density'];
    $generatedAt = $now->format('Y-m-d H:i:s');
    $expiresAt = $now->modify('+3 months')->format('Y-m-d H:i:s');

    $insertStatement = $database->prepare(
        'INSERT INTO agent_profiles '
        .'(user_id, profile_text, agent_known_duration_days, '
        .'interaction_density, generated_at, expires_at) '
        .'VALUES (?, ?, ?, ?, ?, ?)'
    );
    $insertStatement->bind_param(
        'isisss',
        $userId,
        $profileText,
        $knownDays,
        $density,
        $generatedAt,
        $expiresAt
    );
    $insertStatement->execute();
}
