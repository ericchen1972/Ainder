# Ainder Test Account Login Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add two safe landing-page test logins that atomically restore Grace Liu and John Carter to deterministic unanswered incoming Like states.

**Architecture:** A focused `test_accounts.php` domain module owns the fixed slug allowlist, database card lookup, and transactional relationship reset. The landing page renders POST forms from database-backed account cards, while a dedicated auth endpoint validates CSRF, calls the reset module, establishes the session, and redirects to the app. Existing Google login remains unchanged.

**Tech Stack:** PHP 8 strict types, mysqli/InnoDB transactions, server-rendered HTML, CSS media queries, existing custom PHP test harness, In Browser verification.

---

## File Map

- Create `web/lib/test_accounts.php`: fixed scenario mapping, test-card lookup, and atomic reset/Like creation.
- Create `web/auth/test.php`: CSRF-protected POST test-login controller.
- Create `tests/test_accounts_test.php`: pure mapping tests and source-level transaction contract tests.
- Modify `tests/run.php`: load the new test suite.
- Modify `web/seeds/demo_members.php`: rename deterministic `demo:011` from Liam Carter to John Carter and update its Agent Profile name.
- Modify `tests/demo_test.php`: assert the selected deterministic members and John rename.
- Modify `web/index.php`: load database-backed test cards and render two login forms.
- Modify `web/assets/app.css`: desktop lower-center and mobile bottom-row layout with circular non-distorted photos.
- Modify `tests/page_contract_test.php`: landing-page markup, endpoint, CSRF, and responsive CSS contracts.

### Task 1: Define the deterministic scenarios

**Files:**
- Create: `web/lib/test_accounts.php`
- Create: `tests/test_accounts_test.php`
- Modify: `tests/run.php`

- [ ] **Step 1: Write the failing mapping tests**

Add `tests/test_accounts_test.php` with assertions that only `grace` and `john` exist, that each scenario uses a different sender, and that both opinions are non-empty:

```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/test_accounts.php';

test('test account scenarios use stable independent identities', function (): void {
    $scenarios = ainder_test_account_scenarios();

    expect_same(['grace', 'john'], array_keys($scenarios));
    expect_same('demo:010', $scenarios['grace']['member_google_sub']);
    expect_same('demo:001', $scenarios['grace']['sender_google_sub']);
    expect_same('demo:011', $scenarios['john']['member_google_sub']);
    expect_same('demo:020', $scenarios['john']['sender_google_sub']);
    expect_same(
        4,
        count(array_unique([
            $scenarios['grace']['member_google_sub'],
            $scenarios['grace']['sender_google_sub'],
            $scenarios['john']['member_google_sub'],
            $scenarios['john']['sender_google_sub'],
        ]))
    );
});

test('test account lookup rejects unknown slugs', function (): void {
    expect_same(null, ainder_test_account_scenario('unknown'));
    expect_same('Grace Liu', ainder_test_account_scenario('grace')['label']);
    expect_same('John Carter', ainder_test_account_scenario('john')['label']);
});

test('test incoming Likes always contain deterministic opinions', function (): void {
    foreach (ainder_test_account_scenarios() as $scenario) {
        expect_same(true, trim($scenario['agent_opinion']) !== '');
        expect_same(true, mb_strlen($scenario['agent_opinion']) <= 1000);
    }
});
```

Require it from `tests/run.php` immediately after `auth_test.php`.

- [ ] **Step 2: Run the suite and verify RED**

Run: `php tests/run.php`

Expected: FAIL or fatal include error because `web/lib/test_accounts.php` and its functions do not exist.

- [ ] **Step 3: Implement the minimal scenario module**

Create `web/lib/test_accounts.php` with:

```php
<?php

declare(strict_types=1);

function ainder_test_account_scenarios(): array
{
    return [
        'grace' => [
            'label' => 'Grace Liu',
            'member_google_sub' => 'demo:010',
            'sender_google_sub' => 'demo:001',
            'agent_opinion' => "Grace's warmth, creativity, and respect for emotional boundaries look promising. Ethan's steady listening may suit her need for gentleness, while both should be careful not to postpone difficult conversations.",
        ],
        'john' => [
            'label' => 'John Carter',
            'member_google_sub' => 'demo:011',
            'sender_google_sub' => 'demo:020',
            'agent_opinion' => "John's reliability, humor, and active lifestyle look compatible with Evelyn's practical and health-conscious approach. They may connect through shared routines, as long as solutions do not replace emotional listening.",
        ],
    ];
}

function ainder_test_account_scenario(string $slug): ?array
{
    $scenarios = ainder_test_account_scenarios();

    return $scenarios[$slug] ?? null;
}
```

- [ ] **Step 4: Run the suite and verify GREEN**

Run: `php tests/run.php`

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add web/lib/test_accounts.php tests/test_accounts_test.php tests/run.php
git commit -m "test: define deterministic login scenarios"
```

### Task 2: Rename John in the deterministic Demo seed

**Files:**
- Modify: `web/seeds/demo_members.php`
- Modify: `tests/demo_test.php`

- [ ] **Step 1: Write the failing seed identity test**

Add a test that indexes the manifest by `google_sub` and asserts:

```php
test('test login members keep deterministic Demo identities', function () use ($manifest): void {
    $bySub = [];
    foreach ($manifest as $member) {
        $bySub[$member['google_sub']] = $member;
    }

    expect_same('Grace Liu', $bySub['demo:010']['display_name']);
    expect_same('John Carter', $bySub['demo:011']['display_name']);
    expect_same(
        true,
        str_starts_with($bySub['demo:011']['agent_profile']['profile_text'], 'John ')
    );
    expect_same('Ethan Park', $bySub['demo:001']['display_name']);
    expect_same('Evelyn Grant', $bySub['demo:020']['display_name']);
});
```

- [ ] **Step 2: Run and verify RED**

Run: `php tests/run.php`

Expected: FAIL because `demo:011` is still Liam Carter.

- [ ] **Step 3: Rename only the deterministic member**

In `web/seeds/demo_members.php`, change `demo:011` display name from `Liam Carter` to `John Carter` and replace the leading `Liam` in that member's `profile_text` with `John`. Preserve its photos, birth date, gender, profile duration, density, and all other records.

- [ ] **Step 4: Run and verify GREEN**

Run: `php tests/run.php`

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add web/seeds/demo_members.php tests/demo_test.php
git commit -m "feat: prepare John test identity"
```

### Task 3: Implement atomic reset and incoming Like creation

**Files:**
- Modify: `web/lib/test_accounts.php`
- Modify: `tests/test_accounts_test.php`

- [ ] **Step 1: Write failing reset-contract tests**

Add tests that inspect `web/lib/test_accounts.php` and require the database behavior explicitly:

```php
test('test login reset is one transaction with complete relationship cleanup', function (): void {
    $source = file_get_contents(dirname(__DIR__).'/web/lib/test_accounts.php');

    foreach ([
        'begin_transaction',
        'FOR UPDATE',
        'DELETE FROM candidate_evaluations',
        'DELETE FROM matches',
        'DELETE FROM likes',
        'INSERT INTO likes',
        'agent_profiles',
        'user_photos',
        'commit',
        'rollback',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
});

test('test login reset scopes every deletion to the selected member', function (): void {
    $source = file_get_contents(dirname(__DIR__).'/web/lib/test_accounts.php');

    expect_same(true, substr_count($source, 'requester_user_id = ?') >= 1);
    expect_same(true, substr_count($source, 'user_low_id = ?') >= 1);
    expect_same(true, substr_count($source, 'sender_user_id = ?') >= 1);
});
```

- [ ] **Step 2: Run and verify RED**

Run: `php tests/run.php`

Expected: FAIL because the transaction and SQL are absent.

- [ ] **Step 3: Implement member lookup, validation, card lookup, and reset**

Add these public functions to `web/lib/test_accounts.php`:

```php
function ainder_test_account_cards(mysqli $database): array;
function ainder_reset_test_account(mysqli $database, array $scenario): int;
```

`ainder_test_account_cards()` queries each configured `member_google_sub` for an active user joined to `user_photos.sort_order = 1`, verifies the returned identity matches the scenario label, and returns ordered card arrays containing `slug`, `label`, and `photo_path`. Missing records are omitted so the landing page remains available.

`ainder_reset_test_account()` must:

1. call `begin_transaction()`;
2. select the recipient and sender by the two scenario Google subs with `FOR UPDATE`;
3. require exactly one active member for each identity, opposite genders, one Agent Profile per member, and a main photo for each;
4. delete evaluations using `requester_user_id = ? OR candidate_user_id = ?`;
5. delete Matches using `user_low_id = ? OR user_high_id = ?`;
6. delete Likes using `sender_user_id = ? OR recipient_user_id = ?`;
7. insert the configured sender-to-recipient Like with `agent_opinion`;
8. commit and return the recipient ID;
9. roll back and rethrow every failure.

Use prepared statements for all values. Do not accept member IDs or SQL fragments from request data.

- [ ] **Step 4: Run and verify GREEN**

Run: `php tests/run.php`

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add web/lib/test_accounts.php tests/test_accounts_test.php
git commit -m "feat: reset test account relationships atomically"
```

### Task 4: Add the CSRF-protected test auth endpoint

**Files:**
- Create: `web/auth/test.php`
- Modify: `tests/page_contract_test.php`

- [ ] **Step 1: Write the failing endpoint contract test**

Add:

```php
test('test login endpoint is allowlisted CSRF protected and session safe', function () use ($root): void {
    $source = file_get_contents($root.'/web/auth/test.php');

    foreach ([
        "REQUEST_METHOD'] !== 'POST'",
        'ainder_form_csrf_is_valid',
        'ainder_test_account_scenario',
        'ainder_reset_test_account',
        'session_regenerate_id(true)',
        "ainder_member_id",
        'ainder_record_login',
        "Location: /ainder/app/",
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
    expect_same(false, preg_match('/\$_POST\[[^]]*(?:member_id|user_id)/', $source) === 1);
});
```

- [ ] **Step 2: Run and verify RED**

Run: `php tests/run.php`

Expected: FAIL because `web/auth/test.php` is absent.

- [ ] **Step 3: Implement the controller**

The endpoint loads session, config, database, and test-account helpers. Invalid method, CSRF, or slug redirects to `/ainder/?login=test-failed`. A valid request opens the database, calls `ainder_reset_test_account()`, then runs:

```php
session_regenerate_id(true);
unset(
    $_SESSION['ainder_pending_identity'],
    $_SESSION['ainder_pending_expires_at']
);
$_SESSION['ainder_member_id'] = $memberId;
ainder_record_login($database, $memberId);
header('Location: /ainder/app/');
exit;
```

Catch every throwable, return HTTP 503 with a generic unavailable message, and never expose SQL errors.

- [ ] **Step 4: Run and verify GREEN**

Run: `php tests/run.php`

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add web/auth/test.php tests/page_contract_test.php
git commit -m "feat: add transactional test login endpoint"
```

### Task 5: Render and style test logins on the landing page

**Files:**
- Modify: `web/index.php`
- Modify: `web/assets/app.css`
- Modify: `tests/page_contract_test.php`

- [ ] **Step 1: Write failing landing UI contract tests**

Require the page and CSS to contain:

```php
test('landing renders Grace and John main-photo login controls', function () use ($root): void {
    $page = file_get_contents($root.'/web/index.php');
    $css = file_get_contents($root.'/web/assets/app.css');

    foreach ([
        'ainder_test_account_cards',
        'ainder_form_csrf_token',
        '/ainder/auth/test.php',
        'Login as ',
        'test-login-panel',
        'test-login-avatar',
    ] as $needle) {
        expect_same(true, str_contains($page, $needle));
    }
    foreach ([
        '.test-login-panel',
        '.test-login-avatar',
        'border-radius: 50%',
        'object-fit: cover',
        'env(safe-area-inset-bottom)',
    ] as $needle) {
        expect_same(true, str_contains($css, $needle));
    }
});
```

- [ ] **Step 2: Run and verify RED**

Run: `php tests/run.php`

Expected: FAIL because the landing controls and CSS are absent.

- [ ] **Step 3: Load the cards without making the hero depend on the DB**

In `web/index.php`, require `database.php` and `test_accounts.php`, then populate `$testAccounts` in a narrow try/catch:

```php
$testAccounts = [];
try {
    $testAccounts = ainder_test_account_cards(
        ainder_database(ainder_config())
    );
} catch (Throwable) {
    $testAccounts = [];
}
```

Generate a form CSRF token only when cards are available. Treat `?login=test-failed` as a generic `Test login is temporarily unavailable.` error.

- [ ] **Step 4: Render semantic POST forms**

Below the header, render a `section.test-login-panel` when both cards are available. Each form posts only `csrf_token` and `account_slug` to `/ainder/auth/test.php`. The button contains the current main photo and a span reading `Login as {label}`.

- [ ] **Step 5: Add responsive CSS**

Desktop: position the group at `left: 50%`, lower-center with `transform: translateX(-50%)`, a two-column row, and readable text shadow. Mobile: anchor it above `env(safe-area-inset-bottom)` with compact 72 px avatars. Use fixed equal width/height, `border-radius: 50%`, and `object-fit: cover` so portraits never distort.

- [ ] **Step 6: Run and verify GREEN**

Run: `php tests/run.php`

Expected: all tests pass.

Run syntax checks:

```bash
find web tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: no syntax errors.

- [ ] **Step 7: Commit**

```bash
git add web/index.php web/assets/app.css tests/page_contract_test.php
git commit -m "feat: add landing test account logins"
```

### Task 6: Full verification and production deployment

**Files:**
- Verify all modified files
- Preserve: `web/config.local.php`, `web/uploads/`

- [ ] **Step 1: Run the full automated suite**

```bash
php tests/run.php
node --test tests/browse_model_test.mjs tests/profile_editor_model_test.mjs
find web tests -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check
```

Expected: zero failures, zero syntax errors, and no whitespace errors.

- [ ] **Step 2: Verify local desktop and mobile rendering**

Use the In Browser page at the local PHP server. Verify the two avatars are circular and undistorted, desktop placement is lower-center, mobile placement is a bottom icon row above the safe area, and Google login remains present.

- [ ] **Step 3: Deploy the web tree safely**

```bash
rsync -rtv --exclude 'config.local.php' --exclude 'uploads/' web/ /Volumes/sweety.tw/ainder/
```

Expected: only tracked application files change; production config and uploads remain untouched.

- [ ] **Step 4: Rerun the deterministic Demo seed**

POST to `/ainder/seeds/run_demo_members.php` using the migration token from the untracked production configuration without printing it. Expected JSON:

```json
{"ok":true,"users":20,"photos":40,"agent_profiles":20}
```

- [ ] **Step 5: Verify production behavior**

In the authenticated In Browser session:

1. Logout to the landing page.
2. Verify Grace and John controls on desktop and mobile widths.
3. Login as Grace and confirm exactly one pending Like from Ethan Park.
4. Logout, login as John, and confirm exactly one pending Like from Evelyn Grant.
5. Logout, login as Grace again, and confirm Ethan remains while John's Evelyn Like was preserved.
6. Confirm no console errors and successful page responses.

- [ ] **Step 6: Push and record final state**

```bash
git push origin main
git status --short
git rev-parse HEAD
git rev-parse origin/main
```

Expected: local `HEAD` equals `origin/main`; only pre-existing untracked `.DS_Store` and `.superpowers/` remain.

