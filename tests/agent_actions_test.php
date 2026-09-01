<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/agent_actions.php';

test('evaluation tokens are opaque and stored as hashes', function (): void {
    $token = ainder_evaluation_token();
    expect_same(64, strlen($token));
    expect_same(1, preg_match('/^[a-f0-9]{64}$/', $token));
    expect_same(64, strlen(ainder_evaluation_token_hash($token)));
});

test('match pairs have stable low and high order', function (): void {
    expect_same([4, 19], ainder_match_pair(19, 4));
    expect_same([4, 19], ainder_match_pair(4, 19));
});

test('evaluation and Like endpoints expose Profile errors', function (): void {
    $root = dirname(__DIR__);
    foreach (['evaluate.php', 'like.php'] as $file) {
        $source = file_get_contents($root.'/web/api/candidates/'.$file);
        foreach ([
            'SELF_PROFILE_MISSING',
            'SELF_PROFILE_EXPIRED',
            'TARGET_PROFILE_MISSING',
        ] as $code) {
            expect_same(true, str_contains($source, $code));
        }
    }
});
