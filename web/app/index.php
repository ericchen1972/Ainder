<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/session.php';
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/database.php';
require_once dirname(__DIR__).'/lib/candidates.php';
require_once dirname(__DIR__).'/lib/matches.php';

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

    $now = new DateTimeImmutable('now');
    $candidates = ainder_list_browse_candidates(
        $database,
        (int) $member['id'],
        (string) $member['gender'],
        $now
    );
    $incomingLikes = ainder_list_incoming_likes(
        $database,
        (int) $member['id'],
        $now
    );
    $matches = ainder_list_matches($database, (int) $member['id'], $now);
    $requestedMatchId = max(0, (int) ($_GET['match'] ?? 0));
    $selectedMatch = null;
    foreach ($matches as $match) {
        if ((int) $match['match_id'] === $requestedMatchId) {
            $selectedMatch = $match;
            break;
        }
    }
    $messageMode = ($_GET['view'] ?? '') === 'messages'
        && is_array($selectedMatch);
    $initialMessages = $messageMode
        ? ainder_list_match_messages(
            $database,
            (int) $member['id'],
            (int) $selectedMatch['match_id']
        )
        : [];
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
                <button type="submit">Logout</button>
            </form>
        </div>
        <div class="sidebar-tabs" role="tablist" aria-label="Member activity">
            <button type="button" role="tab" data-tab="agent-likes" aria-selected="<?= $messageMode ? 'false' : 'true' ?>">Agent Likes</button>
            <button type="button" role="tab" data-tab="messages" aria-selected="<?= $messageMode ? 'true' : 'false' ?>">Messages</button>
        </div>
        <div class="sidebar-panel" data-panel="agent-likes"<?= $messageMode ? ' hidden' : '' ?>>
            <?php if ($incomingLikes !== []): ?>
                <ul class="agent-like-list" aria-label="Pending Agent Likes">
                <?php foreach ($incomingLikes as $incomingLike): ?>
                    <li
                        class="agent-like-row"
                        data-like-id="<?= (int) $incomingLike['like_id'] ?>"
                        data-candidate-id="<?= (int) $incomingLike['candidate_id'] ?>"
                    >
                        <button
                            class="agent-like-target"
                            type="button"
                            data-candidate-id="<?= (int) $incomingLike['candidate_id'] ?>"
                        >
                            <img
                                src="<?= $escape($incomingLike['photo_path']) ?>"
                                alt=""
                            >
                            <span class="agent-like-name">
                                <?= $escape($incomingLike['display_name']) ?>
                            </span>
                            <span class="agent-like-age">
                                <?= (int) $incomingLike['age'] ?>
                            </span>
                        </button>
                        <button
                            class="agent-like-remove"
                            type="button"
                            data-like-id="<?= (int) $incomingLike['like_id'] ?>"
                            aria-label="Remove <?= $escape($incomingLike['display_name']) ?> Like"
                        >
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M6 6l12 12M18 6L6 18"></path>
                            </svg>
                        </button>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="sidebar-empty"<?= $incomingLikes !== [] ? ' hidden' : '' ?>>
                <span class="agent-symbol" aria-hidden="true">✦</span>
                <h2>Your Agent handles Likes</h2>
                <p>Browse freely. Swiping never sends a Like.</p>
            </div>
        </div>
        <div class="sidebar-panel" data-panel="messages"<?= $messageMode ? '' : ' hidden' ?>>
            <?php if ($matches !== []): ?>
                <div class="message-list" aria-label="Matches">
                    <?php foreach ($matches as $match): ?>
                        <article
                            class="match-card"
                            role="button"
                            tabindex="0"
                            data-match-id="<?= (int) $match['match_id'] ?>"
                            data-candidate-id="<?= (int) $match['candidate_id'] ?>"
                            data-name="<?= $escape($match['display_name']) ?>"
                            data-age="<?= (int) $match['age'] ?>"
                        >
                            <img
                                class="match-card-photo"
                                src="<?= $escape($match['photo_path']) ?>"
                                alt=""
                            >
                            <div class="match-card-content">
                                <div class="match-card-heading">
                                    <strong><?= $escape($match['display_name']) ?></strong>
                                    <span class="match-card-age"><?= (int) $match['age'] ?></span>
                                </div>
                                <button
                                    class="match-card-opinion"
                                    type="button"
                                    data-opinion="<?= $escape($match['agent_opinion']) ?>"
                                ><?= $escape($match['agent_opinion']) ?></button>
                            </div>
                            <button
                                class="match-card-close"
                                type="button"
                                aria-label="Cancel Match with <?= $escape($match['display_name']) ?>"
                            >
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M6 6l12 12M18 6L6 18"></path>
                                </svg>
                            </button>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="messages-empty">
                    <h2>No Messages yet</h2>
                    <p>Your Matches will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <section class="browse-stage" aria-label="候選會員">
        <header class="mobile-bar">
            <img class="mobile-logo" src="/ainder/assets/ainder-logo-white.webp" alt="Ainder">
            <div class="mobile-member-actions">
                <img src="<?= $escape($avatarPath) ?>" alt="會員資料">
                <form class="logout-form" method="post" action="/ainder/logout.php">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <button type="submit">Logout</button>
                </form>
            </div>
        </header>

        <div class="mobile-tabs" role="tablist" aria-label="Member activity">
            <button type="button" role="tab" data-tab="agent-likes" aria-selected="<?= $messageMode ? 'false' : 'true' ?>">Agent Likes</button>
            <button type="button" role="tab" data-tab="messages" aria-selected="<?= $messageMode ? 'true' : 'false' ?>">Messages</button>
        </div>

        <div class="browse-content"<?= $messageMode ? ' hidden' : '' ?>>
        <?php if ($candidates === []): ?>
            <div class="candidate-empty">
                <h1>目前沒有可瀏覽的會員</h1>
                <p>請稍後再回來看看。</p>
            </div>
        <?php else: ?>
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
                                    draggable="false"
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

                        <?php if (count($candidate['photos']) > 1): ?>
                            <button class="photo-control photo-previous" type="button" aria-label="上一張照片">
                                <span aria-hidden="true">‹</span>
                            </button>
                            <button class="photo-control photo-next" type="button" aria-label="下一張照片">
                                <span aria-hidden="true">›</span>
                            </button>
                        <?php endif; ?>
                        <div class="candidate-shade"></div>
                        <div class="candidate-copy">
                            <h1>
                                <span class="candidate-name"><?= $escape($candidate['display_name']) ?></span>
                                <span class="candidate-age"><?= (int) $candidate['age'] ?></span>
                            </h1>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <p class="browse-hint">Drag the card to browse · Use the arrows to change photos</p>
        <?php endif; ?>
        </div>

        <section
            class="message-view"
            data-match-id="<?= $selectedMatch ? (int) $selectedMatch['match_id'] : '' ?>"
            <?= $messageMode ? '' : 'hidden' ?>
        >
            <header class="message-header">
                <button class="message-back" type="button" aria-label="Back to browse">←</button>
                <div>
                    <strong data-message-name><?= $selectedMatch ? $escape($selectedMatch['display_name']) : '' ?></strong>
                    <span data-message-age><?= $selectedMatch ? (int) $selectedMatch['age'] : '' ?></span>
                </div>
            </header>
            <div class="message-thread" aria-live="polite">
                <?php foreach ($initialMessages as $message): ?>
                    <p class="message-bubble<?= $message['is_mine'] ? ' is-mine' : '' ?>" data-message-id="<?= (int) $message['id'] ?>"><?= $escape($message['body']) ?></p>
                <?php endforeach; ?>
            </div>
            <form class="message-composer">
                <div class="emoji-picker">
                    <button class="emoji-toggle" type="button" aria-expanded="false" aria-label="Open emoji list">☺</button>
                    <div class="emoji-list" hidden>
                        <?php foreach (['😀', '😊', '😍', '😂', '🥰', '👍', '❤️', '👋'] as $emoji): ?>
                            <button type="button" data-emoji="<?= $emoji ?>"><?= $emoji ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input name="message" type="text" maxlength="2000" autocomplete="off" placeholder="Write a message…" aria-label="Message">
                <button class="message-send" type="submit">Send</button>
            </form>
        </section>

        <p class="visually-hidden" aria-live="polite" data-candidate-status></p>
    </section>
</main>

<dialog class="opinion-modal">
    <button class="opinion-modal-close" type="button" aria-label="Close opinion">×</button>
    <h2>Agent opinion</h2>
    <p data-full-opinion></p>
</dialog>
</body>
</html>
