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

test('Agent endpoints separate WebMCP JSON from signed image bytes', function (): void {
    $root = dirname(__DIR__);
    $start = file_get_contents(
        $root.'/web/api/agent-registration/start.php'
    );
    $prepare = file_get_contents(
        $root.'/web/api/agent-registration/prepare-photo.php'
    );
    $upload = file_get_contents(
        $root.'/web/api/agent-registration/upload.php'
    );
    $submit = file_get_contents(
        $root.'/web/api/agent-registration/submit.php'
    );

    expect_same(true, str_contains($start, 'ainder_pending_identity_is_valid'));
    expect_same(true, str_contains($prepare, 'ainder_signed_upload_url'));
    expect_same(true, str_contains($upload, "fopen('php://input'"));
    expect_same(true, str_contains($upload, 'ainder_verify_upload_signature'));
    expect_same(true, str_contains($upload, 'ainder_process_image'));
    expect_same(true, str_contains($submit, 'ainder_complete_agent_registration'));
    expect_same(false, str_contains($submit, '$_FILES'));
});
