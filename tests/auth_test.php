<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/auth.php';
require_once dirname(__DIR__).'/web/lib/google.php';

test('Google CSRF requires matching non-empty tokens', function (): void {
    expect_same(true, ainder_google_csrf_is_valid('same', 'same'));
    expect_same(false, ainder_google_csrf_is_valid('', 'same'));
    expect_same(false, ainder_google_csrf_is_valid('one', 'two'));
});

test('verified Google payload is normalized', function (): void {
    expect_same([
        'google_sub' => 'google-123',
        'email' => 'eva@example.com',
        'display_name' => 'Eva',
        'avatar_url' => 'https://example.com/eva.jpg',
    ], ainder_normalize_google_identity([
        'sub' => 'google-123',
        'email' => 'eva@example.com',
        'email_verified' => true,
        'name' => 'Eva',
        'picture' => 'https://example.com/eva.jpg',
    ]));
});

test('unverified or incomplete Google payload is rejected', function (): void {
    expect_same(null, ainder_normalize_google_identity([
        'sub' => 'google-123',
        'email' => 'eva@example.com',
    ]));
    expect_same(null, ainder_normalize_google_identity([
        'sub' => '',
        'email' => 'eva@example.com',
        'email_verified' => true,
    ]));
});

test('Google verifier failures become a rejected token', function (): void {
    expect_same(true, function_exists('ainder_verify_google_token_with'));

    $payload = ainder_verify_google_token_with(
        'malformed-token',
        'client-id',
        static function (): never {
            throw new UnexpectedValueException('Wrong number of segments');
        }
    );

    expect_same(null, $payload);
});

test('only active members route to the app', function (): void {
    expect_same('/ainder/profile/', ainder_login_destination(null));
    expect_same('/ainder/profile/', ainder_login_destination(['status' => 'disabled']));
    expect_same('/ainder/app/', ainder_login_destination(['status' => 'active']));
});

test('pending identity expires at thirty minutes', function (): void {
    $session = [
        'ainder_pending_identity' => ['google_sub' => 'google-123'],
        'ainder_pending_expires_at' => 2000,
    ];

    expect_same(true, ainder_pending_identity_is_valid($session, 1999));
    expect_same(false, ainder_pending_identity_is_valid($session, 2000));
});

test('configuration fixes the database name to ainder', function (): void {
    $source = file_get_contents(dirname(__DIR__).'/web/lib/config.php');

    expect_same(true, str_contains($source, "'db_name' => 'ainder'"));
    expect_same(false, str_contains($source, "'db_name' => 'sweety'"));
});

test('new Google identity has no member insert path', function (): void {
    $source = file_get_contents(dirname(__DIR__).'/web/auth/google.php');

    expect_same(true, str_contains($source, 'ainder_pending_identity'));
    expect_same(false, preg_match('/\\bINSERT\\b/i', $source) === 1);
});

test('session cookie is isolated to the Ainder path', function (): void {
    $source = file_get_contents(dirname(__DIR__).'/web/lib/session.php');

    expect_same(true, str_contains($source, "session_name('AINDERSESSID')"));
    expect_same(true, str_contains($source, "'path' => '/ainder/'"));
    expect_same(true, str_contains($source, "'samesite' => 'Lax'"));
});
