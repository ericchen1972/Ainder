<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/agent_registration.php';

test('Agent registration requires ordered two through six ready uploads', function (): void {
    $ready = [
        [
            'id' => 'a',
            'sort_order' => 1,
            'status' => 'ready',
            'processed_path' => '/tmp/a.webp',
        ],
        [
            'id' => 'b',
            'sort_order' => 2,
            'status' => 'ready',
            'processed_path' => '/tmp/b.webp',
        ],
    ];
    expect_same([], ainder_validate_ready_uploads($ready));
    expect_same(
        'PHOTO_COUNT_INVALID',
        ainder_validate_ready_uploads([$ready[0]])[0]
    );
    $ready[1]['sort_order'] = 1;
    expect_same(
        'PHOTO_ORDER_INVALID',
        ainder_validate_ready_uploads($ready)[0]
    );
});

test('Agent Profile payload is bounded and typed', function (): void {
    expect_same([], ainder_validate_agent_profile([
        'profile_text' => 'A thoughtful and independent person.',
        'agent_known_duration_days' => 180,
        'interaction_density' => 'high',
    ]));
    expect_same(true, count(ainder_validate_agent_profile([
        'profile_text' => '',
        'agent_known_duration_days' => -1,
        'interaction_density' => 'constant',
    ])) > 0);
});
