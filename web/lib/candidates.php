<?php

declare(strict_types=1);

function ainder_candidate_gender(string $viewerGender): string
{
    return match ($viewerGender) {
        'male' => 'female',
        'female' => 'male',
        default => throw new InvalidArgumentException('Invalid member gender.'),
    };
}

function ainder_find_browse_member(mysqli $database, int $memberId): ?array
{
    $statement = $database->prepare(
        'SELECT u.id, u.display_name, u.gender, u.status, '
        .'p.file_path AS avatar_path '
        .'FROM users u LEFT JOIN user_photos p '
        .'ON p.user_id = u.id AND p.sort_order = 1 '
        .'WHERE u.id = ? LIMIT 1'
    );
    $statement->bind_param('i', $memberId);
    $statement->execute();
    $member = $statement->get_result()->fetch_assoc();

    return is_array($member) ? $member : null;
}

function ainder_candidate_cards_from_rows(
    array $rows,
    DateTimeImmutable $now
): array {
    $grouped = [];

    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            continue;
        }

        if (!isset($grouped[$id])) {
            $birthDate = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                (string) ($row['birth_date'] ?? '')
            );
            $dateErrors = DateTimeImmutable::getLastErrors();
            $validDate = $birthDate
                && ($dateErrors === false
                    || ($dateErrors['warning_count'] === 0
                        && $dateErrors['error_count'] === 0));

            $grouped[$id] = [
                'id' => $id,
                'display_name' => trim((string) ($row['display_name'] ?? '')),
                'age' => $validDate ? $birthDate->diff($now)->y : 0,
                'is_demo' => (int) ($row['is_demo'] ?? 0) === 1,
                'photos' => [],
            ];
        }

        $path = trim((string) ($row['file_path'] ?? ''));
        $sortOrder = (int) ($row['sort_order'] ?? 0);
        if ($path === '' || $sortOrder < 1) {
            continue;
        }

        $grouped[$id]['photos'][] = [
            'file_path' => $path,
            'sort_order' => $sortOrder,
            'source_type' => (string) ($row['source_type'] ?? 'local'),
            'photographer_name' => (string) ($row['photographer_name'] ?? ''),
            'photographer_url' => (string) ($row['photographer_url'] ?? ''),
            'source_page_url' => (string) ($row['source_page_url'] ?? ''),
        ];
    }

    $cards = [];
    foreach ($grouped as $card) {
        usort(
            $card['photos'],
            static fn (array $left, array $right): int =>
                $left['sort_order'] <=> $right['sort_order']
        );

        if ($card['display_name'] === ''
            || $card['age'] < 18
            || count($card['photos']) < 2
            || count($card['photos']) > 6) {
            continue;
        }

        $cards[] = $card;
    }

    return $cards;
}

function ainder_list_browse_candidates(
    mysqli $database,
    string $viewerGender,
    DateTimeImmutable $now
): array {
    $candidateGender = ainder_candidate_gender($viewerGender);
    $statement = $database->prepare(
        'SELECT u.id, u.display_name, u.birth_date, u.is_demo, '
        .'p.file_path, p.sort_order, p.source_type, p.photographer_name, '
        .'p.photographer_url, p.source_page_url '
        .'FROM users u INNER JOIN user_photos p ON p.user_id = u.id '
        ."WHERE u.status = 'active' AND u.gender = ? "
        .'ORDER BY u.id, p.sort_order'
    );
    $statement->bind_param('s', $candidateGender);
    $statement->execute();
    $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $cards = ainder_candidate_cards_from_rows($rows, $now);
    shuffle($cards);

    return $cards;
}
