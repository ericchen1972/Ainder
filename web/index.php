<?php

declare(strict_types=1);

require_once __DIR__.'/lib/auth.php';
require_once __DIR__.'/lib/config.php';
require_once __DIR__.'/lib/database.php';
require_once __DIR__.'/lib/session.php';
require_once __DIR__.'/lib/test_accounts.php';

ainder_start_session();

$destination = ainder_home_destination($_SESSION, time());
if ($destination !== null) {
    header('Location: '.$destination);
    exit;
}

$clientId = ainder_config()['google_client_id'];
$loginFailed = ($_GET['login'] ?? '') === 'failed';
$testLoginFailed = ($_GET['login'] ?? '') === 'test-failed';
$testAccounts = [];
try {
    $testAccounts = ainder_test_account_cards(
        ainder_database(ainder_config())
    );
} catch (Throwable) {
    $testAccounts = [];
}
$testLoginCsrf = count($testAccounts) === 2
    ? ainder_form_csrf_token()
    : '';
$cssVersion = (string) filemtime(__DIR__.'/assets/app.css');
$escape = static fn (mixed $value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES,
    'UTF-8'
);
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111319">
    <meta name="description" content="Maybe your Agent knows whether you are truly right for each other.">
    <title>Ainder</title>
    <link rel="preload" href="/ainder/assets/ainder-hero-desktop.webp" as="image" media="(min-width: 721px)">
    <link rel="preload" href="/ainder/assets/ainder-hero-mobile.webp" as="image" media="(max-width: 720px)">
    <link rel="stylesheet" href="/ainder/assets/app.css?v=<?= rawurlencode($cssVersion) ?>">
    <?php if ($clientId !== ''): ?>
        <script src="https://accounts.google.com/gsi/client" async defer></script>
    <?php endif; ?>
</head>
<body class="landing">
    <picture class="hero" aria-hidden="true">
        <source media="(max-width: 720px)" srcset="/ainder/assets/ainder-hero-mobile.webp">
        <img src="/ainder/assets/ainder-hero-desktop.webp" alt="">
    </picture>
    <div class="shade" aria-hidden="true"></div>

    <header class="corner-bar">
        <img class="logo" src="/ainder/assets/ainder-logo-white.webp" alt="Ainder">

        <div class="login-area">
            <?php if ($clientId !== ''): ?>
                <div
                    id="g_id_onload"
                    data-client_id="<?= htmlspecialchars($clientId, ENT_QUOTES, 'UTF-8') ?>"
                    data-login_uri="https://sweety.tw/ainder/auth/google.php"
                    data-auto_prompt="false"
                ></div>
                <div
                    class="g_id_signin"
                    data-type="standard"
                    data-shape="pill"
                    data-theme="outline"
                    data-text="signin_with"
                    data-size="large"
                    data-logo_alignment="left"
                ></div>
            <?php else: ?>
                <span
                    class="google-fallback-button"
                    role="button"
                    aria-disabled="true"
                    title="Google login will be enabled next"
                >
                    <svg class="google-mark" viewBox="0 0 24 24" aria-hidden="true">
                        <path fill="#4285f4" d="M21.6 12.23c0-.71-.06-1.39-.18-2.05H12v3.87h5.38a4.6 4.6 0 0 1-2 3.02v2.51h3.24c1.9-1.75 2.98-4.33 2.98-7.35Z"/>
                        <path fill="#34a853" d="M12 22c2.7 0 4.97-.9 6.62-2.42l-3.24-2.51c-.9.6-2.05.96-3.38.96-2.61 0-4.82-1.76-5.61-4.13H3.04v2.59A10 10 0 0 0 12 22Z"/>
                        <path fill="#fbbc05" d="M6.39 13.9A6 6 0 0 1 6.08 12c0-.66.11-1.3.31-1.9V7.51H3.04A10 10 0 0 0 2 12c0 1.61.39 3.14 1.04 4.49l3.35-2.59Z"/>
                        <path fill="#ea4335" d="M12 5.97c1.47 0 2.8.5 3.84 1.5l2.88-2.88A9.65 9.65 0 0 0 12 2a10 10 0 0 0-8.96 5.51l3.35 2.59C7.18 7.73 9.39 5.97 12 5.97Z"/>
                    </svg>
                    <span>Sign in with Google</span>
                </span>
            <?php endif; ?>
        </div>
    </header>

    <?php if (count($testAccounts) === 2): ?>
        <section class="test-login-panel" aria-label="Test accounts">
            <div class="test-login-accounts">
                <?php foreach ($testAccounts as $testAccount): ?>
                    <form class="test-login-form" method="post" action="/ainder/auth/test.php">
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= $escape($testLoginCsrf) ?>"
                        >
                        <input
                            type="hidden"
                            name="account_slug"
                            value="<?= $escape($testAccount['slug']) ?>"
                        >
                        <button class="test-login-button" type="submit">
                            <img
                                class="test-login-avatar"
                                src="<?= $escape($testAccount['photo_path']) ?>"
                                alt=""
                            >
                            <span><?= $testAccount['slug'] === 'grace' ? 'Login as Grace' : 'Login as John' ?></span>
                        </button>
                    </form>
                <?php endforeach; ?>
            </div>
            <aside class="test-login-alert" role="note">
                <strong>Test account</strong>
                <span>Test account activity is reset, so Likes, Matches, and Messages are not retained. For the most accurate experience, sign in with your own Google account and use ChatGPT with long-term memory about you.</span>
            </aside>
        </section>
    <?php endif; ?>

    <?php if ($loginFailed): ?>
        <p class="login-error" role="alert">登入失敗，請再試一次。</p>
    <?php elseif ($testLoginFailed): ?>
        <p class="login-error" role="alert">Test login is temporarily unavailable.</p>
    <?php endif; ?>
</body>
</html>
