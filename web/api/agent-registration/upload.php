<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/lib/agent_registration.php';
require_once dirname(__DIR__, 2).'/lib/api.php';
require_once dirname(__DIR__, 2).'/lib/config.php';
require_once dirname(__DIR__, 2).'/lib/database.php';
require_once dirname(__DIR__, 2).'/lib/image_processor.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'PUT') {
    ainder_json_error('METHOD_NOT_ALLOWED', 'PUT required.', 405);
}

$uploadId = (string) ($_GET['id'] ?? '');
$expires = (int) ($_GET['expires'] ?? 0);
$signature = (string) ($_GET['signature'] ?? '');
$config = ainder_config();
if (!ainder_verify_upload_signature(
    $uploadId,
    $expires,
    $signature,
    (string) $config['upload_signing_key'],
    time()
)) {
    ainder_json_error('UPLOAD_SIGNATURE_INVALID', 'Upload URL is invalid or expired.', 403);
}

$sourcePath = '';
$processedPath = '';
try {
    $database = ainder_database($config);
    $upload = ainder_find_agent_upload($database, $uploadId);
    if (!is_array($upload)
        || $upload['status'] !== 'prepared'
        || $upload['registration_status'] !== 'active'
        || new DateTimeImmutable((string) $upload['expires_at']) <= new DateTimeImmutable('now')) {
        throw new RuntimeException('UPLOAD_STATE_INVALID');
    }

    $directory = dirname(__DIR__, 2).'/uploads/.agent/'
        .(string) $upload['registration_id'];
    if (!is_dir($directory)
        && !mkdir($directory, 0755, true)
        && !is_dir($directory)) {
        throw new RuntimeException('UPLOAD_STORAGE_FAILED');
    }
    $sourcePath = $directory.'/'.bin2hex(random_bytes(16)).'.source';
    $processedPath = $directory.'/'.bin2hex(random_bytes(16)).'.webp';
    $input = fopen('php://input', 'rb');
    $output = fopen($sourcePath, 'xb');
    if (!is_resource($input) || !is_resource($output)) {
        throw new RuntimeException('UPLOAD_STREAM_FAILED');
    }
    $bytes = stream_copy_to_stream($input, $output, AINDER_MAX_PHOTO_BYTES + 1);
    fclose($input);
    fclose($output);
    if ($bytes !== (int) $upload['declared_size']) {
        throw new InvalidArgumentException('PHOTO_SIZE_MISMATCH');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($sourcePath);
    if ($mime !== $upload['declared_mime']) {
        throw new InvalidArgumentException('PHOTO_TYPE_MISMATCH');
    }

    ainder_process_image($sourcePath, $processedPath);
    ainder_mark_agent_upload_ready($database, $uploadId, $processedPath);
    if (is_file($sourcePath)) {
        unlink($sourcePath);
    }
    ainder_json_success([
        'upload_id' => $uploadId,
        'status' => 'ready',
    ], 201);
} catch (Throwable $error) {
    foreach ([$sourcePath, $processedPath] as $path) {
        if ($path !== '' && is_file($path)) {
            unlink($path);
        }
    }
    if (isset($database) && $uploadId !== '') {
        try {
            $failed = $database->prepare(
                "UPDATE agent_registration_uploads SET status = 'failed' "
                ."WHERE id = ? AND status = 'prepared'"
            );
            $failed->bind_param('s', $uploadId);
            $failed->execute();
        } catch (Throwable) {
        }
    }
    $code = $error instanceof InvalidArgumentException
        ? $error->getMessage()
        : 'UPLOAD_FAILED';
    ainder_json_error($code, 'Unable to accept photo upload.', 422);
}
