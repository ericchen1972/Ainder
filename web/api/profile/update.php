<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2).'/lib/api.php';
require_once dirname(__DIR__, 2).'/lib/config.php';
require_once dirname(__DIR__, 2).'/lib/database.php';
require_once dirname(__DIR__, 2).'/lib/photos.php';
require_once dirname(__DIR__, 2).'/lib/profile_editor.php';
require_once dirname(__DIR__, 2).'/lib/session.php';

ainder_start_session();
ainder_require_post_json();

$memberId = (int) ($_SESSION['ainder_member_id'] ?? 0);
if ($memberId < 1) {
    ainder_json_error('AUTH_REQUIRED', 'Member login required.', 401);
}
if (!ainder_form_csrf_is_valid((string) ($_POST['csrf_token'] ?? ''))) {
    ainder_json_error('CSRF_INVALID', 'Request confirmation expired.', 403);
}

$stagedPaths = [];
$finalPaths = [];

try {
    $displayName = trim((string) ($_POST['display_name'] ?? ''));
    $nameErrors = ainder_validate_profile_name($displayName);
    if ($nameErrors !== []) {
        throw new InvalidArgumentException($nameErrors['display_name']);
    }

    $photoSlots = is_array($_POST['photo_slots'] ?? null)
        ? array_values($_POST['photo_slots'])
        : [];
    $photos = ainder_normalize_uploads($_FILES['photos'] ?? []);
    if (count($photoSlots) !== count($photos)) {
        throw new InvalidArgumentException('Photo slots do not match uploads.');
    }
    foreach ($photos as $photo) {
        $error = ainder_validate_photo_file($photo);
        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }
    }

    $database = ainder_database(ainder_config());
    $existing = ainder_member_profile_photos($database, $memberId);
    ainder_validate_profile_photo_changes(
        count($existing),
        $photoSlots,
        array_fill(0, count($photoSlots), '/pending.webp')
    );

    if ($photos !== []) {
        $attemptId = bin2hex(random_bytes(16));
        $stagingDirectory = dirname(__DIR__, 2)
            .'/uploads/.staging/profile-edit-'.$attemptId;
        $stagedPaths = ainder_stage_photos($photos, $stagingDirectory);
        $finalPaths = ainder_finalize_photos(
            $stagedPaths,
            dirname(__DIR__, 2).'/uploads/profiles',
            $memberId
        );
        $stagedPaths = [];
    }

    $publicPaths = array_map(
        static fn (string $path): string => '/ainder/uploads/profiles/'
            .$memberId.'/'.basename($path),
        $finalPaths
    );
    $result = ainder_update_member_profile(
        $database,
        $memberId,
        $displayName,
        $photoSlots,
        $publicPaths
    );

    $memberUploadDirectory = dirname(__DIR__, 2)
        .'/uploads/profiles/'.$memberId;
    foreach ($result['superseded_local_paths'] as $publicPath) {
        $prefix = '/ainder/uploads/profiles/'.$memberId.'/';
        if (!str_starts_with((string) $publicPath, $prefix)) {
            continue;
        }
        $absolute = $memberUploadDirectory.'/'.basename((string) $publicPath);
        ainder_cleanup_photo_paths([$absolute]);
    }
    if (isset($stagingDirectory) && is_dir($stagingDirectory)) {
        @rmdir($stagingDirectory);
    }

    ainder_json_success([
        'profile' => [
            'display_name' => $result['display_name'],
            'photos' => $result['photos'],
            'avatar_path' => $result['photos'][0],
        ],
    ]);
} catch (InvalidArgumentException $error) {
    ainder_cleanup_photo_paths($stagedPaths);
    ainder_cleanup_photo_paths($finalPaths);
    ainder_json_error('PROFILE_INVALID', $error->getMessage(), 422);
} catch (Throwable) {
    ainder_cleanup_photo_paths($stagedPaths);
    ainder_cleanup_photo_paths($finalPaths);
    ainder_json_error('PROFILE_NOT_UPDATED', 'Profile was not updated.', 503);
}
