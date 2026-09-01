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

    expect_same(true, str_contains($source, 'Sign in with Google'));
    expect_same(true, str_contains($source, 'aria-disabled="true"'));
});

test('landing stylesheet uses its file version', function () use ($root): void {
    $source = file_get_contents($root.'/web/index.php');

    expect_same(true, str_contains($source, 'app.css?v='));
    expect_same(true, str_contains($source, 'filemtime'));
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
            preg_match('/Bobo@|sk-[A-Za-z0-9]{20,}|client_secret/i', $source) === 1
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
    expect_same(true, str_contains($source, 'birth_date DATE NOT NULL'));
    expect_same(true, str_contains($source, "gender ENUM('male', 'female')"));
    expect_same(
        true,
        str_contains($source, 'CREATE TABLE IF NOT EXISTS user_photos')
    );
    expect_same(
        true,
        str_contains($source, 'UNIQUE KEY user_photos_user_sort_unique')
    );
    expect_same(
        true,
        str_contains($source, 'FOREIGN KEY (user_id) REFERENCES users (id)')
    );
    expect_same(
        false,
        preg_match('/(?:INSERT|USE)\s+sweety/i', $source) === 1
    );
});

test('member repository creates user and ordered photos in a transaction', function () use ($root): void {
    $source = file_get_contents($root.'/web/lib/database.php');

    expect_same(true, str_contains($source, 'ainder_create_member_with_photos'));
    expect_same(true, str_contains($source, 'begin_transaction'));
    expect_same(true, str_contains($source, 'INSERT INTO user_photos'));
    expect_same(true, str_contains($source, 'sort_order'));
    expect_same(true, str_contains($source, 'rollback'));
});

test('second migration adds demo photo sources and private Agent Profiles', function () use ($root): void {
    $source = file_get_contents($root.'/web/migrations/002_add_demo_members.php');

    foreach ([
        'is_demo TINYINT(1)',
        "source_type ENUM('local', 'unsplash')",
        'source_photo_id VARCHAR(64)',
        'photographer_name VARCHAR(160)',
        'photographer_url VARCHAR(500)',
        'source_page_url VARCHAR(500)',
        'CREATE TABLE IF NOT EXISTS agent_profiles',
        'agent_known_duration_days',
        "interaction_density ENUM('low', 'medium', 'high')",
        'UNIQUE KEY agent_profiles_user_unique',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
});

test('Demo production diagnostic exposes only aggregate validation data', function () use ($root): void {
    $source = file_get_contents(
        $root.'/web/diagnostics/demo_seed_status.php'
    );

    foreach ([
        'hash_equals',
        'demo_users',
        'demo_photos',
        'demo_agent_profiles',
        'members_with_two_photos',
        'fresh_profiles',
        'non_unsplash_demo_photo_count',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }

    foreach (['profile_text', 'google_sub', 'unsplash_access_key'] as $forbidden) {
        expect_same(false, str_contains($source, $forbidden));
    }
});

test('third migration and current runtime remove basic info completely', function () use ($root): void {
    $migration = file_get_contents(
        $root.'/web/migrations/003_remove_basic_intro.php'
    );
    expect_same(true, str_contains($migration, 'DROP COLUMN basic_intro'));
    expect_same(true, str_contains($migration, 'information_schema.COLUMNS'));

    foreach ([
        'web/lib/registration.php',
        'web/lib/database.php',
        'web/profile/index.php',
        'web/profile/register.php',
        'web/lib/demo.php',
        'web/seeds/demo_members.php',
        'web/diagnostics/demo_seed_status.php',
    ] as $relativePath) {
        $source = file_get_contents($root.'/'.$relativePath);
        expect_same(false, str_contains($source, 'basic_intro'));
    }
});

test('authenticated app renders the approved public browse surface', function () use ($root): void {
    $source = file_get_contents($root.'/web/app/index.php');

    foreach ([
        'ainder_member_id',
        'ainder_find_browse_member',
        'ainder_list_browse_candidates',
        'candidate-browser',
        'Agent Likes',
        'Messages',
        'data-candidate-id',
        'data-current-candidate-id',
        'browse.css?v=',
        'browse-model.mjs?v=',
        'browse.js?v=',
        'aria-live',
        'Photo by',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }

    foreach ([
        'profile_text',
        'basic_intro',
        'agent_known_duration_days',
        'interaction_density',
        'compatibility',
        'like_candidate',
        'LIKE',
    ] as $forbidden) {
        expect_same(false, str_contains($source, $forbidden));
    }
});
