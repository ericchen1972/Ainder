<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/image_processor.php';

function ainder_test_image(string $path, int $width, int $height, string $type): void
{
    $image = imagecreatetruecolor($width, $height);
    $left = imagecolorallocate($image, 220, 40, 80);
    $right = imagecolorallocate($image, 30, 90, 210);
    imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height, $left);
    imagefilledrectangle($image, intdiv($width, 2), 0, $width, $height, $right);
    match ($type) {
        'jpeg' => imagejpeg($image, $path, 92),
        'png' => imagepng($image, $path),
        'webp' => imagewebp($image, $path, 92),
    };
    imagedestroy($image);
}

test('JPEG PNG and WebP normalize to exact portrait WebP', function (): void {
    foreach (['jpeg', 'png', 'webp'] as $type) {
        $source = tempnam(sys_get_temp_dir(), 'ainder-source-');
        $target = tempnam(sys_get_temp_dir(), 'ainder-target-').'.webp';
        ainder_test_image($source, 1600, 900, $type);

        ainder_process_image($source, $target);
        $info = getimagesize($target);

        expect_same(720, $info[0]);
        expect_same(1280, $info[1]);
        expect_same('image/webp', $info['mime']);
        unlink($source);
        unlink($target);
    }
});

test('orientation six rotates an image before cropping', function (): void {
    $image = imagecreatetruecolor(40, 80);
    $rotated = ainder_apply_exif_orientation($image, 6);
    expect_same(80, imagesx($rotated));
    expect_same(40, imagesy($rotated));
    imagedestroy($rotated);
});

test('invalid image data is rejected without output', function (): void {
    $source = tempnam(sys_get_temp_dir(), 'ainder-invalid-');
    $target = $source.'.webp';
    file_put_contents($source, 'not an image');

    try {
        ainder_process_image($source, $target);
        throw new RuntimeException('Expected invalid image rejection.');
    } catch (InvalidArgumentException) {
        expect_same(false, is_file($target));
    } finally {
        unlink($source);
    }
});
