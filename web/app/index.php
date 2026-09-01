<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/lib/session.php';
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/database.php';
require_once dirname(__DIR__).'/lib/candidates.php';
require_once dirname(__DIR__).'/lib/matches.php';
require_once dirname(__DIR__).'/lib/profile_editor.php';

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
    $profilePhotos = ainder_member_profile_photos(
        $database,
        (int) $member['id']
    );
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
    <script type="module" src="/ainder/assets/profile-editor.js?v=<?= $assetVersion('/assets/profile-editor.js') ?>"></script>
</head>
<body class="browse-page">
<main class="candidate-browser" data-current-candidate-id="<?= $escape($currentCandidateId) ?>">
    <aside class="browse-sidebar" aria-label="Ainder navigation">
        <div class="member-bar">
            <button class="profile-open" type="button" aria-label="Edit profile">
                <img data-member-avatar src="<?= $escape($avatarPath) ?>" alt="">
            </button>
            <img class="sidebar-logo" src="/ainder/assets/ainder-logo-white.webp" alt="Ainder">
            <form class="logout-form" method="post" action="/ainder/logout.php">
                <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                <button type="submit">Logout</button>
            </form>
        </div>
        <div class="sidebar-tabs" role="tablist" aria-label="Member activity">
            <button type="button" role="tab" data-tab="likes" aria-selected="<?= $messageMode ? 'false' : 'true' ?>">Likes</button>
            <button type="button" role="tab" data-tab="messages" aria-selected="<?= $messageMode ? 'true' : 'false' ?>">Messages</button>
        </div>
        <div class="sidebar-panel" data-panel="likes"<?= $messageMode ? ' hidden' : '' ?>>
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
                <button class="profile-open" type="button" aria-label="Edit profile">
                    <img data-member-avatar src="<?= $escape($avatarPath) ?>" alt="會員資料">
                </button>
                <form class="logout-form" method="post" action="/ainder/logout.php">
                    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
                    <button type="submit">Logout</button>
                </form>
            </div>
        </header>

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

        <nav class="mobile-destination-nav" aria-label="Primary navigation">
            <button
                type="button"
                data-destination="slide"
                aria-label="Slide"
                aria-selected="<?= $messageMode ? 'false' : 'true' ?>"
            >
                <svg data-fa-icon="fire-flame-curved" viewBox="0 0 384 512" aria-hidden="true"><path d="M153.6 29.9l16-21.3C173.6 3.2 180 0 186.7 0C198.4 0 208 9.6 208 21.3V43.5c0 13.1 5.4 25.7 14.9 34.7L307.6 159C356.4 205.6 384 270.2 384 337.7C384 434 306 512 209.7 512H192C86 512 0 426 0 320v-3.8c0-48.8 19.4-95.6 53.9-130.1l3.5-3.5c4.2-4.2 10-6.6 16-6.6C85.9 176 96 186.1 96 198.6V288c0 35.3 28.7 64 64 64s64-28.7 64-64v-3.9c0-18-7.2-35.3-19.9-48l-38.6-38.6c-24-24-37.5-56.7-37.5-90.7c0-27.7 9-54.8 25.6-76.9z"></path></svg>
            </button>
            <button
                type="button"
                data-destination="likes"
                aria-label="Likes"
                aria-selected="false"
            >
                <svg data-fa-icon="heart" viewBox="0 0 512 512" aria-hidden="true"><path d="M47.6 300.4L228.3 469.1c7.5 7 17.4 10.9 27.7 10.9s20.2-3.9 27.7-10.9L464.4 300.4c30.4-28.3 47.6-68 47.6-109.5v-5.8c0-69.9-50.5-129.5-119.4-141C347 36.5 300.6 51.4 268 84L256 96 244 84c-32.6-32.6-79-47.5-124.6-39.9C50.5 55.6 0 115.2 0 185.1v5.8c0 41.5 17.2 81.2 47.6 109.5z"></path></svg>
            </button>
            <button
                type="button"
                data-destination="messages"
                aria-label="Messages"
                aria-selected="<?= $messageMode ? 'true' : 'false' ?>"
            >
                <svg data-fa-icon="comment-dots" viewBox="0 0 512 512" aria-hidden="true"><path d="M256 448c141.4 0 256-93.1 256-208S397.4 32 256 32S0 125.1 0 240c0 45.1 17.7 86.8 47.7 120.9c-1.9 24.5-11.4 46.3-21.4 62.9c-5.5 9.2-11.1 16.6-15.2 21.6c-2.1 2.5-3.7 4.4-4.9 5.7c-.6 .6-1 1.1-1.3 1.4l-.3 .3c-4.6 4.6-5.9 11.4-3.4 17.4c2.5 6 8.3 9.9 14.8 9.9c28.7 0 57.6-8.9 81.6-19.3c22.9-10 42.4-21.9 54.3-30.6c31.8 11.5 67 17.9 104.1 17.9zM128 208a32 32 0 1 1 0 64 32 32 0 1 1 0-64zm128 0a32 32 0 1 1 0 64 32 32 0 1 1 0-64zm96 32a32 32 0 1 1 64 0 32 32 0 1 1-64 0z"></path></svg>
            </button>
        </nav>

        <p class="visually-hidden" aria-live="polite" data-candidate-status></p>
    </section>
</main>

<dialog class="opinion-modal">
    <button class="opinion-modal-close" type="button" aria-label="Close opinion">×</button>
    <h2>Agent opinion</h2>
    <p data-full-opinion></p>
</dialog>

<dialog class="profile-editor-modal" aria-labelledby="profile-editor-title">
    <form class="profile-editor-form" method="dialog">
        <header class="profile-editor-header">
            <div>
                <span class="profile-editor-kicker">Ainder</span>
                <h2 id="profile-editor-title">Edit profile</h2>
            </div>
            <button class="profile-editor-close" type="button" aria-label="Close profile editor">×</button>
        </header>
        <label class="profile-editor-label" for="profile-display-name">Name</label>
        <input
            id="profile-display-name"
            name="display_name"
            type="text"
            maxlength="120"
            value="<?= $escape($member['display_name']) ?>"
            required
        >
        <div class="profile-photo-heading">
            <strong>Photos</strong>
            <span>First photo is the main photo</span>
        </div>
        <div class="profile-photo-grid">
            <?php foreach ($profilePhotos as $photo): ?>
                <?php $slot = (int) $photo['sort_order']; ?>
                <button
                    class="profile-photo-slot"
                    type="button"
                    data-profile-photo-slot="<?= $slot ?>"
                    aria-label="Replace photo <?= $slot ?>"
                >
                    <img src="<?= $escape($photo['file_path']) ?>" alt="Profile photo <?= $slot ?>">
                    <?php if ($slot === 1): ?>
                        <span class="profile-photo-main">Main</span>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>
            <?php if (count($profilePhotos) < 6): ?>
                <button
                    class="profile-photo-add"
                    type="button"
                    aria-label="Add photo <?= count($profilePhotos) + 1 ?>"
                >＋</button>
            <?php endif; ?>
        </div>
        <input
            class="visually-hidden"
            data-profile-photo-input
            type="file"
            accept="image/jpeg,image/png,image/webp"
        >
        <p class="profile-editor-help">Tap an existing photo to replace it. Photos cannot be deleted.</p>
        <p class="profile-editor-error" role="alert" hidden></p>
        <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
        <button class="profile-editor-save" type="submit">Save changes</button>
    </form>
</dialog>
</body>
</html>
