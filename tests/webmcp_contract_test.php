<?php

declare(strict_types=1);

$webmcpRoot = dirname(__DIR__);

test('registration page loads top-level JavaScript WebMCP tools', function () use ($webmcpRoot): void {
    $page = file_get_contents($webmcpRoot.'/web/profile/index.php');
    $tools = file_get_contents(
        $webmcpRoot.'/web/assets/webmcp-registration.js'
    );

    expect_same(true, str_contains($page, 'webmcp-registration.js'));
    expect_same(true, str_contains($page, 'ainder-csrf-token'));
    expect_same(true, str_contains($tools, 'document.modelContext'));
    foreach ([
        'start_agent_registration',
        'prepare_photo_upload',
        'submit_agent_registration',
    ] as $name) {
        expect_same(true, str_contains($tools, $name));
    }
    foreach ([
        'If sort_order is 1',
        'If sort_order is 2 through 6',
        'do not need a person or visible face',
        '720 x 1280 WebP',
        'centered 9:16 cover crop',
    ] as $policy) {
        expect_same(true, str_contains($tools, $policy));
    }
    expect_same(true, str_contains($tools, "enum: ['image/webp']"));
    expect_same(false, str_contains($tools, "enum: ['image/jpeg', 'image/png', 'image/webp']"));
    foreach ([
        'confirm the public personal data',
        'consents to creating and storing a private Agent Profile',
        'Do not show profile_text by default',
        'actually available conversation and memory',
        'conservative estimate based on the earliest retained interaction actually available',
        'based on the frequency of interactions actually available',
    ] as $policy) {
        expect_same(true, str_contains($tools, $policy));
    }
    expect_same(false, str_contains(
        $tools,
        'user has confirmed all personal data, Agent Profile text'
    ));
    expect_same(false, str_contains(
        $tools,
        'user has confirmed the complete draft'
    ));
    expect_same(false, str_contains($tools, 'openai/fileParams'));
    expect_same(false, str_contains($tools, 'registration-form'));
});

test('app page loads confirmed Profile WebMCP tool', function () use ($webmcpRoot): void {
    $page = file_get_contents($webmcpRoot.'/web/app/index.php');
    $tools = file_get_contents($webmcpRoot.'/web/assets/webmcp-app.js');

    expect_same(true, str_contains($page, 'webmcp-app.js'));
    expect_same(true, str_contains($page, 'ainder-csrf-token'));
    expect_same(true, str_contains($tools, 'upsert_my_agent_profile'));
});

test('browse tools bind evaluation and Like to the current card', function () use ($webmcpRoot): void {
    $tools = file_get_contents($webmcpRoot.'/web/assets/webmcp-app.js');
    foreach ([
        'evaluate_current_candidate',
        'send_like_to_current_candidate',
    ] as $name) {
        expect_same(true, str_contains($tools, $name));
    }
    expect_same(true, str_contains($tools, 'currentCandidateId()'));
    expect_same(true, str_contains($tools, '/api/candidates/evaluate.php'));
    expect_same(true, str_contains($tools, '/api/candidates/like.php'));
});
