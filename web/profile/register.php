<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/auth.php';
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/database.php';
require_once dirname(__DIR__).'/lib/photos.php';
require_once dirname(__DIR__).'/lib/registration.php';
require_once dirname(__DIR__).'/lib/session.php';

ainder_start_session();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /ainder/profile/');
    exit;
}

if (!ainder_pending_identity_is_valid($_SESSION, time())) {
    header('Location: /ainder/');
    exit;
}

$input = [
    'display_name' => trim((string) ($_POST['display_name'] ?? '')),
    'birth_date' => (string) ($_POST['birth_date'] ?? ''),
    'gender' => (string) ($_POST['gender'] ?? ''),
    'basic_intro' => (string) ($_POST['basic_intro'] ?? ''),
];

if (!ainder_form_csrf_is_valid((string) ($_POST['csrf_token'] ?? ''))) {
    ainder_set_form_flash(['form' => '表單已過期，請重新送出。'], $input);
    header('Location: /ainder/profile/?manual=1');
    exit;
}

$photos = ainder_normalize_uploads($_FILES['photos'] ?? []);
$errors = ainder_validate_registration_fields($input, new DateTimeImmutable('today'));
$errors = array_merge($errors, ainder_validate_photo_count($photos));

foreach ($photos as $photo) {
    if (($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        continue;
    }
    $photoError = ainder_validate_photo_file($photo);
    if ($photoError !== null) {
        $errors['photos'] = $photoError;
        break;
    }
}

if ($errors !== []) {
    ainder_set_form_flash($errors, $input);
    header('Location: /ainder/profile/?manual=1');
    exit;
}

$identity = $_SESSION['ainder_pending_identity'];
$attemptId = bin2hex(random_bytes(16));
$stagingDirectory = dirname(__DIR__).'/uploads/.staging/'.$attemptId;
$uploadRoot = dirname(__DIR__).'/uploads/profiles';
$stagedPaths = [];
$finalPaths = [];

try {
    $stagedPaths = ainder_stage_photos($photos, $stagingDirectory);
    $database = ainder_database(ainder_config());
    $memberId = ainder_create_member_with_photos(
        $database,
        $identity,
        $input,
        function (int $newMemberId) use (
            $stagedPaths,
            $uploadRoot,
            &$finalPaths
        ): array {
            $finalPaths = ainder_finalize_photos(
                $stagedPaths,
                $uploadRoot,
                $newMemberId
            );

            return array_map(
                static fn (string $path): string => '/ainder/uploads/profiles/'
                    .$newMemberId.'/'.basename($path),
                $finalPaths
            );
        }
    );
} catch (Throwable $error) {
    ainder_cleanup_photo_paths($stagedPaths);
    ainder_cleanup_photo_paths($finalPaths);

    if ($error instanceof mysqli_sql_exception && $error->getCode() === 1062) {
        try {
            $database ??= ainder_database(ainder_config());
            $member = ainder_find_member($database, (string) $identity['google_sub']);
            if (($member['status'] ?? null) === 'active') {
                unset(
                    $_SESSION['ainder_pending_identity'],
                    $_SESSION['ainder_pending_expires_at'],
                    $_SESSION['ainder_form_csrf']
                );
                $_SESSION['ainder_member_id'] = (int) $member['id'];
                session_regenerate_id(true);
                header('Location: /ainder/app/');
                exit;
            }
        } catch (Throwable) {
        }
    }

    ainder_set_form_flash(
        ['form' => '目前無法建立帳號，請稍後再試。'],
        $input
    );
    header('Location: /ainder/profile/?manual=1');
    exit;
}

if (is_dir($stagingDirectory)) {
    @rmdir($stagingDirectory);
}

unset(
    $_SESSION['ainder_pending_identity'],
    $_SESSION['ainder_pending_expires_at'],
    $_SESSION['ainder_form_csrf'],
    $_SESSION['ainder_form_flash']
);
$_SESSION['ainder_member_id'] = $memberId;
session_regenerate_id(true);

header('Location: /ainder/app/');
exit;
