<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/agent_profiles.php';

test('Profile expiry is three calendar months', function (): void {
    $generated = new DateTimeImmutable('2026-09-02 12:00:00');
    expect_same(
        '2026-12-02 12:00:00',
        ainder_profile_expiry($generated)->format('Y-m-d H:i:s')
    );
});

test('Profile gate checks self freshness and target existence only', function (): void {
    $now = new DateTimeImmutable('2026-09-02 12:00:00');
    expect_same(
        'SELF_PROFILE_MISSING',
        ainder_profile_gate(null, ['expires_at' => '2020-01-01 00:00:00'], $now)
    );
    expect_same(
        'SELF_PROFILE_EXPIRED',
        ainder_profile_gate(
            ['expires_at' => '2026-09-02 11:59:59'],
            ['expires_at' => '2030-01-01 00:00:00'],
            $now
        )
    );
    expect_same(
        'TARGET_PROFILE_MISSING',
        ainder_profile_gate(['expires_at' => '2026-12-02 12:00:00'], null, $now)
    );
    expect_same(
        null,
        ainder_profile_gate(
            ['expires_at' => '2026-12-02 12:00:00'],
            ['expires_at' => '2020-01-01 00:00:00'],
            $now
        )
    );
});
