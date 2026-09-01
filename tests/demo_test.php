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

test('matchmaking evaluation requires fresh profiles for both people', function (): void {
    $now = new DateTimeImmutable('2026-09-01 00:00:00');
    $fresh = ['expires_at' => '2026-12-01 00:00:00'];
    $stale = ['expires_at' => '2026-09-01 00:00:00'];

    expect_same(true, ainder_profiles_allow_evaluation($fresh, $fresh, $now));
    expect_same(false, ainder_profiles_allow_evaluation($fresh, [], $now));
    expect_same(false, ainder_profiles_allow_evaluation($fresh, $stale, $now));
});

test('public candidates exclude Agent Profile fields', function (): void {
    $public = ainder_public_candidate_payload([
        'id' => 7,
        'display_name' => 'Emma Blake',
        'birth_date' => '1998-03-11',
        'gender' => 'female',
        'basic_intro' => 'Architect in Taipei. Books and quiet cafes.',
        'is_demo' => 1,
        'profile_text' => 'Private Agent observation',
        'agent_known_duration_days' => 400,
        'interaction_density' => 'high',
    ], [['file_path' => 'https://images.unsplash.com/photo-123']]);

    expect_same(false, array_key_exists('profile_text', $public));
    expect_same(false, array_key_exists('agent_known_duration_days', $public));
    expect_same(false, array_key_exists('interaction_density', $public));
    expect_same(true, $public['is_demo']);
    expect_same(1, count($public['photos']));
});

test('a Match cannot contain a Demo member', function (): void {
    expect_same(false, ainder_can_create_match(['is_demo' => 0], ['is_demo' => 1]));
    expect_same(false, ainder_can_create_match(['is_demo' => 1], ['is_demo' => 0]));
    expect_same(false, ainder_can_create_match(['is_demo' => 1], ['is_demo' => 1]));
    expect_same(true, ainder_can_create_match(['is_demo' => 0], ['is_demo' => 0]));
});
