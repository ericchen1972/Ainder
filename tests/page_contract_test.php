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
    expect_same(true, str_contains($source, 'ainder_home_destination'));
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

test('fourth migration creates Agent workflow tables', function () use ($root): void {
    $source = file_get_contents($root.'/web/migrations/004_add_agent_actions.php');

    foreach ([
        'agent_registration_sessions',
        'agent_registration_uploads',
        'candidate_evaluations',
        'likes',
        'matches',
        'UNIQUE KEY likes_sender_recipient_unique',
        'UNIQUE KEY matches_pair_unique',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
});

test('fifth migration adds legacy-compatible Agent opinions to Likes', function () use ($root): void {
    $source = file_get_contents(
        $root.'/web/migrations/005_add_like_opinions.php'
    );

    foreach ([
        'information_schema.COLUMNS',
        'likes',
        'agent_opinion',
        'TEXT NULL',
        'migration_token',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
});

test('signed uploads require an untracked signing key and public base URL', function () use ($root): void {
    $config = file_get_contents($root.'/web/lib/config.php');
    $example = file_get_contents($root.'/web/config.local.example.php');

    foreach (['upload_signing_key', 'public_base_url'] as $key) {
        expect_same(true, str_contains($config, $key));
        expect_same(true, str_contains($example, $key));
    }
    expect_same(false, str_contains($config, 'replace-with-64-random-hex-characters'));
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
        'browse-model.js?v=',
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

test('authenticated app provides a CSRF-protected POST sign-out', function () use ($root): void {
    $app = file_get_contents($root.'/web/app/index.php');
    $logout = file_get_contents($root.'/web/logout.php');

    expect_same(2, substr_count($app, 'action="/ainder/logout.php"'));
    expect_same(2, substr_count($app, 'name="csrf_token"'));
    expect_same(2, substr_count($app, 'method="post"'));
    expect_same(2, substr_count($app, '>Logout<'));
    expect_same(false, str_contains($app, '>登出<'));
    expect_same(true, str_contains($logout, "\$_SERVER['REQUEST_METHOD']"));
    expect_same(true, str_contains($logout, 'ainder_form_csrf_is_valid'));
    expect_same(true, str_contains($logout, "header('Location: /ainder/app/')"));
});

test('browse page exposes no visible Like control', function () use ($root): void {
    $page = file_get_contents($root.'/web/app/index.php');
    expect_same(false, str_contains($page, 'class="like-button"'));
    expect_same(false, str_contains($page, 'data-action="like"'));
    expect_same(true, str_contains($page, 'webmcp-app.js'));
});

test('browse card separates candidate drag from inside photo controls', function () use ($root): void {
    $page = file_get_contents($root.'/web/app/index.php');
    $script = file_get_contents($root.'/web/assets/browse.js');
    $style = file_get_contents($root.'/web/assets/browse.css');

    foreach ([
        'draggable="false"',
        "count(\$candidate['photos']) > 1",
        'class="photo-control photo-previous"',
        'class="photo-control photo-next"',
        'candidate-name',
        'candidate-age',
        'Drag the card to browse · Use the arrows to change photos',
    ] as $needle) {
        expect_same(true, str_contains($page, $needle));
    }

    foreach ([
        'dragstart',
        'isPhotoControlTarget',
        "addEventListener('pointerdown'",
        'updatePhotoControls',
    ] as $needle) {
        expect_same(true, str_contains($script, $needle));
    }

    foreach ([
        '-webkit-user-drag: none',
        'user-select: none',
        '.photo-control::before',
        'width: 26px',
        'height: 54px',
        '.candidate-age',
        'font-size: 18px',
    ] as $needle) {
        expect_same(true, str_contains($style, $needle));
    }

    expect_same(false, str_contains($page, 'candidate-control'));
});

test('browse assets implement gestures looping and responsive layout', function () use ($root): void {
    $script = file_get_contents($root.'/web/assets/browse.js');
    $style = file_get_contents($root.'/web/assets/browse.css');

    foreach ([
        'pointerdown',
        'pointermove',
        'pointerup',
        'ArrowLeft',
        'ArrowRight',
        'candidateStepForDrag',
        'data-current-candidate-id',
        'prefers-reduced-motion',
    ] as $needle) {
        expect_same(true, str_contains($script.$style, $needle));
    }

    foreach ([
        '.browse-sidebar',
        '.candidate-card',
        '.mobile-bar',
        '@media (max-width: 720px)',
        'overflow-x: hidden',
    ] as $needle) {
        expect_same(true, str_contains($style, $needle));
    }

    expect_same(false, str_contains($script, 'send_like_to_current_candidate'));
    expect_same(false, str_contains($script, 'like-button'));
});

test('mobile member avatar remains square and does not inherit logo sizing', function () use ($root): void {
    $page = file_get_contents($root.'/web/app/index.php');
    $style = file_get_contents($root.'/web/assets/browse.css');

    expect_same(true, str_contains($page, 'class="mobile-logo"'));
    foreach ([
        '.mobile-member-actions > img',
        'width: 34px',
        'height: 34px',
        'aspect-ratio: 1',
        'object-fit: cover',
        'flex: 0 0 34px',
    ] as $needle) {
        expect_same(true, str_contains($style, $needle));
    }
    expect_same(false, str_contains($style, '.mobile-bar img:first-child'));
});

test('mobile navigation keeps Agent Likes and Messages visible', function () use ($root): void {
    $page = file_get_contents($root.'/web/app/index.php');
    $style = file_get_contents($root.'/web/assets/browse.css');

    expect_same(true, str_contains($page, 'class="mobile-tabs"'));
    expect_same(2, substr_count($page, '>Agent Likes<'));
    expect_same(2, substr_count($page, '>Messages<'));
    foreach ([
        '.mobile-tabs',
        'height: 44px',
        'height: calc(100dvh - 102px)',
    ] as $needle) {
        expect_same(true, str_contains($style, $needle));
    }
    expect_same(true, str_contains(
        $style,
        ".mobile-bar,\n    .mobile-tabs { width: 100%; }"
    ));
});

test('desktop Agent Likes renders actionable pending Like rows', function () use ($root): void {
    $page = file_get_contents($root.'/web/app/index.php');
    $script = file_get_contents($root.'/web/assets/browse.js');
    $style = file_get_contents($root.'/web/assets/browse.css');

    foreach ([
        'ainder_list_incoming_likes',
        'agent-like-list',
        'agent-like-target',
        'agent-like-remove',
        'data-like-id',
        'data-candidate-id',
        'agent-like-age',
    ] as $needle) {
        expect_same(true, str_contains($page.$style, $needle));
    }
    foreach ([
        'showCandidate',
        '/ainder/api/likes/remove.php',
        'removeIncomingLike',
    ] as $needle) {
        expect_same(true, str_contains($script, $needle));
    }
});

test('browse diagnostic is token protected and aggregate only', function () use ($root): void {
    $source = file_get_contents($root.'/web/diagnostics/browse_status.php');

    foreach ([
        'hash_equals',
        'male_view_candidates',
        'female_view_candidates',
        'demo_female_candidates',
        'demo_male_candidates',
        'basic_intro_column_exists',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }

    foreach (['display_name', 'profile_text', 'google_sub'] as $forbidden) {
        expect_same(false, str_contains($source, $forbidden));
    }
});
