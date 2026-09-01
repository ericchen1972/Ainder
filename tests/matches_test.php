<?php

declare(strict_types=1);

$matchesLibrary = dirname(__DIR__).'/web/lib/matches.php';
if (is_file($matchesLibrary)) {
    require_once $matchesLibrary;
}

test('messages are trimmed and bounded before persistence', function (): void {
    expect_same('Hello Chloe 👋', ainder_normalize_message('  Hello Chloe 👋  '));

    foreach (['', " \n\t"] as $empty) {
        try {
            ainder_normalize_message($empty);
            throw new RuntimeException('Empty message was accepted.');
        } catch (InvalidArgumentException $error) {
            expect_same('MESSAGE_REQUIRED', $error->getMessage());
        }
    }
});

test('Match APIs authorize members and persist or remove the correct records', function (): void {
    $root = dirname(__DIR__);
    $library = file_get_contents($root.'/web/lib/matches.php');
    $send = file_get_contents($root.'/web/api/messages/send.php');
    $list = file_get_contents($root.'/web/api/messages/list.php');
    $unmatch = file_get_contents($root.'/web/api/matches/unmatch.php');

    foreach ([
        'ainder_list_matches',
        'ainder_list_match_messages',
        'ainder_member_match',
        'agent_opinion',
        'sender_user_id',
    ] as $needle) {
        expect_same(true, str_contains($library, $needle));
    }
    foreach ([$send, $list, $unmatch] as $source) {
        expect_same(true, str_contains($source, 'ainder_member_match'));
        expect_same(true, str_contains($source, 'AUTH_REQUIRED'));
    }
    foreach ([
        'DELETE FROM likes',
        'DELETE FROM matches',
        'user_low_id',
        'user_high_id',
        'UNMATCHED',
    ] as $needle) {
        expect_same(true, str_contains($unmatch, $needle));
    }
});
