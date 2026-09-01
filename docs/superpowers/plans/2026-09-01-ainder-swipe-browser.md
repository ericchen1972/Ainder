# Ainder Swipe Browser Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove the former basic-info field completely, then replace the authenticated placeholder with a responsive, opposite-gender candidate browser whose circular swipes only navigate and never Like.

**Architecture:** A rerunnable migration removes `users.basic_intro`, and registration/Demo paths stop accepting or writing it before the browse surface is deployed. A focused PHP repository reads active opposite-gender members and converts joined member/photo rows into a strict public card payload. The authenticated page server-renders those cards, while a small pure JavaScript model and DOM controller handle circular candidate navigation, per-member photo navigation, gestures, keyboard input, accessibility, and image fallback; no browsing-history write is introduced.

**Tech Stack:** PHP 8 strict mode, MariaDB/MySQLi, semantic HTML, dedicated CSS, browser ES modules, Node.js built-in test runner, existing PHP test harness, mounted production deployment.

---

## File Map

- Create `web/migrations/003_remove_basic_intro.php`: token-protected, rerunnable removal of `users.basic_intro`.
- Modify `web/lib/registration.php`, `web/lib/database.php`, `web/profile/index.php`, and `web/profile/register.php`: remove the basic-info input, validation, and insert path.
- Modify `web/lib/demo.php`, `web/seeds/demo_members.php`, and `web/diagnostics/demo_seed_status.php`: remove basic-info validation, persistence, fixture data, and diagnostics.
- Modify existing PHP tests: prove the old field is absent and the removal migration is present.
- Create `web/lib/candidates.php`: authenticated member lookup, opposite-gender query, joined-row grouping, age calculation, and public allowlist.
- Create `tests/candidate_test.php`: pure payload, gender, malformed-card, photo grouping, and query-contract tests.
- Modify `tests/run.php`: load the candidate test suite.
- Replace `web/app/index.php`: authenticated browse page and strict server-rendered public card markup.
- Create `web/assets/browse.css`: desktop layout A, mobile layout, candidate/photo states, drag states, attribution, focus, and reduced motion.
- Create `web/assets/browse-model.js`: pure wrap and gesture-direction functions.
- Create `web/assets/browse.js`: DOM controller for cards, photos, gestures, keyboard, looping, fallback, and current candidate synchronization.
- Create `tests/browse_model_test.mjs`: executable behavior tests for circular navigation and drag thresholds.
- Modify `tests/page_contract_test.php`: authenticated page, asset versioning, public-field boundary, responsive layout, and no-Like contracts.
- Create `web/diagnostics/browse_status.php`: temporary token-protected aggregate production diagnostic; deploy only for verification and remove immediately.

### Task 1: Remove Basic Info End to End

**Files:**
- Create: `web/migrations/003_remove_basic_intro.php`
- Modify: `web/lib/registration.php`
- Modify: `web/lib/database.php`
- Modify: `web/profile/index.php`
- Modify: `web/profile/register.php`
- Modify: `web/lib/demo.php`
- Modify: `web/seeds/demo_members.php`
- Modify: `web/diagnostics/demo_seed_status.php`
- Modify: `tests/registration_test.php`
- Modify: `tests/profile_contract_test.php`
- Modify: `tests/demo_test.php`
- Modify: `tests/page_contract_test.php`

- [ ] **Step 1: Rewrite the failing contracts around the removed field**

Remove every `basic_intro` input from the registration test fixtures and delete the former 50-character validation test. Replace the profile-field contract with:

```php
test('profile form contains only approved personal fields', function () use ($profileRoot): void {
    $source = file_get_contents($profileRoot.'/web/profile/index.php');

    foreach (['display_name', 'birth_date', 'gender', 'photos[]'] as $field) {
        expect_same(true, str_contains($source, $field));
    }
    foreach ([
        'basic_intro',
        '工作、居住地等短文字介紹（50字內）',
        '有興趣的對象',
        '我想尋找',
        '是否在個人資料顯示性別',
    ] as $excluded) {
        expect_same(false, str_contains($source, $excluded));
    }
});
```

Delete `profile form requires the public fifty-character introduction`. In `tests/demo_test.php`, remove the `basic_intro` input from the public-payload fixture and replace the manifest assertion with:

```php
foreach ($manifest as $member) {
    expect_same(2, count($member['photos']));
    expect_same(false, array_key_exists('basic_intro', $member));
    expect_same(true, $member['is_demo']);
}
```

Add this migration/runtime contract to `tests/page_contract_test.php`:

```php
test('third migration and current runtime remove basic info completely', function () use ($root): void {
    $migration = file_get_contents(
        $root.'/web/migrations/003_remove_basic_intro.php'
    );
    expect_same(true, str_contains($migration, "DROP COLUMN basic_intro"));
    expect_same(true, str_contains($migration, 'information_schema.COLUMNS'));

    foreach ([
        'web/lib/registration.php',
        'web/lib/database.php',
        'web/profile/index.php',
        'web/profile/register.php',
        'web/lib/demo.php',
        'web/seeds/demo_members.php',
        'web/diagnostics/demo_seed_status.php',
    ] as $relativePath) {
        $source = file_get_contents($root.'/'.$relativePath);
        expect_same(false, str_contains($source, 'basic_intro'));
    }
});
```

Remove `basic_intro VARCHAR(50)` from the historical migration-002 expectations while leaving the actual append-only migration file unchanged. Remove `invalid_intro_count` from the Demo diagnostic expectation.

- [ ] **Step 2: Run the PHP suite and verify removal contracts fail**

Run:

```bash
lean-ctx -c --raw php tests/run.php
```

Expected: failures because registration, onboarding, Demo runtime, and the database still contain `basic_intro`, and migration 003 is absent.

- [ ] **Step 3: Remove the field from registration and onboarding**

In `web/lib/registration.php`, remove `$basicIntro` and its required/length validation so the function validates only display name, birthday, age, and binary gender. In `web/profile/register.php`, use exactly:

```php
$input = [
    'display_name' => trim((string) ($_POST['display_name'] ?? '')),
    'birth_date' => (string) ($_POST['birth_date'] ?? ''),
    'gender' => (string) ($_POST['gender'] ?? ''),
];
```

In `web/profile/index.php`, remove the `$basicIntro` variable and the entire `<label>` whose input is named `basic_intro`. Keep the existing name, readonly email, birthday, gender, and 2–6 photo fields.

In `web/lib/database.php`, remove `$basicIntro` and change the member insert to:

```php
$userStatement = $database->prepare(
    'INSERT INTO users '
    .'(google_sub, email, display_name, birth_date, gender) '
    .'VALUES (?, ?, ?, ?, ?)'
);
$userStatement->bind_param(
    'sssss',
    $googleSub,
    $email,
    $displayName,
    $birthDate,
    $gender
);
```

- [ ] **Step 4: Remove the field from Demo data and persistence**

In `web/seeds/demo_members.php`, delete all twenty `'basic_intro' => '...',` entries and keep the remaining English names, photos, cohorts, and private Agent Profiles unchanged.

In `web/lib/demo.php`:

- remove `basic_intro` from `ainder_public_candidate_payload()`;
- validate only `display_name` in the English member-field loop;
- delete the 50-character introduction check;
- remove `$basicIntro` from `ainder_upsert_demo_user()`;
- replace the Demo user upsert with:

```php
$statement = $database->prepare(
    'INSERT INTO users '
    .'(google_sub, email, display_name, birth_date, gender, is_demo) '
    .'VALUES (?, ?, ?, ?, ?, ?) '
    .'ON DUPLICATE KEY UPDATE '
    .'id = LAST_INSERT_ID(id), email = VALUES(email), '
    .'display_name = VALUES(display_name), birth_date = VALUES(birth_date), '
    .'gender = VALUES(gender), is_demo = 1, status = \'active\''
);
$statement->bind_param(
    'sssssi',
    $googleSub,
    $email,
    $displayName,
    $birthDate,
    $gender,
    $isDemo
);
```

In `web/diagnostics/demo_seed_status.php`, remove the `invalid_intro_count` query and response key.

- [ ] **Step 5: Add the rerunnable removal migration**

Create `web/migrations/003_remove_basic_intro.php`:

```php
<?php

declare(strict_types=1);

$localPath = dirname(__DIR__).'/config.local.php';
if (!is_file($localPath)) {
    http_response_code(503);
    exit('Migration configuration unavailable.');
}
$local = require $localPath;
$providedToken = PHP_SAPI === 'cli'
    ? (string) ($argv[1] ?? '')
    : (string) ($_POST['token'] ?? '');
$expectedToken = (string) ($local['migration_token'] ?? '');
if ($expectedToken === ''
    || $providedToken === ''
    || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    exit('Forbidden');
}

if (!defined('SWEETY_MYSQL_CONFIG_ONLY')) {
    define('SWEETY_MYSQL_CONFIG_ONLY', true);
}
require dirname(__DIR__, 2).'/mysql.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $database = new mysqli($mysqlhost, $mysqluser, $mysqlpasswd, 'ainder');
    $database->set_charset('utf8mb4');
    $schema = 'ainder';
    $table = 'users';
    $column = 'basic_intro';
    $statement = $database->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        .'WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? '
        .'LIMIT 1'
    );
    $statement->bind_param('sss', $schema, $table, $column);
    $statement->execute();
    if ($statement->get_result()->fetch_row() !== null) {
        $database->query('ALTER TABLE users DROP COLUMN basic_intro');
    }
} catch (Throwable) {
    http_response_code(503);
    exit('Migration failed.');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok' => true,
    'migration' => '003_remove_basic_intro',
], JSON_UNESCAPED_SLASHES);
```

- [ ] **Step 6: Run the removal tests, source scan, and lint**

Run:

```bash
lean-ctx -c --raw php tests/run.php
lean-ctx -c --raw php -l web/migrations/003_remove_basic_intro.php
lean-ctx -c 'rg -n "basic_intro|工作、居住地等短文字介紹（50字內）" web tests'
lean-ctx -c 'git diff --check'
```

Expected: all tests pass; lint passes; the source scan reports only the intentional migration-002 add and migration-003 drop references plus the page-contract assertions that verify removal.

- [ ] **Step 7: Commit the complete removal**

```bash
lean-ctx -c 'git add web/migrations/003_remove_basic_intro.php web/lib/registration.php web/lib/database.php web/profile/index.php web/profile/register.php web/lib/demo.php web/seeds/demo_members.php web/diagnostics/demo_seed_status.php tests/registration_test.php tests/profile_contract_test.php tests/demo_test.php tests/page_contract_test.php'
lean-ctx -c 'git commit -m "refactor: remove Ainder basic info"'
```

### Task 2: Public Candidate Repository

**Files:**
- Create: `tests/candidate_test.php`
- Modify: `tests/run.php`
- Create: `web/lib/candidates.php`

- [ ] **Step 1: Add the candidate tests to the PHP runner**

Insert before `demo_test.php` in `tests/run.php`:

```php
require __DIR__.'/candidate_test.php';
```

- [ ] **Step 2: Write the failing repository tests**

Create `tests/candidate_test.php`:

```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/candidates.php';

test('candidate gender is always opposite the viewer', function (): void {
    expect_same('female', ainder_candidate_gender('male'));
    expect_same('male', ainder_candidate_gender('female'));

    try {
        ainder_candidate_gender('other');
        throw new RuntimeException('Expected invalid gender rejection.');
    } catch (InvalidArgumentException) {
        expect_same(true, true);
    }
});

test('joined candidate rows become a strict public card', function (): void {
    $rows = [
        [
            'id' => 8,
            'display_name' => 'Maya Zhou',
            'birth_date' => '1994-02-07',
            'is_demo' => 1,
            'file_path' => 'https://images.unsplash.com/photo-one',
            'sort_order' => 1,
            'source_type' => 'unsplash',
            'photographer_name' => 'Alex',
            'photographer_url' => 'https://unsplash.com/@alex',
            'source_page_url' => 'https://unsplash.com/photos/one',
        ],
        [
            'id' => 8,
            'display_name' => 'Maya Zhou',
            'birth_date' => '1994-02-07',
            'is_demo' => 1,
            'file_path' => 'https://images.unsplash.com/photo-two',
            'sort_order' => 2,
            'source_type' => 'unsplash',
            'photographer_name' => 'Blair',
            'photographer_url' => 'https://unsplash.com/@blair',
            'source_page_url' => 'https://unsplash.com/photos/two',
        ],
    ];

    $cards = ainder_candidate_cards_from_rows(
        $rows,
        new DateTimeImmutable('2026-09-01 00:00:00')
    );

    expect_same(1, count($cards));
    expect_same([
        'id',
        'display_name',
        'age',
        'is_demo',
        'photos',
    ], array_keys($cards[0]));
    expect_same(32, $cards[0]['age']);
    expect_same(2, count($cards[0]['photos']));
    expect_same(false, array_key_exists('birth_date', $cards[0]));
    expect_same(false, array_key_exists('gender', $cards[0]));
    expect_same(false, array_key_exists('basic_intro', $cards[0]));
    expect_same(false, array_key_exists('profile_text', $cards[0]));
});

test('candidate grouping preserves two through six ordered photos', function (): void {
    $rows = [];
    foreach ([4, 2, 3, 1] as $order) {
        $rows[] = [
            'id' => 9,
            'display_name' => 'Emma Blake',
            'birth_date' => '1988-04-04',
            'is_demo' => 0,
            'file_path' => "/ainder/uploads/9/{$order}.webp",
            'sort_order' => $order,
            'source_type' => 'local',
            'photographer_name' => null,
            'photographer_url' => null,
            'source_page_url' => null,
        ];
    }

    $cards = ainder_candidate_cards_from_rows(
        $rows,
        new DateTimeImmutable('2026-09-01 00:00:00')
    );

    expect_same([1, 2, 3, 4], array_column($cards[0]['photos'], 'sort_order'));
});

test('malformed candidates are omitted from public cards', function (): void {
    $row = [
        'id' => 10,
        'display_name' => 'Incomplete',
        'birth_date' => '1990-01-01',
        'is_demo' => 0,
        'file_path' => '/ainder/uploads/10/1.webp',
        'sort_order' => 1,
        'source_type' => 'local',
        'photographer_name' => null,
        'photographer_url' => null,
        'source_page_url' => null,
    ];

    expect_same([], ainder_candidate_cards_from_rows(
        [$row],
        new DateTimeImmutable('2026-09-01 00:00:00')
    ));
});

test('candidate SQL uses only active status and opposite gender', function (): void {
    $source = file_get_contents(dirname(__DIR__).'/web/lib/candidates.php');

    foreach ([
        "u.status = 'active'",
        'u.gender = ?',
        'ORDER BY u.id, p.sort_order',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }

    expect_same(false, str_contains($source, 'u.id <>'));
    expect_same(false, str_contains($source, 'agent_profiles'));
});
```

- [ ] **Step 3: Run the PHP tests and verify the new suite fails**

Run:

```bash
lean-ctx -c --raw php tests/run.php
```

Expected: failure because `web/lib/candidates.php` does not exist or `ainder_candidate_gender()` is undefined.

- [ ] **Step 4: Implement the candidate repository**

Create `web/lib/candidates.php`:

```php
<?php

declare(strict_types=1);

function ainder_candidate_gender(string $viewerGender): string
{
    return match ($viewerGender) {
        'male' => 'female',
        'female' => 'male',
        default => throw new InvalidArgumentException('Invalid member gender.'),
    };
}

function ainder_find_browse_member(mysqli $database, int $memberId): ?array
{
    $statement = $database->prepare(
        'SELECT u.id, u.display_name, u.gender, u.status, '
        .'p.file_path AS avatar_path '
        .'FROM users u LEFT JOIN user_photos p '
        .'ON p.user_id = u.id AND p.sort_order = 1 '
        .'WHERE u.id = ? LIMIT 1'
    );
    $statement->bind_param('i', $memberId);
    $statement->execute();
    $member = $statement->get_result()->fetch_assoc();

    return is_array($member) ? $member : null;
}

function ainder_candidate_cards_from_rows(
    array $rows,
    DateTimeImmutable $now
): array {
    $grouped = [];

    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id < 1) {
            continue;
        }
        if (!isset($grouped[$id])) {
            $birthDate = DateTimeImmutable::createFromFormat(
                '!Y-m-d',
                (string) ($row['birth_date'] ?? '')
            );
            $dateErrors = DateTimeImmutable::getLastErrors();
            $validDate = $birthDate
                && ($dateErrors === false
                    || ($dateErrors['warning_count'] === 0
                        && $dateErrors['error_count'] === 0));

            $grouped[$id] = [
                'id' => $id,
                'display_name' => trim((string) ($row['display_name'] ?? '')),
                'age' => $validDate ? $birthDate->diff($now)->y : 0,
                'is_demo' => (int) ($row['is_demo'] ?? 0) === 1,
                'photos' => [],
            ];
        }

        $path = trim((string) ($row['file_path'] ?? ''));
        $order = (int) ($row['sort_order'] ?? 0);
        if ($path === '' || $order < 1) {
            continue;
        }
        $grouped[$id]['photos'][] = [
            'file_path' => $path,
            'sort_order' => $order,
            'source_type' => (string) ($row['source_type'] ?? 'local'),
            'photographer_name' => (string) ($row['photographer_name'] ?? ''),
            'photographer_url' => (string) ($row['photographer_url'] ?? ''),
            'source_page_url' => (string) ($row['source_page_url'] ?? ''),
        ];
    }

    $cards = [];
    foreach ($grouped as $card) {
        usort(
            $card['photos'],
            static fn (array $left, array $right): int =>
                $left['sort_order'] <=> $right['sort_order']
        );
        if ($card['display_name'] === ''
            || $card['age'] < 18
            || count($card['photos']) < 2
            || count($card['photos']) > 6) {
            continue;
        }
        $cards[] = $card;
    }

    return $cards;
}

function ainder_list_browse_candidates(
    mysqli $database,
    string $viewerGender,
    DateTimeImmutable $now
): array {
    $candidateGender = ainder_candidate_gender($viewerGender);
    $statement = $database->prepare(
        'SELECT u.id, u.display_name, u.birth_date, u.is_demo, '
        .'p.file_path, p.sort_order, p.source_type, p.photographer_name, '
        .'p.photographer_url, p.source_page_url '
        .'FROM users u INNER JOIN user_photos p ON p.user_id = u.id '
        ."WHERE u.status = 'active' AND u.gender = ? "
        .'ORDER BY u.id, p.sort_order'
    );
    $statement->bind_param('s', $candidateGender);
    $statement->execute();
    $rows = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
    $cards = ainder_candidate_cards_from_rows($rows, $now);
    shuffle($cards);

    return $cards;
}
```

- [ ] **Step 5: Run the repository tests and all existing PHP tests**

Run:

```bash
lean-ctx -c --raw php tests/run.php
```

Expected: all tests pass, including the four candidate tests.

- [ ] **Step 6: Commit the repository boundary**

```bash
lean-ctx -c 'git add web/lib/candidates.php tests/candidate_test.php tests/run.php'
lean-ctx -c 'git commit -m "feat: add Ainder candidate repository"'
```

### Task 3: Circular Navigation Model

**Files:**
- Create: `web/assets/browse-model.js`
- Create: `tests/browse_model_test.mjs`

- [ ] **Step 1: Write executable model tests**

Create `tests/browse_model_test.mjs`:

```js
import test from 'node:test';
import assert from 'node:assert/strict';
import {
  wrapIndex,
  candidateStepForDrag,
  photoIndexAfterStep,
} from '../web/assets/browse-model.js';

test('candidate indices wrap at both ends', () => {
  assert.equal(wrapIndex(10, 10), 0);
  assert.equal(wrapIndex(-1, 10), 9);
  assert.equal(wrapIndex(3, 10), 3);
});

test('left drag means next and right drag means previous', () => {
  assert.equal(candidateStepForDrag(-80, 64), 1);
  assert.equal(candidateStepForDrag(80, 64), -1);
  assert.equal(candidateStepForDrag(20, 64), 0);
});

test('photo navigation wraps without changing candidates', () => {
  assert.equal(photoIndexAfterStep(1, 1, 2), 0);
  assert.equal(photoIndexAfterStep(0, -1, 2), 1);
});
```

- [ ] **Step 2: Run the Node tests and verify they fail**

Run:

```bash
lean-ctx -c --raw node --test tests/browse_model_test.mjs
```

Expected: FAIL with `ERR_MODULE_NOT_FOUND` for `browse-model.js`.

- [ ] **Step 3: Implement the pure model**

Create `web/assets/browse-model.js`:

```js
export function wrapIndex(index, total) {
  if (!Number.isInteger(total) || total < 1) return 0;
  return ((index % total) + total) % total;
}

export function candidateStepForDrag(deltaX, threshold = 64) {
  if (deltaX <= -threshold) return 1;
  if (deltaX >= threshold) return -1;
  return 0;
}

export function photoIndexAfterStep(index, step, total) {
  return wrapIndex(index + step, total);
}
```

- [ ] **Step 4: Run model tests**

Run:

```bash
lean-ctx -c --raw node --test tests/browse_model_test.mjs
```

Expected: 3 tests pass.

- [ ] **Step 5: Commit the model**

```bash
lean-ctx -c 'git add web/assets/browse-model.js tests/browse_model_test.mjs'
lean-ctx -c 'git commit -m "feat: add circular browse model"'
```

### Task 4: Authenticated Browse Markup

**Files:**
- Modify: `tests/page_contract_test.php`
- Replace: `web/app/index.php`

- [ ] **Step 1: Write the failing page contract**

Append to `tests/page_contract_test.php`:

```php
test('authenticated app renders the approved public browse surface', function () use ($root): void {
    $source = file_get_contents($root.'/web/app/index.php');

    foreach ([
        'ainder_member_id',
        'ainder_find_browse_member',
        'ainder_list_browse_candidates',
        'candidate-browser',
        'Agent Likes',
        'Messages',
        'data-candidate-id',
        'data-current-candidate-id',
        'browse.css?v=',
        'browse-model.js?v=',
        'browse.js?v=',
        'aria-live',
        'Photo by',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }

    foreach ([
        'profile_text',
        'basic_intro',
        'agent_known_duration_days',
        'interaction_density',
        'compatibility',
        'like_candidate',
        'LIKE',
    ] as $forbidden) {
        expect_same(false, str_contains($source, $forbidden));
    }
});
```

- [ ] **Step 2: Run the PHP tests and verify the contract fails**

Run:

```bash
lean-ctx -c --raw php tests/run.php
```

Expected: FAIL because `web/app/index.php` still renders the placeholder.

- [ ] **Step 3: Replace the authenticated placeholder with server-rendered cards**

Replace `web/app/index.php` with a strict PHP page that performs this sequence:

```php
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
?>
```

Render semantic HTML with these exact structural contracts after the bootstrap:

```php
<!doctype html>
<html lang="zh-Hant" data-current-candidate-id="<?= $candidates === [] ? '' : (int) $candidates[0]['id'] ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d0e13">
    <title>Ainder</title>
    <link rel="stylesheet" href="/ainder/assets/browse.css?v=<?= $assetVersion('/assets/browse.css') ?>">
    <script type="importmap">{"imports":{"ainder-browse-model":"/ainder/assets/browse-model.js?v=<?= $assetVersion('/assets/browse-model.js') ?>"}}</script>
    <script type="module" src="/ainder/assets/browse.js?v=<?= $assetVersion('/assets/browse.js') ?>"></script>
</head>
<body class="browse-page">
<main class="candidate-browser" data-current-candidate-id="<?= $candidates === [] ? '' : (int) $candidates[0]['id'] ?>">
    <aside class="browse-sidebar" aria-label="Ainder navigation">
        <div class="member-bar">
            <img src="<?= $escape($member['avatar_path'] ?: '/ainder/assets/ainder-logo-white.webp') ?>" alt="">
            <img class="sidebar-logo" src="/ainder/assets/ainder-logo-white.webp" alt="Ainder">
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
            <img src="<?= $escape($member['avatar_path'] ?: '/ainder/assets/ainder-logo-white.webp') ?>" alt="會員資料">
        </header>
        <?php if ($candidates === []): ?>
            <div class="candidate-empty"><h1>目前沒有可瀏覽的會員</h1><p>請稍後再回來看看。</p></div>
        <?php else: ?>
            <button class="candidate-control candidate-next" type="button" aria-label="下一位候選人">‹</button>
            <div class="candidate-stack">
                <?php foreach ($candidates as $candidateIndex => $candidate): ?>
                    <article class="candidate-card<?= $candidateIndex === 0 ? ' is-current' : '' ?>" data-candidate-id="<?= (int) $candidate['id'] ?>" aria-hidden="<?= $candidateIndex === 0 ? 'false' : 'true' ?>">
                        <div class="photo-fallback" aria-hidden="true">Ainder</div>
                        <div class="photo-segments" aria-hidden="true">
                            <?php foreach ($candidate['photos'] as $photoIndex => $_): ?><i class="<?= $photoIndex === 0 ? 'is-active' : '' ?>"></i><?php endforeach; ?>
                        </div>
                        <?php foreach ($candidate['photos'] as $photoIndex => $photo): ?>
                            <figure class="candidate-photo<?= $photoIndex === 0 ? ' is-active' : '' ?>" data-photo-index="<?= $photoIndex ?>">
                                <img src="<?= $escape($photo['file_path']) ?>" alt="<?= $escape($candidate['display_name']) ?> 的照片 <?= $photoIndex + 1 ?>">
                                <?php if ($photo['source_type'] === 'unsplash'): ?>
                                    <figcaption>Photo by <a href="<?= $escape($photo['photographer_url']) ?>" target="_blank" rel="noopener noreferrer"><?= $escape($photo['photographer_name']) ?></a> on <a href="<?= $escape($photo['source_page_url']) ?>" target="_blank" rel="noopener noreferrer">Unsplash</a></figcaption>
                                <?php endif; ?>
                            </figure>
                        <?php endforeach; ?>
                        <button class="photo-zone photo-previous" type="button" aria-label="上一張照片"></button>
                        <button class="photo-zone photo-next" type="button" aria-label="下一張照片"></button>
                        <div class="candidate-shade"></div>
                        <div class="candidate-copy"><h1><?= $escape($candidate['display_name']) ?> <span><?= (int) $candidate['age'] ?></span></h1></div>
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
```

- [ ] **Step 4: Run PHP lint and page contracts**

Run:

```bash
lean-ctx -c --raw php -l web/app/index.php
lean-ctx -c --raw php tests/run.php
```

Expected: lint passes; the new page contract passes. Asset existence contracts remain pending until Task 5.

- [ ] **Step 5: Commit the authenticated markup**

```bash
lean-ctx -c 'git add web/app/index.php tests/page_contract_test.php'
lean-ctx -c 'git commit -m "feat: render Ainder candidate browser"'
```

### Task 5: Swipe Controller and Responsive Visual System

**Files:**
- Create: `web/assets/browse.js`
- Create: `web/assets/browse.css`
- Modify: `tests/page_contract_test.php`

- [ ] **Step 1: Add failing asset contracts**

Append to `tests/page_contract_test.php`:

```php
test('browse assets implement gestures looping and responsive layout', function () use ($root): void {
    $script = file_get_contents($root.'/web/assets/browse.js');
    $style = file_get_contents($root.'/web/assets/browse.css');

    foreach ([
        'pointerdown',
        'pointermove',
        'pointerup',
        'ArrowLeft',
        'ArrowRight',
        'candidateStepForDrag',
        'data-current-candidate-id',
        'prefers-reduced-motion',
    ] as $needle) {
        expect_same(true, str_contains($script.$style, $needle));
    }

    foreach ([
        '.browse-sidebar',
        '.candidate-card',
        '.mobile-bar',
        '@media (max-width: 720px)',
        'overflow-x: hidden',
    ] as $needle) {
        expect_same(true, str_contains($style, $needle));
    }

    expect_same(false, preg_match('/like|heart|super.?like/i', $script) === 1);
});
```

- [ ] **Step 2: Verify the asset contract fails**

Run:

```bash
lean-ctx -c --raw php tests/run.php
```

Expected: FAIL because `browse.js` and `browse.css` do not exist.

- [ ] **Step 3: Implement the browser controller**

Create `web/assets/browse.js` as an ES module importing `wrapIndex`, `candidateStepForDrag`, and `photoIndexAfterStep` through the page's versioned import map. Implement these exact responsibilities:

```js
import {
  wrapIndex,
  candidateStepForDrag,
  photoIndexAfterStep,
} from 'ainder-browse-model';

const browser = document.querySelector('.candidate-browser');
const stack = document.querySelector('.candidate-stack');
const cards = [...document.querySelectorAll('.candidate-card')];
const status = document.querySelector('[data-candidate-status]');
let candidateIndex = 0;
let pointerStart = null;
let pointerDelta = 0;
let suppressClick = false;

function currentCard() { return cards[candidateIndex] ?? null; }

function setCurrentCandidate(nextIndex, animateFrom = 0) {
  if (cards.length === 0) return;
  candidateIndex = wrapIndex(nextIndex, cards.length);
  cards.forEach((card, index) => {
    const active = index === candidateIndex;
    card.classList.toggle('is-current', active);
    card.setAttribute('aria-hidden', String(!active));
    card.style.removeProperty('transform');
  });
  const card = currentCard();
  const id = card?.dataset.candidateId ?? '';
  browser.dataset.currentCandidateId = id;
  document.documentElement.dataset.currentCandidateId = id;
  if (status && card) {
    const name = card.querySelector('.candidate-copy h1')?.textContent?.trim() ?? '';
    status.textContent = `目前顯示 ${name}`;
  }
  if (animateFrom !== 0 && card) {
    card.animate(
      [{ transform: `translateX(${animateFrom}px)`, opacity: .65 }, { transform: 'translateX(0)', opacity: 1 }],
      { duration: 220, easing: 'cubic-bezier(.2,.75,.25,1)' }
    );
  }
}

function moveCandidate(step) {
  setCurrentCandidate(candidateIndex + step, step > 0 ? 80 : -80);
}

function activePhoto(card) {
  return [...card.querySelectorAll('.candidate-photo')]
    .findIndex(photo => photo.classList.contains('is-active'));
}

function showPhoto(card, requestedIndex) {
  const photos = [...card.querySelectorAll('.candidate-photo')];
  const segments = [...card.querySelectorAll('.photo-segments i')];
  if (photos.length === 0) return;
  const index = wrapIndex(requestedIndex, photos.length);
  photos.forEach((photo, photoIndex) => photo.classList.toggle('is-active', photoIndex === index));
  segments.forEach((segment, photoIndex) => segment.classList.toggle('is-active', photoIndex === index));
}

function movePhoto(card, step) {
  const photos = card.querySelectorAll('.candidate-photo');
  showPhoto(card, photoIndexAfterStep(activePhoto(card), step, photos.length));
}

document.querySelector('.candidate-next')?.addEventListener('click', () => moveCandidate(1));
document.querySelector('.candidate-previous')?.addEventListener('click', () => moveCandidate(-1));
document.addEventListener('keydown', event => {
  if (event.key === 'ArrowLeft') moveCandidate(1);
  if (event.key === 'ArrowRight') moveCandidate(-1);
});
cards.forEach(card => {
  card.querySelector('.photo-previous')?.addEventListener('click', event => {
    event.stopPropagation(); movePhoto(card, -1);
  });
  card.querySelector('.photo-next')?.addEventListener('click', event => {
    event.stopPropagation(); movePhoto(card, 1);
  });
  card.querySelectorAll('.candidate-photo img').forEach(image => {
    image.addEventListener('error', () => {
      image.closest('.candidate-photo')?.classList.add('has-error');
      const available = [...card.querySelectorAll('.candidate-photo:not(.has-error)')];
      if (available.length > 0) showPhoto(card, Number(available[0].dataset.photoIndex));
      card.classList.toggle('all-photos-failed', available.length === 0);
    });
  });
});

stack?.addEventListener('pointerdown', event => {
  pointerStart = event.clientX;
  pointerDelta = 0;
  stack.setPointerCapture(event.pointerId);
  currentCard()?.classList.add('is-dragging');
});
stack?.addEventListener('pointermove', event => {
  if (pointerStart === null) return;
  pointerDelta = event.clientX - pointerStart;
  const card = currentCard();
  if (card) card.style.transform = `translateX(${pointerDelta}px) rotate(${pointerDelta / 35}deg)`;
});
function finishPointer(cancelled = false) {
  if (pointerStart === null) return;
  const step = cancelled ? 0 : candidateStepForDrag(pointerDelta, 64);
  currentCard()?.classList.remove('is-dragging');
  currentCard()?.style.removeProperty('transform');
  pointerStart = null;
  pointerDelta = 0;
  suppressClick = step !== 0;
  if (step !== 0) {
    moveCandidate(step);
    setTimeout(() => { suppressClick = false; }, 0);
  }
}
stack?.addEventListener('pointerup', () => finishPointer(false));
stack?.addEventListener('pointercancel', () => finishPointer(true));
stack?.addEventListener('click', event => {
  if (!suppressClick) return;
  event.preventDefault();
  event.stopPropagation();
  suppressClick = false;
}, true);

setCurrentCandidate(0);
```

- [ ] **Step 4: Implement the dedicated browse stylesheet**

Create `web/assets/browse.css` with Ainder tokens and these required rules. Keep selectors browse-specific and do not modify `app.css`:

```css
:root { color-scheme: dark; font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #0b0c11; color: #f7f5f2; }
* { box-sizing: border-box; }
html, body { width: 100%; min-height: 100%; margin: 0; overflow-x: hidden; background: #0b0c11; color: #f7f5f2; }
button, a { font: inherit; }
.browse-page { min-height: 100vh; min-height: 100dvh; }
.candidate-browser { min-height: 100vh; min-height: 100dvh; display: grid; grid-template-columns: 286px minmax(0, 1fr); }
.browse-sidebar { min-height: 100dvh; border-right: 1px solid #292b36; background: #14151b; }
.member-bar { min-height: 72px; display: flex; align-items: center; gap: 14px; padding: 12px 18px; background: linear-gradient(135deg, #5f204f, #b33d70); }
.member-bar > img:first-child { width: 42px; height: 42px; border: 2px solid #fff; border-radius: 50%; object-fit: cover; }
.sidebar-logo { width: 108px; height: auto; }
.sidebar-tabs { display: flex; gap: 22px; padding: 18px 18px 0; border-bottom: 1px solid #292b36; }
.sidebar-tabs button { padding: 0 0 12px; border: 0; border-bottom: 2px solid transparent; background: none; color: #9a9daa; font-size: 13px; font-weight: 800; cursor: default; }
.sidebar-tabs button[aria-selected="true"] { border-color: #e84d7d; color: #fff; }
.sidebar-empty { padding: 58px 24px; text-align: center; color: #9a9daa; }
.sidebar-empty h2 { margin: 18px 0 8px; color: #fff; font-size: 18px; }
.sidebar-empty p { margin: 0; line-height: 1.55; }
.agent-symbol { width: 72px; height: 72px; display: grid; place-items: center; margin: auto; border-radius: 24px; background: linear-gradient(135deg, #52254b, #e14d7e); color: #fff; font-size: 28px; }
.browse-stage { position: relative; min-width: 0; min-height: 100dvh; display: grid; place-items: center; padding: 34px 92px 52px; overflow: hidden; background: radial-gradient(circle at 50% 22%, #282a36, #0a0b10 62%); }
.mobile-bar { display: none; }
.candidate-stack { position: relative; width: min(430px, calc((100dvh - 96px) * .69)); aspect-ratio: .69; touch-action: pan-y; }
.candidate-card { position: absolute; inset: 0; overflow: hidden; border: 1px solid rgba(255,255,255,.1); border-radius: 22px; background: #20222b; opacity: 0; pointer-events: none; box-shadow: 0 26px 72px rgba(0,0,0,.46); transition: transform 180ms ease, opacity 180ms ease; }
.candidate-card.is-current { z-index: 2; opacity: 1; pointer-events: auto; }
.candidate-card.is-dragging { transition: none; cursor: grabbing; }
.candidate-photo { position: absolute; inset: 0; display: none; margin: 0; }
.candidate-photo.is-active { display: block; }
.candidate-photo.has-error { display: none; }
.candidate-photo img { width: 100%; height: 100%; object-fit: cover; }
.candidate-photo figcaption { position: absolute; z-index: 5; top: 28px; right: 12px; color: rgba(255,255,255,.78); font-size: 10px; }
.candidate-photo figcaption a { color: inherit; }
.photo-fallback { position: absolute; inset: 0; display: grid; place-items: center; background: radial-gradient(circle at 50% 35%, #493047, #191a21 70%); color: rgba(255,255,255,.35); font-family: Georgia, serif; font-size: 42px; }
.photo-segments { position: absolute; z-index: 6; top: 10px; left: 10px; right: 10px; display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; gap: 5px; }
.photo-segments i { height: 3px; border-radius: 99px; background: rgba(255,255,255,.35); }
.photo-segments i.is-active { background: #fff; }
.photo-zone { position: absolute; z-index: 4; top: 0; bottom: 0; width: 34%; border: 0; background: transparent; color: transparent; }
.photo-previous { left: 0; }
.photo-next { right: 0; }
.candidate-shade { position: absolute; z-index: 3; inset: 42% 0 0; background: linear-gradient(transparent, rgba(7,8,12,.9)); pointer-events: none; }
.candidate-copy { position: absolute; z-index: 5; left: 22px; right: 22px; bottom: 28px; pointer-events: none; }
.candidate-copy h1 { margin: 0; font-size: clamp(24px, 2.5vw, 34px); letter-spacing: -.025em; }
.candidate-copy h1 span { font-weight: 450; }
.candidate-control { position: absolute; z-index: 8; top: 50%; width: 52px; height: 52px; display: grid; place-items: center; border: 1px solid #3b3e4b; border-radius: 50%; background: #171820; color: #fff; font-size: 30px; cursor: pointer; transform: translateY(-50%); }
.candidate-control:focus-visible, .sidebar-tabs button:focus-visible, .photo-zone:focus-visible { outline: 3px solid #ff83a9; outline-offset: 3px; }
.candidate-next { left: max(22px, calc(50% - 300px)); }
.candidate-previous { right: max(22px, calc(50% - 300px)); }
.browse-hint { position: absolute; bottom: 18px; margin: 0; color: #777a87; font-size: 11px; letter-spacing: .08em; }
.candidate-empty { text-align: center; color: #9a9daa; }
.candidate-empty h1 { color: #fff; }
.visually-hidden { position: absolute !important; width: 1px !important; height: 1px !important; overflow: hidden !important; clip: rect(0,0,0,0) !important; white-space: nowrap !important; }
@media (max-height: 720px) and (min-width: 721px) { .candidate-stack { width: min(360px, calc((100dvh - 68px) * .69)); } .browse-stage { padding-top: 20px; padding-bottom: 34px; } }
@media (max-width: 720px) {
  .candidate-browser { display: block; }
  .browse-sidebar { display: none; }
  .browse-stage { min-height: 100dvh; display: block; padding: 0; background: #0b0c11; }
  .mobile-bar { position: relative; z-index: 10; height: 58px; display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; background: #111219; }
  .mobile-bar img:first-child { width: 110px; height: auto; }
  .mobile-bar img:last-child { width: 34px; height: 34px; border-radius: 50%; object-fit: cover; }
  .candidate-stack { width: 100%; height: calc(100dvh - 58px); aspect-ratio: auto; }
  .candidate-card { border: 0; border-radius: 0; box-shadow: none; }
  .candidate-copy { bottom: calc(28px + env(safe-area-inset-bottom)); }
  .candidate-control, .browse-hint { display: none; }
}
@media (prefers-reduced-motion: reduce) { .candidate-card { transition: none; } }
```

- [ ] **Step 5: Run all page and model tests**

Run:

```bash
lean-ctx -c --raw php tests/run.php
lean-ctx -c --raw node --test tests/browse_model_test.mjs
lean-ctx -c --raw php -l web/app/index.php
lean-ctx -c 'git diff --check'
```

Expected: all PHP tests and all 3 Node tests pass; lint and diff checks succeed.

- [ ] **Step 6: Perform local responsive visual verification**

Run the PHP site through the existing local/remote configuration or a production-safe preview, then inspect at:

```text
Desktop: 1440×900 and 1280×720
Mobile: 390×844 and 360×800
```

Verify the approved layout A proportions, image crops, no horizontal overflow, card loop, photo controls, pointer threshold, keyboard directions, missing-image fallback, and no Like affordance. Capture screenshots for comparison with the approved mockup.

- [ ] **Step 7: Commit the interactions and responsive design**

```bash
lean-ctx -c 'git add web/assets/browse.js web/assets/browse.css tests/page_contract_test.php'
lean-ctx -c 'git commit -m "feat: add Ainder circular swipe interface"'
```

### Task 6: Production Aggregate Diagnostic

**Files:**
- Create: `web/diagnostics/browse_status.php`
- Modify: `tests/page_contract_test.php`

- [ ] **Step 1: Add the diagnostic privacy contract**

Append to `tests/page_contract_test.php`:

```php
test('browse diagnostic is token protected and aggregate only', function () use ($root): void {
    $source = file_get_contents($root.'/web/diagnostics/browse_status.php');

    foreach ([
        'hash_equals',
        'male_view_candidates',
        'female_view_candidates',
        'demo_female_candidates',
        'demo_male_candidates',
        'basic_intro_column_exists',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
    foreach (['display_name', 'basic_intro', 'profile_text', 'google_sub'] as $forbidden) {
        expect_same(false, str_contains($source, $forbidden));
    }
});
```

- [ ] **Step 2: Verify the contract fails**

Run:

```bash
lean-ctx -c --raw php tests/run.php
```

Expected: FAIL because `browse_status.php` does not exist.

- [ ] **Step 3: Implement the temporary aggregate diagnostic**

Create `web/diagnostics/browse_status.php` using the existing POST-only `migration_token` pattern:

```json
{
  "male_view_candidates": 10,
  "female_view_candidates": 11,
  "demo_female_candidates": 10,
  "demo_male_candidates": 10,
  "basic_intro_column_exists": 0,
  "active_candidates_without_two_photos": 0
}
```

The total candidate values include real members and therefore must not be hardcoded in verification. The two Demo values verify the seeded balance. Use the complete endpoint:

```php
<?php

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

$localPath = dirname(__DIR__).'/config.local.php';
if (!is_file($localPath)) {
    http_response_code(503);
    exit('Diagnostic configuration unavailable.');
}
$local = require $localPath;
$providedToken = (string) ($_POST['token'] ?? '');
$expectedToken = (string) ($local['migration_token'] ?? '');
if ($providedToken === ''
    || $expectedToken === ''
    || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    exit('Forbidden');
}

require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/database.php';

$count = static function (mysqli $database, string $sql): int {
    $row = $database->query($sql)->fetch_row();
    return (int) ($row[0] ?? 0);
};

try {
    $database = ainder_database(ainder_config());
$result = [
    'male_view_candidates' => $count(
        $database,
        "SELECT COUNT(*) FROM users WHERE status = 'active' AND gender = 'female'"
    ),
    'female_view_candidates' => $count(
        $database,
        "SELECT COUNT(*) FROM users WHERE status = 'active' AND gender = 'male'"
    ),
    'demo_female_candidates' => $count(
        $database,
        "SELECT COUNT(*) FROM users WHERE status = 'active' AND gender = 'female' AND is_demo = 1"
    ),
    'demo_male_candidates' => $count(
        $database,
        "SELECT COUNT(*) FROM users WHERE status = 'active' AND gender = 'male' AND is_demo = 1"
    ),
    'basic_intro_column_exists' => $count(
        $database,
        "SELECT COUNT(*) FROM information_schema.COLUMNS "
        ."WHERE TABLE_SCHEMA = 'ainder' AND TABLE_NAME = 'users' "
        ."AND COLUMN_NAME = 'basic_intro'"
    ),
    'active_candidates_without_two_photos' => $count(
        $database,
        "SELECT COUNT(*) FROM (SELECT u.id FROM users u LEFT JOIN user_photos p "
        ."ON p.user_id = u.id WHERE u.status = 'active' GROUP BY u.id "
        ."HAVING COUNT(p.id) < 2 OR COUNT(p.id) > 6) invalid_photo_counts"
    ),
];
} catch (Throwable) {
    http_response_code(503);
    exit('Diagnostic failed.');
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_SLASHES);
```

- [ ] **Step 4: Run diagnostic contract and lint**

Run:

```bash
lean-ctx -c --raw php tests/run.php
lean-ctx -c --raw php -l web/diagnostics/browse_status.php
```

Expected: all tests pass and lint succeeds.

- [ ] **Step 5: Commit the diagnostic**

```bash
lean-ctx -c 'git add web/diagnostics/browse_status.php tests/page_contract_test.php'
lean-ctx -c 'git commit -m "test: add Ainder browse deployment diagnostic"'
```

### Task 7: Deployment and Live Verification

**Files:**
- Deploy persistently: `web/lib/registration.php`, `web/lib/database.php`, `web/lib/demo.php`, `web/profile/index.php`, `web/profile/register.php`, `web/lib/candidates.php`, `web/app/index.php`, `web/assets/browse.css`, `web/assets/browse-model.js`, `web/assets/browse.js`
- Deploy temporarily, then remove: `web/migrations/003_remove_basic_intro.php`, `web/diagnostics/browse_status.php`
- Preserve: `/Volumes/sweety.tw/ainder/config.local.php`, `/Volumes/sweety.tw/ainder/uploads/`

- [ ] **Step 1: Run the complete pre-deploy evidence loop**

```bash
lean-ctx -c --raw php tests/run.php
lean-ctx -c --raw node --test tests/browse_model_test.mjs
lean-ctx -c --raw sh -lc 'find web tests -name "*.php" -type f -print0 | xargs -0 -n1 php -l'
lean-ctx -c 'git diff --check'
lean-ctx -c 'git status --short'
```

Expected: all tests/lints pass; only user-owned `.DS_Store`, `.superpowers/`, and `pic/` may remain untracked.

- [ ] **Step 2: Inventory production targets and deploy persistent files**

Confirm `/Volumes/sweety.tw/ainder` is mounted and inventory the exact target directories. Deploy the removal-compatible runtime and browse files before dropping the column:

```bash
lean-ctx -c 'ls -la /Volumes/sweety.tw/ainder'
cp web/lib/registration.php /Volumes/sweety.tw/ainder/lib/registration.php
cp web/lib/database.php /Volumes/sweety.tw/ainder/lib/database.php
cp web/lib/demo.php /Volumes/sweety.tw/ainder/lib/demo.php
cp web/profile/index.php /Volumes/sweety.tw/ainder/profile/index.php
cp web/profile/register.php /Volumes/sweety.tw/ainder/profile/register.php
cp web/lib/candidates.php /Volumes/sweety.tw/ainder/lib/candidates.php
cp web/app/index.php /Volumes/sweety.tw/ainder/app/index.php
cp web/assets/browse.css /Volumes/sweety.tw/ainder/assets/browse.css
cp web/assets/browse-model.js /Volumes/sweety.tw/ainder/assets/browse-model.js
cp web/assets/browse.js /Volumes/sweety.tw/ainder/assets/browse.js
```

Do not overwrite `config.local.php`, `.user.ini`, existing uploads, landing assets, or onboarding assets.

- [ ] **Step 3: Verify persistent file hashes**

Run SHA-256 for each local/production pair and require identical output:

```bash
shasum -a 256 web/lib/candidates.php /Volumes/sweety.tw/ainder/lib/candidates.php
shasum -a 256 web/lib/registration.php /Volumes/sweety.tw/ainder/lib/registration.php
shasum -a 256 web/lib/database.php /Volumes/sweety.tw/ainder/lib/database.php
shasum -a 256 web/lib/demo.php /Volumes/sweety.tw/ainder/lib/demo.php
shasum -a 256 web/profile/index.php /Volumes/sweety.tw/ainder/profile/index.php
shasum -a 256 web/profile/register.php /Volumes/sweety.tw/ainder/profile/register.php
shasum -a 256 web/app/index.php /Volumes/sweety.tw/ainder/app/index.php
shasum -a 256 web/assets/browse.css /Volumes/sweety.tw/ainder/assets/browse.css
shasum -a 256 web/assets/browse-model.js /Volumes/sweety.tw/ainder/assets/browse-model.js
shasum -a 256 web/assets/browse.js /Volumes/sweety.tw/ainder/assets/browse.js
```

- [ ] **Step 4: Remove the production database column**

Copy `web/migrations/003_remove_basic_intro.php` to the exact production migrations directory. POST the ignored `migration_token` by reading it inside the local PHP process so the secret never appears in command output. Require HTTP 200 and:

```json
{"ok":true,"migration":"003_remove_basic_intro"}
```

Remove only `/Volumes/sweety.tw/ainder/migrations/003_remove_basic_intro.php`, then use a cache-busted request with `Cache-Control: no-cache` and require HTTP 404.

- [ ] **Step 5: Run the temporary production diagnostic**

Create the exact production diagnostics directory if absent, copy `browse_status.php`, POST the ignored production migration token without printing it, and require HTTP 200 with:

```json
{"male_view_candidates":10,"female_view_candidates":11,"demo_female_candidates":10,"demo_male_candidates":10,"basic_intro_column_exists":0,"active_candidates_without_two_photos":0}
```

Remove only `/Volumes/sweety.tw/ainder/diagnostics/browse_status.php`, remove the directory if it is now empty and was created by this task, and verify the cache-busted diagnostic URL returns HTTP 404.

- [ ] **Step 6: Verify authenticated and unauthenticated live behavior**

Verify:

```text
GET /ainder/                         -> HTTP 200
GET /ainder/app/ without session     -> HTTP 302 to /ainder/
GET /ainder/assets/browse.css        -> HTTP 200
GET /ainder/assets/browse-model.js   -> HTTP 200
GET /ainder/assets/browse.js         -> HTTP 200
```

Using the existing signed-in real member, verify every visible card has the opposite gender, the ten opposite-gender Demo members are present, all additional real candidates also satisfy the gender rule, the final card wraps to the first, all available photos switch, `data-current-candidate-id` updates, and no Like/Match request occurs.

- [ ] **Step 7: Verify desktop and mobile rendering in a real browser**

Capture and inspect 1440×900 and 390×844 screenshots. Compare against approved layout A for sidebar width, card proportions, image crop, bottom text gradient, mobile safe areas, focus states, and overflow. Correct and re-run Tasks 5–7 if material visual drift is found.

- [ ] **Step 8: Report deployment evidence**

Report the live URL, candidate counts for both viewing genders, full test result, hash match, diagnostic removal/404, responsive browser result, and the explicit fact that swiping created no Like or Match.
