<?php

declare(strict_types=1);

function ainder_validate_profile_name(string $name): array
{
    $trimmed = trim($name);

    return $trimmed !== '' && mb_strlen($trimmed) <= 120
        ? []
        : ['display_name' => 'Name must contain 1–120 characters.'];
}

function ainder_validate_profile_photo_changes(
    int $existingCount,
    array $slots,
    array $paths
): array {
    if ($existingCount < 2
        || $existingCount > 6
        || count($slots) !== count($paths)
    ) {
        throw new InvalidArgumentException('Invalid profile photo update.');
    }

    $changes = [];
    $nextAppend = $existingCount + 1;
    foreach ($slots as $index => $rawSlot) {
        $slot = (int) $rawSlot;
        if ($slot < 1 || $slot > 6 || isset($changes[$slot])) {
            throw new InvalidArgumentException('Invalid profile photo slot.');
        }
        if ($slot > $existingCount && $slot !== $nextAppend++) {
            throw new InvalidArgumentException(
                'New profile photos must be contiguous.'
            );
        }
        $changes[$slot] = (string) $paths[$index];
    }
    ksort($changes);

    return $changes;
}

function ainder_member_profile_photos(mysqli $database, int $memberId): array
{
    $statement = $database->prepare(
        'SELECT id, file_path, sort_order, source_type '
        .'FROM user_photos WHERE user_id = ? ORDER BY sort_order ASC'
    );
    $statement->bind_param('i', $memberId);
    $statement->execute();

    return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
}

function ainder_update_member_profile(
    mysqli $database,
    int $memberId,
    string $displayName,
    array $photoSlots,
    array $photoPaths
): array {
    $nameErrors = ainder_validate_profile_name($displayName);
    if ($nameErrors !== []) {
        throw new InvalidArgumentException($nameErrors['display_name']);
    }

    $existing = ainder_member_profile_photos($database, $memberId);
    $existingCount = count($existing);
    $changes = ainder_validate_profile_photo_changes(
        $existingCount,
        $photoSlots,
        $photoPaths
    );
    $bySlot = [];
    foreach ($existing as $photo) {
        $bySlot[(int) $photo['sort_order']] = $photo;
    }

    $supersededLocalPaths = [];
    $database->begin_transaction();
    try {
        $trimmedName = trim($displayName);
        $userStatement = $database->prepare(
            'UPDATE users SET display_name = ? WHERE id = ? AND status = \'active\''
        );
        $userStatement->bind_param('si', $trimmedName, $memberId);
        $userStatement->execute();
        if ($userStatement->affected_rows < 1) {
            $check = $database->prepare(
                'SELECT id FROM users WHERE id = ? AND status = \'active\' LIMIT 1'
            );
            $check->bind_param('i', $memberId);
            $check->execute();
            if (!is_array($check->get_result()->fetch_assoc())) {
                throw new RuntimeException('Member is unavailable.');
            }
        }

        $replace = $database->prepare(
            'UPDATE user_photos SET file_path = ?, source_type = \'local\', '
            .'source_photo_id = NULL, photographer_name = NULL, '
            .'photographer_url = NULL, source_page_url = NULL '
            .'WHERE user_id = ? AND sort_order = ?'
        );
        $insert = $database->prepare(
            'INSERT INTO user_photos '
            .'(user_id, file_path, sort_order, source_type) '
            .'VALUES (?, ?, ?, \'local\')'
        );

        foreach ($changes as $slot => $path) {
            if (isset($bySlot[$slot])) {
                $replace->bind_param('sii', $path, $memberId, $slot);
                $replace->execute();
                if (($bySlot[$slot]['source_type'] ?? '') === 'local') {
                    $supersededLocalPaths[] = (string) $bySlot[$slot]['file_path'];
                }
                $bySlot[$slot]['file_path'] = $path;
                $bySlot[$slot]['source_type'] = 'local';
                continue;
            }

            $insert->bind_param('isi', $memberId, $path, $slot);
            $insert->execute();
            $bySlot[$slot] = [
                'file_path' => $path,
                'sort_order' => $slot,
                'source_type' => 'local',
            ];
        }

        $database->commit();
    } catch (Throwable $error) {
        $database->rollback();
        throw $error;
    }

    ksort($bySlot);

    return [
        'display_name' => trim($displayName),
        'photos' => array_values(array_map(
            static fn (array $photo): string => (string) $photo['file_path'],
            $bySlot
        )),
        'superseded_local_paths' => $supersededLocalPaths,
    ];
}
