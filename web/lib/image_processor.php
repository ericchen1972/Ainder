<?php

declare(strict_types=1);

const AINDER_OUTPUT_WIDTH = 720;
const AINDER_OUTPUT_HEIGHT = 1280;
const AINDER_OUTPUT_WEBP_QUALITY = 84;
const AINDER_PROCESSABLE_IMAGE_MIMES = [
    'image/jpeg',
    'image/png',
    'image/webp',
];

function ainder_rotate_image(GdImage $image, int $degrees): GdImage
{
    $rotated = imagerotate($image, $degrees, 0);
    if (!$rotated instanceof GdImage) {
        throw new RuntimeException('Unable to orient image.');
    }
    imagedestroy($image);

    return $rotated;
}

function ainder_apply_exif_orientation(GdImage $image, int $orientation): GdImage
{
    return match ($orientation) {
        2 => (function () use ($image): GdImage {
            imageflip($image, IMG_FLIP_HORIZONTAL);
            return $image;
        })(),
        3 => ainder_rotate_image($image, 180),
        4 => (function () use ($image): GdImage {
            imageflip($image, IMG_FLIP_VERTICAL);
            return $image;
        })(),
        5 => (function () use ($image): GdImage {
            imageflip($image, IMG_FLIP_HORIZONTAL);
            return ainder_rotate_image($image, -90);
        })(),
        6 => ainder_rotate_image($image, -90),
        7 => (function () use ($image): GdImage {
            imageflip($image, IMG_FLIP_HORIZONTAL);
            return ainder_rotate_image($image, 90);
        })(),
        8 => ainder_rotate_image($image, 90),
        default => $image,
    };
}

function ainder_decode_image(string $path): GdImage
{
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!is_string($mime) || !in_array($mime, AINDER_PROCESSABLE_IMAGE_MIMES, true)) {
        throw new InvalidArgumentException('Invalid image data.');
    }

    $bytes = file_get_contents($path);
    $image = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
    if (!$image instanceof GdImage) {
        throw new InvalidArgumentException('Invalid image data.');
    }

    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $image = ainder_apply_exif_orientation($image, $orientation);
    }

    return $image;
}

function ainder_process_image(string $sourcePath, string $targetPath): void
{
    $source = ainder_decode_image($sourcePath);
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    if ($sourceWidth < 1 || $sourceHeight < 1) {
        imagedestroy($source);
        throw new InvalidArgumentException('Invalid image dimensions.');
    }

    $scale = max(
        AINDER_OUTPUT_WIDTH / $sourceWidth,
        AINDER_OUTPUT_HEIGHT / $sourceHeight
    );
    $cropWidth = AINDER_OUTPUT_WIDTH / $scale;
    $cropHeight = AINDER_OUTPUT_HEIGHT / $scale;
    $sourceX = max(0.0, ($sourceWidth - $cropWidth) / 2);
    $sourceY = max(0.0, ($sourceHeight - $cropHeight) / 2);
    $output = imagecreatetruecolor(AINDER_OUTPUT_WIDTH, AINDER_OUTPUT_HEIGHT);

    $copied = imagecopyresampled(
        $output,
        $source,
        0,
        0,
        (int) round($sourceX),
        (int) round($sourceY),
        AINDER_OUTPUT_WIDTH,
        AINDER_OUTPUT_HEIGHT,
        (int) round($cropWidth),
        (int) round($cropHeight)
    );
    $written = $copied
        && imagewebp($output, $targetPath, AINDER_OUTPUT_WEBP_QUALITY);
    imagedestroy($output);
    imagedestroy($source);

    if (!$written) {
        if (is_file($targetPath)) {
            unlink($targetPath);
        }
        throw new RuntimeException('Unable to write processed image.');
    }
}
