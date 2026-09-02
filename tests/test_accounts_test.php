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
