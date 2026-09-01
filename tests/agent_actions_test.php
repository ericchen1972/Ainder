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

test('Agent Like opinion is trimmed and cannot be empty', function (): void {
    expect_same(
        'Chloe sees Eric as thoughtful and direct.',
        ainder_normalize_agent_opinion(
            '  Chloe sees Eric as thoughtful and direct.  '
        )
    );

    foreach (['', " \n\t"] as $empty) {
        try {
            ainder_normalize_agent_opinion($empty);
            throw new RuntimeException('Empty opinion was accepted.');
        } catch (InvalidArgumentException $error) {
            expect_same('AGENT_OPINION_REQUIRED', $error->getMessage());
        }
    }
});

test('Like API stores opinion and pending incoming Like can be removed', function (): void {
    $root = dirname(__DIR__);
    $like = file_get_contents($root.'/web/api/candidates/like.php');
    $remove = file_get_contents($root.'/web/api/likes/remove.php');
    $actions = file_get_contents($root.'/web/lib/agent_actions.php');

    expect_same(true, str_contains($like, "body['opinion']"));
    expect_same(true, str_contains($actions, 'agent_opinion'));
    foreach ([
        'DELETE FROM likes',
        'recipient_user_id',
        'LIKE_NOT_FOUND',
        'RECIPROCAL_LIKE_EXISTS',
    ] as $needle) {
        expect_same(true, str_contains($remove, $needle));
    }
});

test('reciprocal Like returns a Match id without excluding Demo members', function (): void {
    $actions = file_get_contents(
        dirname(__DIR__).'/web/lib/agent_actions.php'
    );

    expect_same(true, str_contains($actions, "'match_id'"));
    expect_same(false, str_contains(
        $actions,
        "(int) \$requester['is_demo'] !== 1"
    ));
    expect_same(false, str_contains(
        $actions,
        "(int) \$candidate['is_demo'] !== 1"
    ));
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
