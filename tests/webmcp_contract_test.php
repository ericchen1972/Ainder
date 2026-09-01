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
    expect_same(false, str_contains($tools, 'openai/fileParams'));
    expect_same(false, str_contains($tools, 'registration-form'));
});
