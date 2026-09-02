<?php

declare(strict_types=1);

$demoLibrary = dirname(__DIR__).'/web/lib/demo.php';
if (is_file($demoLibrary)) {
    require_once $demoLibrary;
}

test('Unsplash photos require the exact CDN host and attribution', function (): void {
    $valid = [
        'source_type' => 'unsplash',
        'file_path' => 'https://images.unsplash.com/photo-123?auto=format&w=900',
        'source_photo_id' => 'photo-123',
        'photographer_name' => 'Alex Example',
        'photographer_url' => 'https://unsplash.com/@alex?utm_source=ainder&utm_medium=referral',
        'source_page_url' => 'https://unsplash.com/photos/photo-123?utm_source=ainder&utm_medium=referral',
    ];

    expect_same([], ainder_validate_demo_photo($valid));

    $valid['file_path'] = 'https://example.com/photo.jpg';
    expect_same(true, ainder_validate_demo_photo($valid) !== []);

    $valid['file_path'] = 'https://images.unsplash.com/photo-123';
    $valid['photographer_url'] = 'https://example.com/@alex';
    expect_same(true, ainder_validate_demo_photo($valid) !== []);
});

test('Agent Profiles expire at their explicit expiry time', function (): void {
    $profile = ['expires_at' => '2026-12-01 00:00:00'];

    expect_same(true, ainder_agent_profile_is_fresh(
        $profile,
        new DateTimeImmutable('2026-11-30 23:59:59')
    ));
    expect_same(false, ainder_agent_profile_is_fresh(
        $profile,
        new DateTimeImmutable('2026-12-01 00:00:00')
    ));
    expect_same(false, ainder_agent_profile_is_fresh(
        [],
        new DateTimeImmutable('2026-09-01 00:00:00')
    ));
});

test('matchmaking evaluation checks only the requester profile date', function (): void {
    $now = new DateTimeImmutable('2026-09-01 00:00:00');
    $requesterFresh = [
        'profile_text' => 'Requester profile',
        'expires_at' => '2026-12-01 00:00:00',
    ];
    $requesterStale = [
        'profile_text' => 'Requester profile',
        'expires_at' => '2026-09-01 00:00:00',
    ];
    $candidateStale = [
        'profile_text' => 'Candidate profile',
        'expires_at' => '2026-01-01 00:00:00',
    ];

    expect_same(true, ainder_profiles_allow_evaluation(
        $requesterFresh,
        $candidateStale,
        $now
    ));
    expect_same(false, ainder_profiles_allow_evaluation($requesterFresh, [], $now));
    expect_same(false, ainder_profiles_allow_evaluation(
        $requesterStale,
        $candidateStale,
        $now
    ));
});

test('public candidates exclude Agent Profile fields', function (): void {
    $public = ainder_public_candidate_payload([
        'id' => 7,
        'display_name' => 'Emma Blake',
        'birth_date' => '1998-03-11',
        'gender' => 'female',
        'is_demo' => 1,
        'profile_text' => 'Private Agent observation',
        'agent_known_duration_days' => 400,
        'interaction_density' => 'high',
    ], [['file_path' => 'https://images.unsplash.com/photo-123']]);

    expect_same(false, array_key_exists('profile_text', $public));
    expect_same(false, array_key_exists('agent_known_duration_days', $public));
    expect_same(false, array_key_exists('interaction_density', $public));
    expect_same(false, array_key_exists('basic_intro', $public));
    expect_same(true, $public['is_demo']);
    expect_same(1, count($public['photos']));
});

test('a Match cannot contain a Demo member', function (): void {
    expect_same(false, ainder_can_create_match(['is_demo' => 0], ['is_demo' => 1]));
    expect_same(false, ainder_can_create_match(['is_demo' => 1], ['is_demo' => 0]));
    expect_same(false, ainder_can_create_match(['is_demo' => 1], ['is_demo' => 1]));
    expect_same(true, ainder_can_create_match(['is_demo' => 0], ['is_demo' => 0]));
});

test('Demo manifest validator rejects an incomplete cohort', function (): void {
    $errors = ainder_validate_demo_manifest(
        [],
        new DateTimeImmutable('2026-09-01 00:00:00')
    );

    expect_same(true, $errors !== []);
});

test('test login members keep deterministic Demo identities', function (): void {
    $manifest = require dirname(__DIR__).'/web/seeds/demo_members.php';
    $bySub = [];
    foreach ($manifest as $member) {
        $bySub[$member['google_sub']] = $member;
    }

    expect_same('Grace Liu', $bySub['demo:010']['display_name']);
    expect_same('John Carter', $bySub['demo:011']['display_name']);
    expect_same(
        true,
        str_starts_with(
            $bySub['demo:011']['agent_profile']['profile_text'],
            'John '
        )
    );
    expect_same('Ethan Park', $bySub['demo:001']['display_name']);
    expect_same('Evelyn Grant', $bySub['demo:020']['display_name']);
});

test('frozen Demo manifest contains the exact approved cohort', function (): void {
    $manifestPath = dirname(__DIR__).'/web/seeds/demo_members.php';
    $ledgerPath = dirname(__DIR__).'/web/seeds/demo_photo_tracking.php';

    expect_same(true, is_file($manifestPath));
    expect_same(true, is_file($ledgerPath));

    $manifest = require $manifestPath;
    $trackedPhotoIds = require $ledgerPath;
    $errors = ainder_validate_demo_manifest(
        $manifest,
        new DateTimeImmutable('2026-09-01 00:00:00')
    );

    expect_same([], $errors);
    expect_same(20, count($manifest));

    $cohorts = array_count_values(array_column($manifest, 'cohort'));
    expect_same([
        'asian_male' => 5,
        'asian_female' => 5,
        'western_male' => 5,
        'western_female' => 5,
    ], $cohorts);

    $manifestPhotoIds = [];
    foreach ($manifest as $member) {
        expect_same(2, count($member['photos']));
        expect_same(false, array_key_exists('basic_intro', $member));
        expect_same(true, $member['is_demo']);

        foreach ($member['photos'] as $photo) {
            $manifestPhotoIds[] = $photo['source_photo_id'];
        }
    }

    sort($manifestPhotoIds);
    sort($trackedPhotoIds);
    expect_same(40, count(array_unique($trackedPhotoIds)));
    expect_same($manifestPhotoIds, $trackedPhotoIds);
});

test('Demo seed endpoint is token protected and transactional', function (): void {
    $endpoint = file_get_contents(
        dirname(__DIR__).'/web/seeds/run_demo_members.php'
    );
    $library = file_get_contents(dirname(__DIR__).'/web/lib/demo.php');
    $source = $endpoint.$library;

    foreach ([
        'hash_equals',
        'demo_members.php',
        'begin_transaction',
        'ON DUPLICATE KEY UPDATE',
        'DELETE FROM user_photos',
        'DELETE FROM agent_profiles',
        'INSERT INTO agent_profiles',
        'rollback',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
});

test('inbound demo Like seed is protected and preserves one-way state', function (): void {
    $source = file_get_contents(
        dirname(__DIR__).'/web/seeds/run_demo_inbound_like.php'
    );

    foreach ([
        'migration_token',
        'hash_equals',
        'sender_display_name',
        'recipient_display_name',
        'agent_opinion',
        'RECIPROCAL_LIKE_EXISTS',
        'is_demo',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
});
