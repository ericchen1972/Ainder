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
