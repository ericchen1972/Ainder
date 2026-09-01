<?php

declare(strict_types=1);

$root = dirname(__DIR__);

test('landing declares responsive hero, logo, and Google login', function () use ($root): void {
    $source = file_get_contents($root.'/web/index.php');

    foreach ([
        'ainder-hero-mobile.webp',
        'ainder-hero-desktop.webp',
        'ainder-logo-white.webp',
        'accounts.google.com/gsi/client',
        '/ainder/auth/google.php',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
});

test('placeholder pages enforce separate session states', function () use ($root): void {
    $profile = file_get_contents($root.'/web/profile/index.php');
    $app = file_get_contents($root.'/web/app/index.php');

    expect_same(true, str_contains($profile, 'ainder_pending_identity_is_valid'));
    expect_same(true, str_contains($app, 'ainder_member_id'));
});

test('web source contains no production credential', function () use ($root): void {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root.'/web')
    );

    foreach ($files as $file) {
        if (!$file->isFile()
            || !preg_match('/\.(php|css|js|html)$/', $file->getFilename())) {
            continue;
        }

        $source = file_get_contents($file->getPathname());
        expect_same(
            false,
            preg_match('/Bobo@|sk-[A-Za-z0-9]|client_secret/i', $source) === 1
        );
    }
});

test('landing has no navbar and prevents horizontal overflow', function () use ($root): void {
    $page = file_get_contents($root.'/web/index.php');
    $css = file_get_contents($root.'/web/assets/app.css');

    expect_same(false, str_contains(strtolower($page), '<nav'));
    expect_same(true, str_contains($css, 'overflow: hidden'));
});

test('migration creates only Ainder database and users table', function () use ($root): void {
    $source = file_get_contents(
        $root.'/web/migrations/001_create_ainder.php'
    );

    expect_same(
        true,
        str_contains($source, 'CREATE DATABASE IF NOT EXISTS ainder')
    );
    expect_same(
        true,
        str_contains($source, 'CREATE TABLE IF NOT EXISTS users')
    );
    expect_same(
        true,
        str_contains($source, 'UNIQUE KEY users_google_sub_unique')
    );
    expect_same(
        false,
        preg_match('/(?:INSERT|USE)\s+sweety/i', $source) === 1
    );
});
