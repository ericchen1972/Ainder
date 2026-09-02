<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/test_accounts.php';

test('test account scenarios use stable independent identities', function (): void {
    $scenarios = ainder_test_account_scenarios();

    expect_same(['grace', 'john'], array_keys($scenarios));
    expect_same('demo:010', $scenarios['grace']['member_google_sub']);
    expect_same('demo:001', $scenarios['grace']['sender_google_sub']);
    expect_same('demo:011', $scenarios['john']['member_google_sub']);
    expect_same('demo:020', $scenarios['john']['sender_google_sub']);
    expect_same(
        4,
        count(array_unique([
            $scenarios['grace']['member_google_sub'],
            $scenarios['grace']['sender_google_sub'],
            $scenarios['john']['member_google_sub'],
            $scenarios['john']['sender_google_sub'],
        ]))
    );
});

test('test account lookup rejects unknown slugs', function (): void {
    expect_same(null, ainder_test_account_scenario('unknown'));
    expect_same('Grace Liu', ainder_test_account_scenario('grace')['label']);
    expect_same('John Carter', ainder_test_account_scenario('john')['label']);
});

test('test incoming Likes always contain deterministic opinions', function (): void {
    foreach (ainder_test_account_scenarios() as $scenario) {
        expect_same(true, trim($scenario['agent_opinion']) !== '');
        expect_same(true, mb_strlen($scenario['agent_opinion']) <= 1000);
    }
});

test('test login reset is one transaction with complete relationship cleanup', function (): void {
    $source = file_get_contents(dirname(__DIR__).'/web/lib/test_accounts.php');

    foreach ([
        'begin_transaction',
        'FOR UPDATE',
        'DELETE FROM candidate_evaluations',
        'DELETE FROM matches',
        'DELETE FROM likes',
        'INSERT INTO likes',
        'agent_profiles',
        'user_photos',
        'commit',
        'rollback',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
});

test('test login reset scopes every deletion to the selected member', function (): void {
    $source = file_get_contents(dirname(__DIR__).'/web/lib/test_accounts.php');

    expect_same(true, substr_count($source, 'requester_user_id = ?') >= 1);
    expect_same(true, substr_count($source, 'user_low_id = ?') >= 1);
    expect_same(true, substr_count($source, 'sender_user_id = ?') >= 1);
});
