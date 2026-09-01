<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="zh-Hant">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111319">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/ainder/assets/app.css">
</head>
<body class="placeholder">
    <main class="placeholder-content">
        <img src="/ainder/assets/ainder-logo-white.webp" alt="Ainder">
        <h1><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
    </main>
</body>
</html>
