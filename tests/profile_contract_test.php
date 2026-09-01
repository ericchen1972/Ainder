<?php

declare(strict_types=1);

$profileRoot = dirname(__DIR__);

test('registration endpoint requires pending identity and form CSRF', function () use ($profileRoot): void {
    $source = file_get_contents($profileRoot.'/web/profile/register.php');

    expect_same(true, str_contains($source, 'ainder_pending_identity_is_valid'));
    expect_same(true, str_contains($source, 'ainder_form_csrf_is_valid'));
});

test('registration endpoint never accepts email or Google sub from post', function () use ($profileRoot): void {
    $source = file_get_contents($profileRoot.'/web/profile/register.php');

    expect_same(false, preg_match('/_POST[^;]*(email|google_sub)/i', $source) === 1);
});

test('registration failure cleans staged and final files', function () use ($profileRoot): void {
    $source = file_get_contents($profileRoot.'/web/profile/register.php');

    expect_same(true, str_contains($source, 'ainder_cleanup_photo_paths'));
    expect_same(true, str_contains($source, 'catch (Throwable'));
});

test('session helpers provide CSRF and one-request flash state', function () use ($profileRoot): void {
    $source = file_get_contents($profileRoot.'/web/lib/session.php');

    expect_same(true, str_contains($source, 'ainder_form_csrf_token'));
    expect_same(true, str_contains($source, 'ainder_form_csrf_is_valid'));
    expect_same(true, str_contains($source, 'ainder_set_form_flash'));
    expect_same(true, str_contains($source, 'ainder_pull_form_flash'));
});
