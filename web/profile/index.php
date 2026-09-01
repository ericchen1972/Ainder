<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/auth.php';
require_once dirname(__DIR__).'/lib/session.php';

ainder_start_session();

if (!ainder_pending_identity_is_valid($_SESSION, time())) {
    unset($_SESSION['ainder_pending_identity'], $_SESSION['ainder_pending_expires_at']);
    header('Location: /ainder/');
    exit;
}

$identity = $_SESSION['ainder_pending_identity'];
$flash = ainder_pull_form_flash();
$errors = is_array($flash['errors'] ?? null) ? $flash['errors'] : [];
$old = is_array($flash['input'] ?? null) ? $flash['input'] : [];
$manualOpen = ($_GET['manual'] ?? '') === '1' || $errors !== [];
$assetRoot = dirname(__DIR__).'/assets';
$cssVersion = (string) filemtime($assetRoot.'/app.css');
$scriptVersion = (string) filemtime($assetRoot.'/profile.js');
$maximumBirthDate = (new DateTimeImmutable('today'))->modify('-18 years')->format('Y-m-d');
$displayName = trim((string) ($old['display_name'] ?? $identity['display_name'] ?? ''));
$birthDate = (string) ($old['birth_date'] ?? '');
$gender = (string) ($old['gender'] ?? '');

function ainder_field_error(array $errors, string $field): string
{
    return isset($errors[$field])
        ? '<p class="field-error">'.htmlspecialchars((string) $errors[$field], ENT_QUOTES, 'UTF-8').'</p>'
        : '';
}
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111319">
    <meta name="ainder-csrf-token" content="<?= htmlspecialchars(ainder_form_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
    <title>建立 Ainder 帳號</title>
    <link rel="stylesheet" href="/ainder/assets/app.css?v=<?= rawurlencode($cssVersion) ?>">
    <script type="module" src="/ainder/assets/profile.js?v=<?= rawurlencode($scriptVersion) ?>"></script>
    <script type="module" src="/ainder/assets/webmcp-registration.js?v=<?= rawurlencode((string) filemtime($assetRoot.'/webmcp-registration.js')) ?>"></script>
</head>
<body class="profile-page<?= $manualOpen ? ' manual-is-open' : '' ?>">
    <header class="profile-header">
        <a href="/ainder/" aria-label="Ainder 首頁">
            <img src="/ainder/assets/ainder-logo-white.webp" alt="Ainder">
        </a>
    </header>

    <main class="onboarding-shell">
        <section class="agent-intro">
            <p class="eyebrow">AINDER PROFILE</p>
            <h1>你可以讓 Agent 為你填寫個人資訊</h1>
            <button type="button" class="manual-toggle" aria-expanded="<?= $manualOpen ? 'true' : 'false' ?>" aria-controls="manual-form">手動填寫</button>
        </section>

        <section id="manual-form" class="manual-form" <?= $manualOpen ? '' : 'hidden' ?>>
            <div class="form-heading">
                <p class="eyebrow">CREATE ACCOUNT</p>
                <h2>建立帳號</h2>
                <p>完成基本資料並上傳 2–6 張照片。</p>
            </div>

            <?php if (isset($errors['form'])): ?>
                <p class="form-error" role="alert"><?= htmlspecialchars((string) $errors['form'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form class="registration-form" action="/ainder/profile/register.php" method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ainder_form_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                <div class="personal-fields">
                    <label class="form-field">
                        <span>名字 <b>*</b></span>
                        <input type="text" name="display_name" value="<?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?>" maxlength="120" autocomplete="name" required>
                        <?= ainder_field_error($errors, 'display_name') ?>
                    </label>

                    <label class="form-field">
                        <span>電子郵件 <b>*</b></span>
                        <input type="email" value="<?= htmlspecialchars((string) $identity['email'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="email" readonly>
                    </label>

                    <label class="form-field">
                        <span>生日 <b>*</b></span>
                        <input type="date" name="birth_date" value="<?= htmlspecialchars($birthDate, ENT_QUOTES, 'UTF-8') ?>" max="<?= $maximumBirthDate ?>" required>
                        <?= ainder_field_error($errors, 'birth_date') ?>
                    </label>

                    <fieldset class="gender-field">
                        <legend>性別 <b>*</b></legend>
                        <div class="gender-options">
                            <label><input type="radio" name="gender" value="male" <?= $gender === 'male' ? 'checked' : '' ?> required><span>男性</span></label>
                            <label><input type="radio" name="gender" value="female" <?= $gender === 'female' ? 'checked' : '' ?> required><span>女性</span></label>
                        </div>
                        <?= ainder_field_error($errors, 'gender') ?>
                    </fieldset>
                </div>

                <div class="photo-fields">
                    <div class="photo-heading">
                        <span>個人照片 <b>*</b></span>
                        <small><strong class="photo-count">0</strong>/6</small>
                    </div>
                    <input id="photos" class="visually-hidden" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required>
                    <div class="photo-grid" aria-live="polite">
                        <?php for ($slot = 0; $slot < 6; $slot++): ?>
                            <button class="photo-slot" type="button" data-slot="<?= $slot ?>" aria-label="新增第 <?= $slot + 1 ?> 張照片"><span>＋</span></button>
                        <?php endfor; ?>
                    </div>
                    <p class="photo-help">至少上傳 2 張，每張不超過 10MB。支援 JPG、PNG、WebP，選取後會自動以 9:16 裁切。</p>
                    <p class="field-error photo-client-error" hidden></p>
                    <?= ainder_field_error($errors, 'photos') ?>
                </div>

                <div class="submit-row">
                    <button class="register-submit" type="submit" disabled>建立帳號</button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
