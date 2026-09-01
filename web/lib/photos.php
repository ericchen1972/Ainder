<?php

declare(strict_types=1);

require_once __DIR__.'/image_processor.php';

const AINDER_MAX_PHOTO_BYTES = 10 * 1024 * 1024;
const AINDER_CLIENT_PHOTO_MIME = 'image/webp';
const AINDER_CLIENT_PHOTO_ERROR = '照片必須先裁切為 720×1280 WebP。';

function ainder_normalize_uploads(array $files): array
{
    $normalized = [];

    foreach (($files['name'] ?? []) as $index => $name) {
        $normalized[] = [
            'name' => (string) $name,
            'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
            'size' => (int) ($files['size'][$index] ?? 0),
            'error' => (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
        ];
    }

    return $normalized;
}

function ainder_validate_photo_count(array $photos): array
{
    $successful = array_values(array_filter(
        $photos,
        static fn (array $photo): bool => ($photo['error'] ?? null) === UPLOAD_ERR_OK
    ));
    $count = count($successful);

    return $count >= 2 && $count <= 6
        ? []
        : ['photos' => '請上傳 2–6 張照片。'];
}

function ainder_is_client_processed_photo(string $path): bool
{
    if ($path === '' || !is_file($path)) {
        return false;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if ($mime !== AINDER_CLIENT_PHOTO_MIME) {
        return false;
    }

    $info = @getimagesize($path);

    return is_array($info)
        && (int) ($info[0] ?? 0) === AINDER_OUTPUT_WIDTH
        && (int) ($info[1] ?? 0) === AINDER_OUTPUT_HEIGHT
        && ($info['mime'] ?? null) === AINDER_CLIENT_PHOTO_MIME;
}

function ainder_validate_photo_file(array $photo): ?string
{
    if (($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return '照片上傳失敗，請重新選擇。';
    }

    if (($photo['size'] ?? 0) > AINDER_MAX_PHOTO_BYTES) {
        return '每張照片不可超過 10MB。';
    }

    return ainder_is_client_processed_photo((string) ($photo['tmp_name'] ?? ''))
        ? null
        : AINDER_CLIENT_PHOTO_ERROR;
}

function ainder_stage_photos(
    array $photos,
    string $directory,
    ?callable $mover = null
): array {
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create photo staging directory.');
    }

    $mover ??= static fn (string $from, string $to): bool => move_uploaded_file($from, $to);
    $staged = [];

    try {
        foreach ($photos as $photo) {
            $error = ainder_validate_photo_file($photo);
            if ($error !== null) {
                throw new InvalidArgumentException($error);
            }

            $sourcePath = rtrim($directory, '/')
                .'/'.bin2hex(random_bytes(16)).'.source';
            $processedPath = rtrim($directory, '/')
                .'/'.bin2hex(random_bytes(16)).'.webp';
            if (!$mover($photo['tmp_name'], $sourcePath)) {
                throw new RuntimeException('Unable to stage uploaded photo.');
            }
            try {
                ainder_process_image($sourcePath, $processedPath);
                $staged[] = $processedPath;
            } finally {
                if (is_file($sourcePath)) {
                    unlink($sourcePath);
                }
            }
        }
    } catch (Throwable $error) {
        ainder_cleanup_photo_paths($staged);
        throw $error;
    }

    return $staged;
}

function ainder_finalize_photos(
    array $stagedPaths,
    string $uploadRoot,
    int $memberId
): array {
    $directory = rtrim($uploadRoot, '/').'/'.$memberId;
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create member photo directory.');
    }

    $finalPaths = [];
    foreach ($stagedPaths as $stagedPath) {
        if (strtolower((string) pathinfo($stagedPath, PATHINFO_EXTENSION)) !== 'webp') {
            throw new RuntimeException('Only processed WebP files can be finalized.');
        }
        $finalPath = $directory.'/'.bin2hex(random_bytes(16)).'.webp';
        if (!rename($stagedPath, $finalPath)) {
            ainder_cleanup_photo_paths($finalPaths);
            throw new RuntimeException('Unable to finalize uploaded photo.');
        }
        $finalPaths[] = $finalPath;
    }

    return $finalPaths;
}

function ainder_cleanup_photo_paths(array $paths): void
{
    foreach ($paths as $path) {
        if (is_string($path) && is_file($path)) {
            unlink($path);
        }
    }
}
