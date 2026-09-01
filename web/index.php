<?php

declare(strict_types=1);

require_once __DIR__.'/lib/config.php';
require_once __DIR__.'/lib/session.php';

ainder_start_session();

if (isset($_SESSION['ainder_member_id'])) {
    header('Location: /ainder/app/');
    exit;
}

$clientId = ainder_config()['google_client_id'];
$loginFailed = ($_GET['login'] ?? '') === 'failed';
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
    <link rel="stylesheet" href="/ainder/assets/app.css">
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
                <span class="login-unavailable">Google login unavailable</span>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($loginFailed): ?>
        <p class="login-error" role="alert">登入失敗，請再試一次。</p>
    <?php endif; ?>
</body>
</html>
