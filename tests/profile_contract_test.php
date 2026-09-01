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

test('profile page leads with Agent message and manual action', function () use ($profileRoot): void {
    $source = file_get_contents($profileRoot.'/web/profile/index.php');

    expect_same(true, str_contains($source, '你可以讓 Agent 為你填寫個人資訊'));
    expect_same(true, str_contains($source, '手動填寫'));
    expect_same(true, str_contains($source, 'aria-expanded'));
});

test('profile assets use file versions to avoid stale browser caches', function () use ($profileRoot): void {
    $source = file_get_contents($profileRoot.'/web/profile/index.php');

    expect_same(true, str_contains($source, 'app.css?v='));
    expect_same(true, str_contains($source, 'profile.js?v='));
    expect_same(true, str_contains($source, 'filemtime'));
});

test('profile form contains only approved personal fields', function () use ($profileRoot): void {
    $source = file_get_contents($profileRoot.'/web/profile/index.php');

    foreach (['display_name', 'birth_date', 'gender', 'photos[]'] as $field) {
        expect_same(true, str_contains($source, $field));
    }
    foreach ([
        'basic_intro',
        '工作、居住地等短文字介紹（50字內）',
        '有興趣的對象',
        '我想尋找',
        '是否在個人資料顯示性別',
    ] as $excluded) {
        expect_same(false, str_contains($source, $excluded));
    }
});

test('photo script enforces two to six selected files', function () use ($profileRoot): void {
    $source = file_get_contents($profileRoot.'/web/assets/profile.js');

    expect_same(true, str_contains($source, 'selectedFiles.length >= 2'));
    expect_same(true, str_contains($source, 'selectedFiles.length <= 6'));
    expect_same(true, str_contains($source, 'DataTransfer'));
    expect_same(true, str_contains($source, 'URL.revokeObjectURL'));
});

test('manual and Agent uploads share the image processor', function () use ($profileRoot): void {
    $photos = file_get_contents($profileRoot.'/web/lib/photos.php');
    $register = file_get_contents($profileRoot.'/web/profile/register.php');

    expect_same(true, str_contains($photos, 'ainder_process_image'));
    expect_same(
        true,
        str_contains(
            $register,
            "require_once dirname(__DIR__).'/lib/image_processor.php'"
        )
    );
    expect_same(true, str_contains($photos, "'.webp'"));
});
