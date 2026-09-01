<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/photos.php';

test('two through six photos are accepted', function (): void {
    expect_same([], ainder_validate_photo_count([
        ['error' => UPLOAD_ERR_OK],
        ['error' => UPLOAD_ERR_OK],
    ]));
    expect_same([], ainder_validate_photo_count(
        array_fill(0, 6, ['error' => UPLOAD_ERR_OK])
    ));
});

test('one or seven photos are rejected', function (): void {
    expect_same(true, isset(ainder_validate_photo_count([
        ['error' => UPLOAD_ERR_OK],
    ])['photos']));
    expect_same(true, isset(ainder_validate_photo_count(
        array_fill(0, 7, ['error' => UPLOAD_ERR_OK])
    )['photos']));
});

test('oversized photo is rejected before mime detection', function (): void {
    expect_same('每張照片不可超過 10MB。', ainder_validate_photo_file([
        'error' => UPLOAD_ERR_OK,
        'size' => 10485761,
        'tmp_name' => __FILE__,
    ]));
});

test('non-image content is rejected', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'ainder-photo-');
    file_put_contents($path, 'not an image');

    expect_same('照片必須先裁切為 720×1280 WebP。', ainder_validate_photo_file([
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($path),
        'tmp_name' => $path,
    ]));

    unlink($path);
});

test('original JPEG or PNG is rejected before server image decoding', function (): void {
    $source = tempnam(sys_get_temp_dir(), 'ainder-source-');
    file_put_contents($source, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
        true
    ));

    expect_same('照片必須先裁切為 720×1280 WebP。', ainder_validate_photo_file([
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($source),
        'tmp_name' => $source,
    ]));
    unlink($source);
});

test('incorrectly sized WebP is rejected', function (): void {
    $source = tempnam(sys_get_temp_dir(), 'ainder-source-');
    $image = imagecreatetruecolor(10, 10);
    imagewebp($image, $source, 84);
    imagedestroy($image);

    expect_same('照片必須先裁切為 720×1280 WebP。', ainder_validate_photo_file([
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($source),
        'tmp_name' => $source,
    ]));
    unlink($source);
});

test('canonical WebP can be staged and cleaned', function (): void {
    $source = tempnam(sys_get_temp_dir(), 'ainder-source-');
    $image = imagecreatetruecolor(720, 1280);
    imagewebp($image, $source, 84);
    imagedestroy($image);
    $directory = sys_get_temp_dir().'/ainder-stage-'.bin2hex(random_bytes(4));
    $photo = ['error' => UPLOAD_ERR_OK, 'size' => filesize($source), 'tmp_name' => $source];
    $staged = ainder_stage_photos([$photo], $directory, function (string $from, string $to): bool {
        return copy($from, $to);
    });

    expect_same(1, count($staged));
    expect_same(true, is_file($staged[0]));
    expect_same('webp', pathinfo($staged[0], PATHINFO_EXTENSION));
    $info = getimagesize($staged[0]);
    expect_same(720, $info[0]);
    expect_same(1280, $info[1]);
    expect_same('image/webp', $info['mime']);

    ainder_cleanup_photo_paths($staged);
    expect_same(false, is_file($staged[0]));
    unlink($source);
    rmdir($directory);
});
