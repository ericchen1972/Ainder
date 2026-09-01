<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/candidates.php';

test('browse query excludes candidates already Liked by the viewer', function (): void {
    $source = file_get_contents(dirname(__DIR__).'/web/lib/candidates.php');

    foreach ([
        'int $viewerMemberId',
        'NOT EXISTS',
        'sender_user_id = ?',
        'recipient_user_id = u.id',
        'ainder_list_incoming_likes',
        'reciprocal',
        'agent_opinion',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
});

test('candidate gender is always opposite the viewer', function (): void {
    expect_same('female', ainder_candidate_gender('male'));
    expect_same('male', ainder_candidate_gender('female'));

    try {
        ainder_candidate_gender('other');
        throw new RuntimeException('Expected invalid gender rejection.');
    } catch (InvalidArgumentException) {
        expect_same(true, true);
    }
});

test('joined candidate rows become a strict public card', function (): void {
    $rows = [
        [
            'id' => 8,
            'display_name' => 'Maya Zhou',
            'birth_date' => '1994-02-07',
            'is_demo' => 1,
            'file_path' => 'https://images.unsplash.com/photo-one',
            'sort_order' => 1,
            'source_type' => 'unsplash',
            'photographer_name' => 'Alex',
            'photographer_url' => 'https://unsplash.com/@alex',
            'source_page_url' => 'https://unsplash.com/photos/one',
        ],
        [
            'id' => 8,
            'display_name' => 'Maya Zhou',
            'birth_date' => '1994-02-07',
            'is_demo' => 1,
            'file_path' => 'https://images.unsplash.com/photo-two',
            'sort_order' => 2,
            'source_type' => 'unsplash',
            'photographer_name' => 'Blair',
            'photographer_url' => 'https://unsplash.com/@blair',
            'source_page_url' => 'https://unsplash.com/photos/two',
        ],
    ];

    $cards = ainder_candidate_cards_from_rows(
        $rows,
        new DateTimeImmutable('2026-09-01 00:00:00')
    );

    expect_same(1, count($cards));
    expect_same([
        'id',
        'display_name',
        'age',
        'is_demo',
        'photos',
    ], array_keys($cards[0]));
    expect_same(32, $cards[0]['age']);
    expect_same(2, count($cards[0]['photos']));
    expect_same(false, array_key_exists('birth_date', $cards[0]));
    expect_same(false, array_key_exists('gender', $cards[0]));
    expect_same(false, array_key_exists('basic_intro', $cards[0]));
    expect_same(false, array_key_exists('profile_text', $cards[0]));
});

test('candidate grouping preserves two through six ordered photos', function (): void {
    $rows = [];
    foreach ([4, 2, 3, 1] as $order) {
        $rows[] = [
            'id' => 9,
            'display_name' => 'Emma Blake',
            'birth_date' => '1988-04-04',
            'is_demo' => 0,
            'file_path' => "/ainder/uploads/9/{$order}.webp",
            'sort_order' => $order,
            'source_type' => 'local',
            'photographer_name' => null,
            'photographer_url' => null,
            'source_page_url' => null,
        ];
    }

    $cards = ainder_candidate_cards_from_rows(
        $rows,
        new DateTimeImmutable('2026-09-01 00:00:00')
    );

    expect_same([1, 2, 3, 4], array_column($cards[0]['photos'], 'sort_order'));
});

test('malformed candidates are omitted from public cards', function (): void {
    $row = [
        'id' => 10,
        'display_name' => 'Incomplete',
        'birth_date' => '1990-01-01',
        'is_demo' => 0,
        'file_path' => '/ainder/uploads/10/1.webp',
        'sort_order' => 1,
        'source_type' => 'local',
        'photographer_name' => null,
        'photographer_url' => null,
        'source_page_url' => null,
    ];

    expect_same([], ainder_candidate_cards_from_rows(
        [$row],
        new DateTimeImmutable('2026-09-01 00:00:00')
    ));
});

test('candidate SQL uses only active status and opposite gender', function (): void {
    $source = file_get_contents(dirname(__DIR__).'/web/lib/candidates.php');

    foreach ([
        "u.status = 'active'",
        'u.gender = ?',
        'ORDER BY u.id, p.sort_order',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }

    expect_same(false, str_contains($source, 'u.id <>'));
    expect_same(false, str_contains($source, 'agent_profiles'));
});
