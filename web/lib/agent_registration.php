<?php

declare(strict_types=1);

require_once __DIR__.'/agent_profiles.php';
require_once __DIR__.'/database.php';
require_once __DIR__.'/photos.php';
require_once __DIR__.'/registration.php';
require_once __DIR__.'/signed_uploads.php';

function ainder_validate_ready_uploads(array $uploads): array
{
    $count = count($uploads);
    if ($count < 2 || $count > 6) {
        return ['PHOTO_COUNT_INVALID'];
    }
    $orders = array_map(
        static fn (array $upload): int => (int) ($upload['sort_order'] ?? 0),
        $uploads
    );
    sort($orders);
    if ($orders !== range(1, $count)) {
        return ['PHOTO_ORDER_INVALID'];
    }
    foreach ($uploads as $upload) {
        if (($upload['status'] ?? '') !== 'ready'
            || trim((string) ($upload['processed_path'] ?? '')) === '') {
            return ['PHOTO_NOT_READY'];
        }
    }

    return [];
}

function ainder_start_agent_registration(
    mysqli $database,
    string $googleSub,
    string $idempotencyKey,
    DateTimeImmutable $now
): array {
    if (!preg_match('/^[a-f0-9]{64}$/', $idempotencyKey)) {
        throw new InvalidArgumentException('IDEMPOTENCY_KEY_INVALID');
    }
    $statement = $database->prepare(
        'SELECT id, status, member_id, expires_at '
        .'FROM agent_registration_sessions '
        .'WHERE google_sub = ? AND idempotency_key = ? LIMIT 1'
    );
    $statement->bind_param('ss', $googleSub, $idempotencyKey);
    $statement->execute();
    $existing = $statement->get_result()->fetch_assoc();
    if (is_array($existing)) {
        return $existing;
    }

    $id = ainder_agent_identifier();
    $expires = $now->modify('+30 minutes')->format('Y-m-d H:i:s');
    $insert = $database->prepare(
        'INSERT INTO agent_registration_sessions '
        .'(id, google_sub, idempotency_key, expires_at) VALUES (?, ?, ?, ?)'
    );
    $insert->bind_param('ssss', $id, $googleSub, $idempotencyKey, $expires);
    $insert->execute();

    return [
        'id' => $id,
        'status' => 'active',
        'member_id' => null,
        'expires_at' => $expires,
    ];
}

function ainder_prepare_agent_upload(
    mysqli $database,
    string $registrationId,
    string $googleSub,
    int $order,
    string $mime,
    int $size,
    DateTimeImmutable $now
): array {
    if ($order < 1 || $order > 6) {
        throw new InvalidArgumentException('PHOTO_ORDER_INVALID');
    }
    if (!in_array($mime, AINDER_PROCESSABLE_IMAGE_MIMES, true)) {
        throw new InvalidArgumentException('PHOTO_TYPE_INVALID');
    }
    if ($size < 1 || $size > AINDER_MAX_PHOTO_BYTES) {
        throw new InvalidArgumentException('PHOTO_SIZE_INVALID');
    }
    $session = $database->prepare(
        'SELECT id FROM agent_registration_sessions '
        ."WHERE id = ? AND google_sub = ? AND status = 'active' "
        .'AND expires_at > ? LIMIT 1'
    );
    $nowValue = $now->format('Y-m-d H:i:s');
    $session->bind_param('sss', $registrationId, $googleSub, $nowValue);
    $session->execute();
    if ($session->get_result()->fetch_assoc() === null) {
        throw new RuntimeException('REGISTRATION_SESSION_INVALID');
    }

    $id = ainder_agent_identifier();
    $expires = $now->modify('+15 minutes')->format('Y-m-d H:i:s');
    $insert = $database->prepare(
        'INSERT INTO agent_registration_uploads '
        .'(id, registration_id, sort_order, declared_mime, declared_size, expires_at) '
        .'VALUES (?, ?, ?, ?, ?, ?)'
    );
    $insert->bind_param(
        'ssisis',
        $id,
        $registrationId,
        $order,
        $mime,
        $size,
        $expires
    );
    $insert->execute();

    return ['id' => $id, 'expires_at' => $expires];
}

function ainder_find_agent_upload(mysqli $database, string $uploadId): ?array
{
    $statement = $database->prepare(
        'SELECT u.*, s.google_sub, s.status AS registration_status '
        .'FROM agent_registration_uploads u '
        .'INNER JOIN agent_registration_sessions s ON s.id = u.registration_id '
        .'WHERE u.id = ? LIMIT 1'
    );
    $statement->bind_param('s', $uploadId);
    $statement->execute();
    $upload = $statement->get_result()->fetch_assoc();

    return is_array($upload) ? $upload : null;
}

function ainder_mark_agent_upload_ready(
    mysqli $database,
    string $uploadId,
    string $processedPath
): void {
    $statement = $database->prepare(
        "UPDATE agent_registration_uploads SET status = 'ready', processed_path = ? "
        ."WHERE id = ? AND status = 'prepared'"
    );
    $statement->bind_param('ss', $processedPath, $uploadId);
    $statement->execute();
    if ($statement->affected_rows !== 1) {
        throw new RuntimeException('UPLOAD_STATE_INVALID');
    }
}

function ainder_find_ready_agent_uploads(
    mysqli $database,
    string $registrationId
): array {
    $statement = $database->prepare(
        'SELECT id, sort_order, processed_path, status, expires_at '
        .'FROM agent_registration_uploads WHERE registration_id = ? '
        .'ORDER BY sort_order'
    );
    $statement->bind_param('s', $registrationId);
    $statement->execute();

    return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
}

function ainder_complete_agent_registration(
    mysqli $database,
    array $identity,
    array $input,
    array $profile,
    string $registrationId,
    string $idempotencyKey,
    array $uploadIds,
    string $uploadRoot,
    DateTimeImmutable $now
): int {
    $fieldErrors = ainder_validate_registration_fields($input, $now);
    $profileErrors = ainder_validate_agent_profile($profile);
    if ($fieldErrors !== [] || $profileErrors !== []) {
        throw new InvalidArgumentException('REGISTRATION_DATA_INVALID');
    }

    $finalPaths = [];
    $temporaryPaths = [];
    $database->begin_transaction();
    try {
        $session = $database->prepare(
            'SELECT status, member_id, expires_at FROM agent_registration_sessions '
            .'WHERE id = ? AND google_sub = ? AND idempotency_key = ? '
            .'FOR UPDATE'
        );
        $googleSub = (string) $identity['google_sub'];
        $session->bind_param(
            'sss',
            $registrationId,
            $googleSub,
            $idempotencyKey
        );
        $session->execute();
        $sessionRow = $session->get_result()->fetch_assoc();
        if (!is_array($sessionRow)) {
            throw new RuntimeException('REGISTRATION_SESSION_INVALID');
        }
        if ($sessionRow['status'] === 'consumed'
            && (int) $sessionRow['member_id'] > 0) {
            $database->commit();
            return (int) $sessionRow['member_id'];
        }
        if ($sessionRow['status'] !== 'active'
            || new DateTimeImmutable((string) $sessionRow['expires_at']) <= $now) {
            throw new RuntimeException('REGISTRATION_SESSION_EXPIRED');
        }

        $uploads = ainder_find_ready_agent_uploads($database, $registrationId);
        if (ainder_validate_ready_uploads($uploads) !== []
            || array_column($uploads, 'id') !== array_values($uploadIds)) {
            throw new InvalidArgumentException('PHOTO_SET_INVALID');
        }
        foreach ($uploads as $upload) {
            if (new DateTimeImmutable((string) $upload['expires_at']) <= $now) {
                throw new RuntimeException('PHOTO_UPLOAD_EXPIRED');
            }
        }

        $memberId = ainder_insert_member($database, $identity, $input);
        $directory = rtrim($uploadRoot, '/').'/'.$memberId;
        if (!is_dir($directory)
            && !mkdir($directory, 0755, true)
            && !is_dir($directory)) {
            throw new RuntimeException('Unable to create member photo directory.');
        }
        $publicPaths = [];
        foreach ($uploads as $upload) {
            $temporaryPath = (string) $upload['processed_path'];
            $finalPath = $directory.'/'.bin2hex(random_bytes(16)).'.webp';
            if (!is_file($temporaryPath) || !copy($temporaryPath, $finalPath)) {
                throw new RuntimeException('Unable to finalize Agent photo.');
            }
            $temporaryPaths[] = $temporaryPath;
            $finalPaths[] = $finalPath;
            $publicPaths[] = '/ainder/uploads/profiles/'.$memberId.'/'.basename($finalPath);
        }
        ainder_insert_member_photos($database, $memberId, $publicPaths);
        ainder_upsert_agent_profile($database, $memberId, $profile, $now);

        $consumeUploads = $database->prepare(
            "UPDATE agent_registration_uploads SET status = 'consumed' "
            .'WHERE registration_id = ?'
        );
        $consumeUploads->bind_param('s', $registrationId);
        $consumeUploads->execute();
        $consumeSession = $database->prepare(
            "UPDATE agent_registration_sessions SET status = 'consumed', member_id = ? "
            .'WHERE id = ?'
        );
        $consumeSession->bind_param('is', $memberId, $registrationId);
        $consumeSession->execute();
        $database->commit();
        ainder_cleanup_photo_paths($temporaryPaths);

        return $memberId;
    } catch (Throwable $error) {
        $database->rollback();
        ainder_cleanup_photo_paths($finalPaths);
        throw $error;
    }
}
