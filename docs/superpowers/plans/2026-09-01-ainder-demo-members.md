# Ainder Demo Members Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task in the current session. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add the required public 50-character introduction, mixed local/Unsplash photo records, private expiring Agent Profiles, and an idempotent production seed containing 20 balanced Demo members.

**Architecture:** Extend the existing independent `ainder` schema through a rerunnable second migration. Keep real-member registration on the current local-upload path while adding a separate Unsplash client, frozen Demo manifest, validator, and transactional seeder. Fetch and visually curate image candidates before freezing the manifest; never expose Agent Profile text in public candidate payloads and never permit a Demo participant to create a Match.

**Tech Stack:** PHP 8.2, MariaDB/MySQLi, existing PHP test runner, Unsplash JSON API, browser-based brainstorming visual companion, mounted SMB deployment, curl-based production verification.

---

## File Map

- `web/lib/registration.php` — validate `basic_intro` with the existing registration fields.
- `web/profile/index.php` — render the required public-introduction input.
- `web/profile/register.php` — pass the submitted introduction to the repository.
- `web/lib/database.php` — persist real-member introductions and explicitly mark their photos as local.
- `web/migrations/002_add_demo_members.php` — add Demo/public-intro/photo-source columns and create `agent_profiles`.
- `web/lib/demo.php` — pure Demo manifest, photo, Agent Profile freshness, public-payload, and Match-eligibility rules.
- `web/lib/unsplash.php` — server-side Unsplash search, normalization, URL allowlisting, and download tracking.
- `tools/fetch_unsplash_candidates.php` — fetch candidate portrait and lifestyle records for visual review without writing the database.
- `web/seeds/demo_members.php` — frozen fictional English member and selected Unsplash photo manifest.
- `web/seeds/demo_photo_tracking.php` — non-secret ledger of the 40 selected photo IDs whose Unsplash download endpoints were triggered once during curation.
- `web/seeds/run_demo_members.php` — temporary token-protected, transactional production seed endpoint.
- `web/config.local.example.php` — document the ignored Unsplash Access Key setting.
- `web/lib/config.php` — expose the server-side Access Key only to PHP.
- `tests/registration_test.php` — public-introduction validation tests.
- `tests/profile_contract_test.php` — onboarding field/placeholder tests.
- `tests/page_contract_test.php` — migration and secret-boundary contracts.
- `tests/demo_test.php` — Demo manifest, photo, public/private, freshness, and Match rules.
- `tests/unsplash_test.php` — Unsplash response normalization and host restrictions.
- `tests/run.php` — load the new test files.

## Task 1: Require the Public Introduction During Real Registration

**Files:**
- Modify: `web/lib/registration.php`
- Modify: `web/profile/index.php`
- Modify: `web/profile/register.php`
- Modify: `web/lib/database.php`
- Modify: `tests/registration_test.php`
- Modify: `tests/profile_contract_test.php`

- [ ] **Step 1: Write failing validation tests**

Add these assertions to `tests/registration_test.php`:

```php
test('basic intro is required and limited to fifty Unicode characters', function (): void {
    $base = [
        'display_name' => 'Eric',
        'birth_date' => '1990-01-01',
        'gender' => 'male',
        'basic_intro' => '',
    ];
    $now = new DateTimeImmutable('2026-09-01');

    $empty = ainder_validate_registration_fields($base, $now);
    expect_same(true, isset($empty['errors']['basic_intro']));

    $base['basic_intro'] = str_repeat('界', 51);
    $long = ainder_validate_registration_fields($base, $now);
    expect_same(true, isset($long['errors']['basic_intro']));

    $base['basic_intro'] = str_repeat('界', 50);
    $valid = ainder_validate_registration_fields($base, $now);
    expect_same(false, isset($valid['errors']['basic_intro']));
    expect_same($base['basic_intro'], $valid['values']['basic_intro']);
});
```

Add this test to `tests/profile_contract_test.php`:

```php
test('profile form requires the public fifty-character introduction', function () use ($profileRoot): void {
    $source = file_get_contents($profileRoot.'/web/profile/index.php');

    expect_same(true, str_contains($source, 'name="basic_intro"'));
    expect_same(true, str_contains($source, 'maxlength="50"'));
    expect_same(true, str_contains(
        $source,
        '工作、居住地等短文字介紹（50字內）'
    ));
});
```

- [ ] **Step 2: Run the tests and verify RED**

Run:

```bash
lean-ctx -c --raw php tests/run.php
```

Expected: both new tests fail because `basic_intro` is not validated or rendered.

- [ ] **Step 3: Add minimal field validation**

In `web/lib/registration.php`, normalize and validate the value alongside the existing fields:

```php
$basicIntro = trim((string) ($input['basic_intro'] ?? ''));

if ($basicIntro === '') {
    $errors['basic_intro'] = '請填寫基本資料。';
} elseif (mb_strlen($basicIntro, 'UTF-8') > 50) {
    $errors['basic_intro'] = '基本資料不可超過 50 字。';
}

$values['basic_intro'] = $basicIntro;
```

Preserve the existing name, birthday, gender, and age behavior.

- [ ] **Step 4: Render and submit the field**

In `web/profile/index.php`, place this required field with the other basic fields:

```php
<label class="field field-wide">
    <span>基本資料 <b>*</b></span>
    <input
        type="text"
        name="basic_intro"
        maxlength="50"
        required
        value="<?= htmlspecialchars((string) ($old['basic_intro'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
        placeholder="工作、居住地等短文字介紹（50字內）"
    >
    <?php if (isset($errors['basic_intro'])): ?>
        <small class="field-error"><?= htmlspecialchars($errors['basic_intro'], ENT_QUOTES, 'UTF-8') ?></small>
    <?php endif; ?>
</label>
```

In `web/profile/register.php`, include only the user-submitted public introduction in `$input`:

```php
'basic_intro' => (string) ($_POST['basic_intro'] ?? ''),
```

Do not accept email, Google sub, Demo status, or Agent Profile text from `POST`.

- [ ] **Step 5: Persist the introduction and explicit local photo source**

In `web/lib/database.php`, read `$input['basic_intro']`, add `basic_intro` to the real-user insert, and make local photo inserts explicit:

```php
'INSERT INTO users '
.'(google_sub, email, display_name, birth_date, gender, basic_intro) '
.'VALUES (?, ?, ?, ?, ?, ?)'
```

```php
'INSERT INTO user_photos (user_id, file_path, sort_order, source_type) '
.'VALUES (?, ?, ?, \'local\')'
```

Bind the six user strings in the same order as the columns.

- [ ] **Step 6: Run tests and syntax checks**

Run:

```bash
lean-ctx -c --raw sh -lc "php tests/run.php && php -l web/profile/index.php && php -l web/profile/register.php && php -l web/lib/database.php"
```

Expected: all tests pass and every file reports no syntax errors.

- [ ] **Step 7: Commit**

```bash
git add web/lib/registration.php web/profile/index.php web/profile/register.php web/lib/database.php tests/registration_test.php tests/profile_contract_test.php
git commit -m "feat: require Ainder public introductions"
```

## Task 2: Add the Demo and Agent Profile Schema

**Files:**
- Create: `web/migrations/002_add_demo_members.php`
- Modify: `tests/page_contract_test.php`

- [ ] **Step 1: Write the migration contract test**

Add to `tests/page_contract_test.php`:

```php
test('second migration adds demo photo sources and private Agent Profiles', function () use ($root): void {
    $source = file_get_contents($root.'/web/migrations/002_add_demo_members.php');

    foreach ([
        'basic_intro VARCHAR(50)',
        'is_demo TINYINT(1)',
        "source_type ENUM('local', 'unsplash')",
        'source_photo_id VARCHAR(64)',
        'photographer_name VARCHAR(160)',
        'photographer_url VARCHAR(500)',
        'source_page_url VARCHAR(500)',
        'CREATE TABLE IF NOT EXISTS agent_profiles',
        'agent_known_duration_days',
        "interaction_density ENUM('low', 'medium', 'high')",
        'UNIQUE KEY agent_profiles_user_unique',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
});
```

- [ ] **Step 2: Run the test and verify RED**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: FAIL because `002_add_demo_members.php` does not exist.

- [ ] **Step 3: Implement a rerunnable migration**

Create `web/migrations/002_add_demo_members.php` using the same config-only database bootstrap and token validation as migration 001. Select only the `ainder` database. Query `information_schema.columns` before each `ALTER TABLE` so a retry does not duplicate columns.

Use these exact definitions:

```php
$addColumn('users', 'basic_intro',
    "ALTER TABLE users ADD COLUMN basic_intro VARCHAR(50) NOT NULL DEFAULT '' AFTER gender");
$addColumn('users', 'is_demo',
    'ALTER TABLE users ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER basic_intro');
$addColumn('user_photos', 'source_type',
    "ALTER TABLE user_photos ADD COLUMN source_type ENUM('local', 'unsplash') NOT NULL DEFAULT 'local' AFTER sort_order");
$addColumn('user_photos', 'source_photo_id',
    'ALTER TABLE user_photos ADD COLUMN source_photo_id VARCHAR(64) NULL AFTER source_type');
$addColumn('user_photos', 'photographer_name',
    'ALTER TABLE user_photos ADD COLUMN photographer_name VARCHAR(160) NULL AFTER source_photo_id');
$addColumn('user_photos', 'photographer_url',
    'ALTER TABLE user_photos ADD COLUMN photographer_url VARCHAR(500) NULL AFTER photographer_name');
$addColumn('user_photos', 'source_page_url',
    'ALTER TABLE user_photos ADD COLUMN source_page_url VARCHAR(500) NULL AFTER photographer_url');
```

Create `agent_profiles` with the exact schema from the approved design. Wrap errors in a generic 503 response and return only:

```json
{"ok":true,"migration":"002_add_demo_members","tables":["users","user_photos","agent_profiles"]}
```

- [ ] **Step 4: Verify GREEN and lint**

Run:

```bash
lean-ctx -c --raw sh -lc "php tests/run.php && php -l web/migrations/002_add_demo_members.php && git diff --check"
```

Expected: all tests pass with no syntax or whitespace errors.

- [ ] **Step 5: Commit**

```bash
git add web/migrations/002_add_demo_members.php tests/page_contract_test.php
git commit -m "feat: add Ainder demo member schema"
```

## Task 3: Define Demo, Public-Payload, Freshness, and Match Rules

**Files:**
- Create: `web/lib/demo.php`
- Create: `tests/demo_test.php`
- Modify: `tests/run.php`

- [ ] **Step 1: Write failing pure-domain tests**

Create `tests/demo_test.php` with tests for these APIs:

```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/demo.php';

test('Unsplash photos require the exact CDN host and attribution', function (): void {
    $valid = [
        'source_type' => 'unsplash',
        'file_path' => 'https://images.unsplash.com/photo-123?auto=format&w=900',
        'source_photo_id' => 'photo-123',
        'photographer_name' => 'Alex Example',
        'photographer_url' => 'https://unsplash.com/@alex?utm_source=ainder&utm_medium=referral',
        'source_page_url' => 'https://unsplash.com/photos/photo-123?utm_source=ainder&utm_medium=referral',
    ];

    expect_same([], ainder_validate_demo_photo($valid));

    $valid['file_path'] = 'https://example.com/photo.jpg';
    expect_same(true, ainder_validate_demo_photo($valid) !== []);
});

test('Agent Profiles expire at their explicit expiry time', function (): void {
    $profile = ['expires_at' => '2026-12-01 00:00:00'];

    expect_same(true, ainder_agent_profile_is_fresh(
        $profile,
        new DateTimeImmutable('2026-11-30 23:59:59')
    ));
    expect_same(false, ainder_agent_profile_is_fresh(
        $profile,
        new DateTimeImmutable('2026-12-01 00:00:00')
    ));
});

test('matchmaking evaluation checks only the requester profile date', function (): void {
    $now = new DateTimeImmutable('2026-09-01 00:00:00');
    $requesterFresh = [
        'profile_text' => 'Requester profile',
        'expires_at' => '2026-12-01 00:00:00',
    ];
    $requesterStale = [
        'profile_text' => 'Requester profile',
        'expires_at' => '2026-09-01 00:00:00',
    ];
    $candidateStale = [
        'profile_text' => 'Candidate profile',
        'expires_at' => '2026-01-01 00:00:00',
    ];

    expect_same(true, ainder_profiles_allow_evaluation(
        $requesterFresh,
        $candidateStale,
        $now
    ));
    expect_same(false, ainder_profiles_allow_evaluation($requesterFresh, [], $now));
    expect_same(false, ainder_profiles_allow_evaluation(
        $requesterStale,
        $candidateStale,
        $now
    ));
});

test('public candidates exclude Agent Profile fields', function (): void {
    $public = ainder_public_candidate_payload([
        'id' => 7,
        'display_name' => 'Emma Blake',
        'birth_date' => '1998-03-11',
        'gender' => 'female',
        'basic_intro' => 'Architect in Taipei. Books and quiet cafés.',
        'is_demo' => 1,
        'profile_text' => 'Private Agent observation',
        'agent_known_duration_days' => 400,
        'interaction_density' => 'high',
    ], []);

    expect_same(false, array_key_exists('profile_text', $public));
    expect_same(false, array_key_exists('agent_known_duration_days', $public));
    expect_same(false, array_key_exists('interaction_density', $public));
    expect_same(true, $public['is_demo']);
});

test('a Match cannot contain a Demo member', function (): void {
    expect_same(false, ainder_can_create_match(['is_demo' => 0], ['is_demo' => 1]));
    expect_same(false, ainder_can_create_match(['is_demo' => 1], ['is_demo' => 0]));
    expect_same(false, ainder_can_create_match(['is_demo' => 1], ['is_demo' => 1]));
    expect_same(true, ainder_can_create_match(['is_demo' => 0], ['is_demo' => 0]));
});
```

Load `demo_test.php` from `tests/run.php`.

- [ ] **Step 2: Run and verify RED**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: FAIL because `web/lib/demo.php` and its functions do not exist.

- [ ] **Step 3: Implement the pure rules**

Create `web/lib/demo.php` with:

```php
function ainder_validate_demo_photo(array $photo): array
{
    $errors = [];
    $url = filter_var((string) ($photo['file_path'] ?? ''), FILTER_VALIDATE_URL);
    $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;

    if (($photo['source_type'] ?? null) !== 'unsplash') {
        $errors[] = 'Demo photo source must be Unsplash.';
    }
    if ($host !== 'images.unsplash.com') {
        $errors[] = 'Demo photo URL is not allowlisted.';
    }
    foreach (['source_photo_id', 'photographer_name', 'photographer_url', 'source_page_url'] as $field) {
        if (trim((string) ($photo[$field] ?? '')) === '') {
            $errors[] = "Missing {$field}.";
        }
    }

    foreach (['photographer_url', 'source_page_url'] as $field) {
        $attributionUrl = filter_var((string) ($photo[$field] ?? ''), FILTER_VALIDATE_URL);
        $attributionHost = is_string($attributionUrl)
            ? parse_url($attributionUrl, PHP_URL_HOST)
            : null;
        if ($attributionHost !== 'unsplash.com') {
            $errors[] = "Invalid {$field}.";
        }
    }

    return $errors;
}

function ainder_agent_profile_is_fresh(array $profile, DateTimeImmutable $now): bool
{
    try {
        $expiresAt = new DateTimeImmutable((string) ($profile['expires_at'] ?? ''));
    } catch (Throwable) {
        return false;
    }

    return $expiresAt > $now;
}

function ainder_profiles_allow_evaluation(
    array $requesterProfile,
    array $candidateProfile,
    DateTimeImmutable $now
): bool {
    return ainder_agent_profile_is_fresh($requesterProfile, $now)
        && trim((string) ($candidateProfile['profile_text'] ?? '')) !== '';
}

function ainder_can_create_match(array $left, array $right): bool
{
    return (int) ($left['is_demo'] ?? 0) !== 1
        && (int) ($right['is_demo'] ?? 0) !== 1;
}
```

Implement `ainder_public_candidate_payload()` as an explicit allowlist returning only `id`, `display_name`, `birth_date`, `gender`, `basic_intro`, boolean `is_demo`, and a supplied public photo array. Never copy arbitrary input keys.

- [ ] **Step 4: Run tests and lint**

Run:

```bash
lean-ctx -c --raw sh -lc "php tests/run.php && php -l web/lib/demo.php"
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add web/lib/demo.php tests/demo_test.php tests/run.php
git commit -m "feat: define Ainder demo member boundaries"
```

## Task 4: Add the Server-Side Unsplash Client

**Files:**
- Create: `web/lib/unsplash.php`
- Create: `tests/unsplash_test.php`
- Modify: `tests/run.php`
- Modify: `web/lib/config.php`
- Modify: `web/config.local.example.php`

- [ ] **Step 1: Write failing Unsplash normalization tests**

Create `tests/unsplash_test.php`:

```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/unsplash.php';

test('Unsplash API photos normalize to hotlinks and attributed source data', function (): void {
    $photo = ainder_unsplash_normalize_photo([
        'id' => 'abc123',
        'urls' => ['regular' => 'https://images.unsplash.com/photo-abc?fit=crop&w=1080'],
        'links' => [
            'html' => 'https://unsplash.com/photos/abc123',
            'download_location' => 'https://api.unsplash.com/photos/abc123/download',
        ],
        'user' => [
            'name' => 'Jamie Example',
            'links' => ['html' => 'https://unsplash.com/@jamie'],
        ],
    ]);

    expect_same('abc123', $photo['source_photo_id']);
    expect_same('unsplash', $photo['source_type']);
    expect_same('Jamie Example', $photo['photographer_name']);
    expect_same(true, str_contains($photo['photographer_url'], 'utm_source=ainder'));
    expect_same(true, str_contains($photo['source_page_url'], 'utm_source=ainder'));
    expect_same('https://api.unsplash.com/photos/abc123/download', $photo['download_location']);
});

test('Unsplash client rejects non-Unsplash download endpoints', function (): void {
    expect_same(false, ainder_unsplash_download_location_is_allowed(
        'https://example.com/photos/abc/download'
    ));
    expect_same(true, ainder_unsplash_download_location_is_allowed(
        'https://api.unsplash.com/photos/abc/download'
    ));
});
```

Load `unsplash_test.php` from `tests/run.php`.

- [ ] **Step 2: Run and verify RED**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: FAIL because the Unsplash functions do not exist.

- [ ] **Step 3: Implement API request and normalization functions**

Create `web/lib/unsplash.php` with focused functions:

- `ainder_unsplash_request(string $accessKey, string $url): array` — cURL GET with `Authorization: Client-ID ...`, JSON decode, 10-second timeout, and generic exceptions that never include the key.
- `ainder_unsplash_search(string $accessKey, string $query, string $orientation, int $perPage = 30): array` — call only `https://api.unsplash.com/search/photos` with `content_filter=high` and normalize returned results.
- `ainder_unsplash_normalize_photo(array $photo): array` — return `source_type`, `file_path`, `source_photo_id`, photographer fields, source page, and `download_location`; add `utm_source=ainder&utm_medium=referral` to attribution links.
- `ainder_unsplash_download_location_is_allowed(string $url): bool` — require HTTPS, exact `api.unsplash.com` host, and a path ending in `/download`.
- `ainder_unsplash_track_download(string $accessKey, string $downloadLocation): void` — reject unallowlisted URLs before calling the common request function.

The Access Key must appear only in an HTTP header assembled server-side.

- [ ] **Step 4: Add ignored configuration plumbing**

Add this example key to `web/config.local.example.php`:

```php
'unsplash_access_key' => 'replace-with-unsplash-access-key',
```

In `web/lib/config.php`, read only the server-side local value:

```php
'unsplash_access_key' => (string) ($local['unsplash_access_key'] ?? ''),
```

Do not modify the tracked example with the real key. Add the supplied key only to ignored `web/config.local.php` during execution.

- [ ] **Step 5: Run tests and scan for accidental credentials**

Run:

```bash
lean-ctx -c --raw sh -lc "php tests/run.php && php -l web/lib/unsplash.php && git diff --check && git grep -n 'unsplash_access_key' -- ':!web/config.local.php'"
```

Expected: tests pass; the grep finds only setting names and placeholders, never a real Access Key.

- [ ] **Step 6: Commit**

```bash
git add web/lib/unsplash.php web/lib/config.php web/config.local.example.php tests/unsplash_test.php tests/run.php
git commit -m "feat: add Ainder Unsplash client"
```

## Task 5: Fetch and Visually Curate the Unsplash Candidates

**Files:**
- Create: `tools/fetch_unsplash_candidates.php`
- Create during execution: `.superpowers/brainstorm/<session>/content/demo-portrait-candidates.html`
- Create during execution: `.superpowers/brainstorm/<session>/content/demo-lifestyle-candidates.html`
- Create during execution: `var/demo-candidates.json` (ignored working artifact)
- Create: `web/seeds/demo_photo_tracking.php`

- [ ] **Step 1: Write a CLI contract test first**

Add to `tests/unsplash_test.php`:

```php
test('candidate fetch tool defines the four balanced portrait cohorts', function (): void {
    $source = file_get_contents(dirname(__DIR__).'/tools/fetch_unsplash_candidates.php');

    foreach ([
        'asian_male',
        'asian_female',
        'western_male',
        'western_female',
        'portrait',
        'content_filter',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
});
```

- [ ] **Step 2: Run and verify RED**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: FAIL because the fetch tool does not exist.

- [ ] **Step 3: Implement the candidate fetch tool**

Create `tools/fetch_unsplash_candidates.php`. It must:

- run only under `PHP_SAPI === 'cli'`;
- load ignored `web/config.local.php` and stop when the key is absent;
- make one search request per portrait cohort with 30 results;
- make lifestyle searches for `travel`, `coffee`, `pet`, `workspace`, `design`, and `fitness`;
- normalize every result through `ainder_unsplash_normalize_photo()`;
- deduplicate by `source_photo_id`;
- write JSON only to an explicit `--output=<path>` argument;
- print counts and the output path without printing the key or raw authorization header.

Use these query definitions:

```php
$portraitQueries = [
    'asian_male' => 'Asian man portrait lifestyle',
    'asian_female' => 'Asian woman portrait lifestyle',
    'western_male' => 'Western man portrait lifestyle',
    'western_female' => 'Western woman portrait lifestyle',
];
```

- [ ] **Step 4: Verify GREEN**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: all tests pass without making a network request from the test suite.

- [ ] **Step 5: Fetch the real candidate set**

After placing the supplied Access Key in ignored `web/config.local.php`, run:

```bash
mkdir -p var
php tools/fetch_unsplash_candidates.php --output=var/demo-candidates.json
```

Expected: four portrait arrays with up to 30 normalized items each and six lifestyle arrays. Confirm the file contains no Access Key.

- [ ] **Step 6: Build and show the portrait gallery**

Start the accepted visual companion with the project root. Create a new `demo-portrait-candidates.html` fragment showing four labeled sections. Each card must include the thumbnail, photographer name, Unsplash link, photo ID, and a selectable cohort/photo choice.

Pause implementation and ask the user to review the local companion URL. Select exactly five distinct portrait IDs per cohort. Reject group portraits, branded imagery, weak crops, repeated people, and unclear subjects.

- [ ] **Step 7: Build and show the lifestyle gallery**

After the 20 portraits are approved, show lifestyle candidates grouped by travel, coffee, pet, workspace, design, and fitness. Select one compatible secondary image per fictional member. Secondary images do not need to show a person.

Pause again until all 20 lifestyle assignments are approved.

- [ ] **Step 8: Trigger and record the selected-photo download events**

After the user approves all 40 assignments, call each selected photo's allowlisted `download_location` once through `ainder_unsplash_track_download()`. Create `web/seeds/demo_photo_tracking.php` as a pure list and append each `source_photo_id` atomically immediately after its tracking request succeeds. If execution is interrupted, skip IDs already present in this ledger and continue only with the missing selections.

The frozen member manifest must remain a pure 20-item member list. The seeder never calls Unsplash and therefore cannot retrigger these events during an idempotency run.

- [ ] **Step 9: Commit the fetch tool, test, and non-secret tracking ledger**

Do not commit `var/demo-candidates.json` or `.superpowers/`.

```bash
git add tools/fetch_unsplash_candidates.php web/seeds/demo_photo_tracking.php tests/unsplash_test.php
git commit -m "feat: fetch Ainder demo photo candidates"
```

## Task 6: Freeze and Validate the 20-Member Manifest

**Files:**
- Create: `web/seeds/demo_members.php`
- Modify: `web/lib/demo.php`
- Modify: `tests/demo_test.php`

- [ ] **Step 1: Write failing manifest tests**

Add to `tests/demo_test.php`:

```php
test('frozen Demo manifest contains the exact approved cohort', function (): void {
    $manifest = require dirname(__DIR__).'/web/seeds/demo_members.php';
    $trackedPhotoIds = require dirname(__DIR__).'/web/seeds/demo_photo_tracking.php';
    $errors = ainder_validate_demo_manifest(
        $manifest,
        new DateTimeImmutable('2026-09-01 00:00:00')
    );

    expect_same([], $errors);
    expect_same(20, count($manifest));

    $cohorts = array_count_values(array_column($manifest, 'cohort'));
    expect_same([
        'asian_male' => 5,
        'asian_female' => 5,
        'western_male' => 5,
        'western_female' => 5,
    ], $cohorts);

    foreach ($manifest as $member) {
        expect_same(2, count($member['photos']));
        expect_same(true, mb_strlen($member['basic_intro'], 'UTF-8') <= 50);
        expect_same(true, $member['is_demo']);
    }

    $manifestPhotoIds = [];
    foreach ($manifest as $member) {
        foreach ($member['photos'] as $photo) {
            $manifestPhotoIds[] = $photo['source_photo_id'];
        }
    }
    sort($manifestPhotoIds);
    sort($trackedPhotoIds);
    expect_same(40, count(array_unique($trackedPhotoIds)));
    expect_same($manifestPhotoIds, $trackedPhotoIds);
});
```

- [ ] **Step 2: Run and verify RED**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: FAIL because the manifest and validator do not exist.

- [ ] **Step 3: Implement the complete manifest validator**

Add `ainder_validate_demo_manifest(array $manifest, DateTimeImmutable $now): array` to `web/lib/demo.php`. It must reject:

- any count other than 20;
- duplicate or non-`demo:` Google subs;
- email outside `ainder.invalid`;
- non-ASCII/blank English display names, introductions, or profile text;
- introductions over 50 Unicode characters;
- gender outside `male`/`female`;
- ages outside 25–55 at `$now`;
- cohort totals other than five each;
- photo counts other than two;
- duplicate photo IDs;
- any `ainder_validate_demo_photo()` error;
- Agent known durations outside 90–730 days;
- density outside `low`, `medium`, `high`;

The manifest does not contain `generated_at` or `expires_at`. Those timestamps are generated by the transactional seeder, then validated through the freshness rules and production aggregate check.

- [ ] **Step 4: Author the frozen English Demo manifest**

Create `web/seeds/demo_members.php` with the 20 approved curated photo assignments and complete fictional records. Use deterministic IDs `demo:001` through `demo:020`, emails under `ainder.invalid`, and these balanced English names as the starting roster:

```text
Ethan Park, Daniel Kim, Adrian Lee, Noah Chen, Marcus Tan
Maya Zhou, Chloe Park, Olivia Chen, Natalie Kim, Grace Liu
Liam Carter, Owen Brooks, Julian Reed, Henry Collins, Victor Hayes
Emma Blake, Sophia Reed, Claire Bennett, Hannah Moore, Evelyn Grant
```

Each record must contain `google_sub`, `email`, `display_name`, `birth_date`, `gender`, `basic_intro`, `is_demo`, internal `cohort`, exactly two approved normalized photos, and one complete Agent Profile. Write distinct English Agent observations covering personality, communication, values, relationship expectations, lifestyle, strengths, and possible friction. Do not mention that the source person in the photo has any of the fictional traits.

Keep all seeded English text within printable ASCII so language validation is deterministic. Set `generated_at` at seed execution time through the seeder and compute `expires_at` as exactly three calendar months later; the manifest stores relative profile inputs rather than a permanent fresh date.

- [ ] **Step 5: Cross-check the tracking ledger**

Load `web/seeds/demo_photo_tracking.php` and assert that its 40 unique IDs exactly match the 40 unique `source_photo_id` values in the pure 20-member manifest. Download tracking was already completed during curation; neither the validator nor seeder makes a network request.

- [ ] **Step 6: Verify GREEN and inspect content**

Run:

```bash
lean-ctx -c --raw sh -lc "php tests/run.php && php -l web/seeds/demo_members.php && git diff --check"
```

Expected: exact counts pass, all 40 photos validate, and all 20 Agent Profiles are complete.

- [ ] **Step 7: Commit**

```bash
git add web/seeds/demo_members.php web/lib/demo.php tests/demo_test.php
git commit -m "feat: add curated Ainder demo manifest"
```

## Task 7: Add the Transactional, Idempotent Demo Seeder

**Files:**
- Create: `web/seeds/run_demo_members.php`
- Modify: `web/lib/demo.php`
- Modify: `tests/demo_test.php`

- [ ] **Step 1: Write failing seeder contracts**

Add tests asserting the endpoint requires the migration token, loads only the frozen manifest, begins a transaction, upserts by `google_sub`, replaces photos and Agent Profile rows only for the same Demo member IDs, rolls back on `Throwable`, and never inserts `is_demo = 0`.

Use exact source assertions:

```php
test('Demo seed endpoint is token protected and transactional', function (): void {
    $source = file_get_contents(dirname(__DIR__).'/web/seeds/run_demo_members.php');

    foreach ([
        "hash_equals",
        "demo_members.php",
        "begin_transaction",
        "ON DUPLICATE KEY UPDATE",
        "DELETE FROM user_photos",
        "DELETE FROM agent_profiles",
        "INSERT INTO agent_profiles",
        "rollback",
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
});
```

- [ ] **Step 2: Run and verify RED**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: FAIL because the endpoint does not exist.

- [ ] **Step 3: Implement transaction-safe upsert helpers**

Add focused functions to `web/lib/demo.php`:

- `ainder_seed_demo_members(mysqli $database, array $manifest, DateTimeImmutable $now): array`;
- `ainder_upsert_demo_user(mysqli $database, array $member): int`;
- `ainder_replace_demo_photos(mysqli $database, int $userId, array $photos): void`;
- `ainder_replace_demo_agent_profile(mysqli $database, int $userId, array $profile, DateTimeImmutable $now): void`.

The top-level function validates before `begin_transaction()`, commits only after all records succeed, rolls back and rethrows on any error, and returns only:

```php
['users' => 20, 'photos' => 40, 'agent_profiles' => 20]
```

`ainder_replace_demo_agent_profile()` must set `generated_at` to `$now` and `expires_at` to `$now->modify('+3 months')`, then persist the manifest's profile text, known-duration days, and interaction density.

The user upsert must force `is_demo = 1` and update only the deterministic Demo identity's fictional fields. It must never update a real row whose `google_sub` does not begin with `demo:`.

- [ ] **Step 4: Implement the temporary endpoint**

Create `web/seeds/run_demo_members.php` using the same POST/token/config-only boundary as migrations. It must:

- reject non-POST requests;
- compare the submitted token with ignored `migration_token`;
- connect only to the `ainder` database through `ainder_config()` and `ainder_database()`;
- load `demo_members.php`;
- call `ainder_seed_demo_members()`;
- return only success counts or `Seed failed.` with HTTP 503.

- [ ] **Step 5: Run tests and lint**

Run:

```bash
lean-ctx -c --raw sh -lc "php tests/run.php && php -l web/lib/demo.php && php -l web/seeds/run_demo_members.php && git diff --check"
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add web/lib/demo.php web/seeds/run_demo_members.php tests/demo_test.php
git commit -m "feat: seed Ainder demo members transactionally"
```

## Task 8: Deploy, Migrate, Seed, and Verify Production

**Files:**
- Modify ignored: `web/config.local.php`
- Deploy: all required files under `web/`
- Create temporarily: `web/diagnostics/demo_seed_status.php`
- Remove from production after use: `migrations/002_add_demo_members.php`
- Remove from production after use: `seeds/run_demo_members.php`
- Remove from production after use: `diagnostics/demo_seed_status.php`

- [ ] **Step 1: Add the supplied Access Key only to ignored config**

Update `web/config.local.php` with `unsplash_access_key`. Confirm:

```bash
git check-ignore web/config.local.php
git status --short
```

Expected: the file is ignored and the real key is absent from status and tracked diffs.

- [ ] **Step 2: Run the complete local verification gate**

Run:

```bash
lean-ctx -c --raw sh -lc "php tests/run.php && find web tests tools -name '*.php' -print0 | xargs -0 -n1 php -l && git diff --check"
```

Expected: all tests and syntax checks pass.

- [ ] **Step 3: Deploy only migration 002**

Inventory `/Volumes/sweety.tw/ainder` first. Deploy only `migrations/002_add_demo_members.php`; do not replace the registration runtime yet. Ensure the ignored production `config.local.php` is present and protected by PHP execution.

- [ ] **Step 4: Run migration 002 once and remove its live endpoint**

POST the existing migration token to:

```text
https://sweety.tw/ainder/migrations/002_add_demo_members.php
```

Expected JSON:

```json
{"ok":true,"migration":"002_add_demo_members","tables":["users","user_photos","agent_profiles"]}
```

Immediately remove the exact deployed migration file. Confirm its public URL returns 404.

- [ ] **Step 5: Deploy persistent runtime files**

After migration 002 succeeds, synchronize the reviewed runtime and seed manifest without deleting user uploads or overwriting ignored production configuration. Do not deploy `config.local.example.php`. This order prevents the new registration insert from running against the old schema.

- [ ] **Step 6: Verify the real onboarding field live**

With the existing pending Google session, confirm the page renders the required public-introduction input with the exact placeholder, 50-character maximum, versioned CSS/JS, and unchanged 2–6 local-photo flow. Do not submit personal data without action-time confirmation.

- [ ] **Step 7: Run the Demo seed and remove the live endpoint**

Deploy only `seeds/run_demo_members.php` immediately before use, then POST the token to:

```text
https://sweety.tw/ainder/seeds/run_demo_members.php
```

Expected JSON:

```json
{"ok":true,"users":20,"photos":40,"agent_profiles":20}
```

Immediately remove only the deployed `run_demo_members.php`. Confirm its public URL returns 404. Keep the reviewed repository source.

- [ ] **Step 8: Verify production counts through a temporary read-only endpoint**

Create and deploy `diagnostics/demo_seed_status.php` using the same POST/token/config-only boundary. It must return only aggregate counts and validation booleans:

```json
{
  "demo_users":20,
  "demo_photos":40,
  "demo_agent_profiles":20,
  "members_with_two_photos":20,
  "fresh_profiles":20,
  "invalid_intro_count":0,
  "non_unsplash_demo_photo_count":0
}
```

Remove the diagnostic immediately and verify it returns 404. Never return profile text, email, Google sub, Access Key, or photo API credentials.

- [ ] **Step 9: Verify idempotency**

Redeploy only the seed endpoint, run the identical manifest a second time, remove the endpoint again, and repeat aggregate verification. Expected counts remain 20/40/20 with no duplicates.

- [ ] **Step 10: Final evidence gate and commit any deployment-only test adjustments**

Run fresh local tests, syntax checks, deployed-file hashes for persistent runtime files, homepage HTTP 200, protected-page guards, and 404 checks for every temporary endpoint. Confirm tracked source contains no production Access Key.

Record the final commits and live evidence in the handoff. Do not claim the Demo candidate badge or attribution UI is visually verified until the future candidate UI exists; this phase verifies the data boundary only.
