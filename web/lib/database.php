<?php

declare(strict_types=1);

function ainder_database(array $config): mysqli
{
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $database = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_password'],
        $config['db_name']
    );
    $database->set_charset('utf8mb4');

    return $database;
}

function ainder_find_member(mysqli $database, string $googleSub): ?array
{
    $statement = $database->prepare(
        'SELECT id, status FROM users WHERE google_sub = ? LIMIT 1'
    );
    $statement->bind_param('s', $googleSub);
    $statement->execute();
    $member = $statement->get_result()->fetch_assoc();

    return is_array($member) ? $member : null;
}

function ainder_record_login(mysqli $database, int $memberId): void
{
    $statement = $database->prepare(
        'UPDATE users SET last_login_at = NOW() WHERE id = ?'
    );
    $statement->bind_param('i', $memberId);
    $statement->execute();
}

function ainder_create_member_with_photos(
    mysqli $database,
    array $identity,
    array $input,
    array $photoPaths
): int {
    $googleSub = (string) $identity['google_sub'];
    $email = (string) $identity['email'];
    $displayName = trim((string) $input['display_name']);
    $birthDate = (string) $input['birth_date'];
    $gender = (string) $input['gender'];

    $database->begin_transaction();

    try {
        $userStatement = $database->prepare(
            'INSERT INTO users '
            .'(google_sub, email, display_name, birth_date, gender) '
            .'VALUES (?, ?, ?, ?, ?)'
        );
        $userStatement->bind_param(
            'sssss',
            $googleSub,
            $email,
            $displayName,
            $birthDate,
            $gender
        );
        $userStatement->execute();
        $memberId = (int) $database->insert_id;

        $photoStatement = $database->prepare(
            'INSERT INTO user_photos (user_id, file_path, sort_order) '
            .'VALUES (?, ?, ?)'
        );
        foreach ($photoPaths as $index => $path) {
            $filePath = (string) $path;
            $sortOrder = $index + 1;
            $photoStatement->bind_param(
                'isi',
                $memberId,
                $filePath,
                $sortOrder
            );
            $photoStatement->execute();
        }

        $database->commit();

        return $memberId;
    } catch (Throwable $error) {
        $database->rollback();
        throw $error;
    }
}
