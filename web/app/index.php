<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/session.php';
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/database.php';
require_once dirname(__DIR__).'/lib/candidates.php';

ainder_start_session();

if (!isset($_SESSION['ainder_member_id'])) {
    header('Location: /ainder/');
    exit;
}

try {
    $database = ainder_database(ainder_config());
    $member = ainder_find_browse_member(
        $database,
        (int) $_SESSION['ainder_member_id']
    );

    if (!is_array($member) || $member['status'] !== 'active') {
        unset($_SESSION['ainder_member_id']);
        header('Location: /ainder/');
        exit;
    }

    $candidates = ainder_list_browse_candidates(
        $database,
        (string) $member['gender'],
        new DateTimeImmutable('now')
    );
} catch (Throwable) {
    http_response_code(503);
    exit('Ainder is temporarily unavailable.');
}

$assetVersion = static function (string $path): string {
    $absolute = dirname(__DIR__).$path;

    return is_file($absolute) ? (string) filemtime($absolute) : '1';
};
$escape = static fn (mixed $value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES,
    'UTF-8'
);
$csrfToken = ainder_form_csrf_token();
$currentCandidateId = $candidates === [] ? '' : (string) $candidates[0]['id'];
$avatarPath = trim((string) ($member['avatar_path'] ?? ''));
$avatarPath = $avatarPath !== ''
    ? $avatarPath
    : '/ainder/assets/ainder-logo-white.webp';
?>
<!doctype html>
<html lang="zh-Hant" data-current-candidate-id="<?= $escape($currentCandidateId) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d0e13">
    <meta name="ainder-csrf-token" content="<?= $escape($csrfToken) ?>">
    <title>Ainder</title>
    <link rel="stylesheet" href="/ainder/assets/browse.css?v=<?= $assetVersion('/assets/browse.css') ?>">
    <script type="importmap">{"imports":{"ainder-browse-model":"/ainder/assets/browse-model.js?v=<?= $assetVersion('/assets/browse-model.js') ?>"}}</script>
    <script type="module" src="/ainder/assets/browse.js?v=<?= $assetVersion('/assets/browse.js') ?>"></script>
    <script type="module" src="/ainder/assets/webmcp-app.js?v=<?= $assetVersion('/assets/webmcp-app.js') ?>"></script>
</head>
<body class="browse-page">
<main class="candidate-browser" data-current-candidate-id="<?= $escape($currentCandidateId) ?>">
    <aside class="browse-sidebar" aria-label="Ainder navigation">
        <div class="member-bar">
            <img src="<?= $escape($avatarPath) ?>" alt="">
            <img class="sidebar-logo" src="/ainder/assets/ainder-logo-white.webp" alt="Ainder">
            <form class="logout-form" method="post" action="/ainder/logout.php">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <button type="submit">登出</button>
            </form>
        </div>
        <div class="sidebar-tabs" role="tablist" aria-label="Member activity">
            <button type="button" role="tab" aria-selected="true">Agent Likes</button>
            <button type="button" role="tab" aria-selected="false">Messages</button>
        </div>
        <div class="sidebar-empty">
            <span class="agent-symbol" aria-hidden="true">✦</span>
            <h2>Your Agent handles Likes</h2>
            <p>Browse freely. Swiping never sends a Like.</p>
        </div>
    </aside>

    <section class="browse-stage" aria-label="候選會員">
        <header class="mobile-bar">
            <img src="/ainder/assets/ainder-logo-white.webp" alt="Ainder">
            <div class="mobile-member-actions">
                <img src="<?= $escape($avatarPath) ?>" alt="會員資料">
                <form class="logout-form" method="post" action="/ainder/logout.php">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <button type="submit">登出</button>
                </form>
            </div>
        </header>

        <?php if ($candidates === []): ?>
            <div class="candidate-empty">
                <h1>目前沒有可瀏覽的會員</h1>
                <p>請稍後再回來看看。</p>
            </div>
        <?php else: ?>
            <button class="candidate-control candidate-next" type="button" aria-label="下一位候選人">‹</button>
            <div class="candidate-stack">
                <?php foreach ($candidates as $candidateIndex => $candidate): ?>
                    <article
                        class="candidate-card<?= $candidateIndex === 0 ? ' is-current' : '' ?>"
                        data-candidate-id="<?= (int) $candidate['id'] ?>"
                        aria-hidden="<?= $candidateIndex === 0 ? 'false' : 'true' ?>"
                    >
                        <div class="photo-fallback" aria-hidden="true">Ainder</div>
                        <div class="photo-segments" aria-hidden="true">
                            <?php foreach ($candidate['photos'] as $photoIndex => $_): ?>
                                <i class="<?= $photoIndex === 0 ? 'is-active' : '' ?>"></i>
                            <?php endforeach; ?>
                        </div>

                        <?php foreach ($candidate['photos'] as $photoIndex => $photo): ?>
                            <figure
                                class="candidate-photo<?= $photoIndex === 0 ? ' is-active' : '' ?>"
                                data-photo-index="<?= $photoIndex ?>"
                            >
                                <img
                                    src="<?= $escape($photo['file_path']) ?>"
                                    alt="<?= $escape($candidate['display_name']) ?> 的照片 <?= $photoIndex + 1 ?>"
                                >
                                <?php if ($photo['source_type'] === 'unsplash'): ?>
                                    <figcaption>
                                        Photo by
                                        <a href="<?= $escape($photo['photographer_url']) ?>" target="_blank" rel="noopener noreferrer"><?= $escape($photo['photographer_name']) ?></a>
                                        on
                                        <a href="<?= $escape($photo['source_page_url']) ?>" target="_blank" rel="noopener noreferrer">Unsplash</a>
                                    </figcaption>
                                <?php endif; ?>
                            </figure>
                        <?php endforeach; ?>

                        <button class="photo-zone photo-previous" type="button" aria-label="上一張照片"></button>
                        <button class="photo-zone photo-next" type="button" aria-label="下一張照片"></button>
                        <div class="candidate-shade"></div>
                        <div class="candidate-copy">
                            <h1><?= $escape($candidate['display_name']) ?> <span><?= (int) $candidate['age'] ?></span></h1>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <button class="candidate-control candidate-previous" type="button" aria-label="上一位候選人">›</button>
            <p class="browse-hint">← 下一位 · → 上一位</p>
        <?php endif; ?>

        <p class="visually-hidden" aria-live="polite" data-candidate-status></p>
    </section>
</main>
</body>
</html>
