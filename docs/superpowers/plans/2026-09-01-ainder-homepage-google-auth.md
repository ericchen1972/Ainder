# Ainder Homepage and Google Authentication Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Publish the responsive Ainder landing page and route verified Google users to either a non-persisted onboarding placeholder or an authenticated member placeholder backed by an independent Ainder database.

**Architecture:** A focused PHP 8.2 application lives under `web/` and deploys to `sweety.tw/ainder`. Google Identity Services posts an ID token to PHP; the server verifies it with the installed Google SDK, looks up `google_sub` in `ainder.users`, and stores new identities only in a 30-minute session.

**Tech Stack:** PHP 8.2, mysqli, Google Identity Services, google/apiclient, plain PHP contract tests, HTML/CSS, WebP.

---

## File Map

- `web/lib/config.php` — load Ainder configuration and reuse only Sweety's MySQL connection values.
- `web/lib/session.php` — start and clear the Ainder-scoped session.
- `web/lib/auth.php` — pure CSRF, identity normalization, expiry, and routing decisions.
- `web/lib/database.php` — connect only to `ainder` and query/update existing members.
- `web/lib/google.php` — verify Google ID tokens.
- `web/index.php` — public landing page.
- `web/auth/google.php` — Google sign-in POST endpoint.
- `web/profile/index.php` — guarded non-member placeholder.
- `web/app/index.php` — guarded member placeholder.
- `web/placeholder.php` — shared placeholder markup.
- `web/logout.php` — clear only Ainder state.
- `web/assets/app.css` — responsive dark visual system.
- `web/config.local.example.php` — production configuration shape without real values.
- `web/migrations/001_create_ainder.php` — token-protected, one-time database migration.
- `tests/run.php`, `tests/auth_test.php`, `tests/page_contract_test.php` — dependency-free regression suite.

### Task 1: Authentication Decision Core

**Files:**
- Create: `tests/run.php`
- Create: `tests/auth_test.php`
- Create: `web/lib/auth.php`

- [ ] **Step 1: Create the test runner and failing authentication tests**

```php
<?php
// tests/run.php
$failures = 0;
function test(string $name, callable $callback): void {
    global $failures;
    try { $callback(); echo "PASS {$name}\n"; }
    catch (Throwable $error) { $failures++; echo "FAIL {$name}: {$error->getMessage()}\n"; }
}
function expect_same(mixed $expected, mixed $actual): void {
    if ($expected !== $actual) throw new RuntimeException('Unexpected value: '.var_export($actual, true));
}
require __DIR__.'/auth_test.php';
if (is_file(__DIR__.'/page_contract_test.php')) require __DIR__.'/page_contract_test.php';
exit($failures === 0 ? 0 : 1);
```

```php
<?php
// tests/auth_test.php
require_once dirname(__DIR__).'/web/lib/auth.php';
test('Google CSRF requires matching non-empty tokens', function (): void {
    expect_same(true, ainder_google_csrf_is_valid('same', 'same'));
    expect_same(false, ainder_google_csrf_is_valid('', 'same'));
    expect_same(false, ainder_google_csrf_is_valid('one', 'two'));
});
test('verified Google payload is normalized', function (): void {
    expect_same('google-123', ainder_normalize_google_identity([
        'sub' => 'google-123', 'email' => 'eva@example.com',
        'email_verified' => true, 'name' => 'Eva',
        'picture' => 'https://example.com/eva.jpg',
    ])['google_sub']);
});
test('unverified Google payload is rejected', function (): void {
    expect_same(null, ainder_normalize_google_identity(['sub' => 'x', 'email' => 'e@example.com']));
});
test('only active members route to the app', function (): void {
    expect_same('/ainder/profile/', ainder_login_destination(null));
    expect_same('/ainder/profile/', ainder_login_destination(['status' => 'disabled']));
    expect_same('/ainder/app/', ainder_login_destination(['status' => 'active']));
});
test('pending identity expires at thirty minutes', function (): void {
    $session = ['ainder_pending_identity' => ['google_sub' => 'x'], 'ainder_pending_expires_at' => 2000];
    expect_same(true, ainder_pending_identity_is_valid($session, 1999));
    expect_same(false, ainder_pending_identity_is_valid($session, 2000));
});
```

- [ ] **Step 2: Run `php tests/run.php` and verify it fails because `web/lib/auth.php` is absent**

Expected: non-zero exit with a missing-file error.

- [ ] **Step 3: Implement the smallest pure authentication core**

```php
<?php
declare(strict_types=1);
function ainder_google_csrf_is_valid(string $cookie, string $post): bool {
    return $cookie !== '' && $post !== '' && hash_equals($cookie, $post);
}
function ainder_normalize_google_identity(array $payload): ?array {
    $sub = trim((string) ($payload['sub'] ?? ''));
    $email = trim((string) ($payload['email'] ?? ''));
    if ($sub === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !filter_var($payload['email_verified'] ?? false, FILTER_VALIDATE_BOOL)) return null;
    $name = trim((string) ($payload['name'] ?? ''));
    return [
        'google_sub' => $sub,
        'email' => $email,
        'display_name' => mb_substr($name !== '' ? $name : (strstr($email, '@', true) ?: 'Ainder user'), 0, 120),
        'avatar_url' => filter_var($payload['picture'] ?? '', FILTER_VALIDATE_URL) ? (string) $payload['picture'] : '',
    ];
}
function ainder_login_destination(?array $member): string {
    return ($member['status'] ?? null) === 'active' ? '/ainder/app/' : '/ainder/profile/';
}
function ainder_pending_identity_is_valid(array $session, int $now): bool {
    return isset($session['ainder_pending_identity']['google_sub'], $session['ainder_pending_expires_at'])
        && (int) $session['ainder_pending_expires_at'] > $now;
}
```

- [ ] **Step 4: Run `php tests/run.php`; expect all five tests to pass**

- [ ] **Step 5: Commit with `git commit -m "test: define Ainder authentication decisions"`**

### Task 2: Configuration, Session, Database, and Google Verification

**Files:**
- Create: `web/config.local.example.php`
- Create: `web/lib/config.php`
- Create: `web/lib/session.php`
- Create: `web/lib/database.php`
- Create: `web/lib/google.php`
- Create: `web/auth/google.php`
- Modify: `tests/auth_test.php`

- [ ] **Step 1: Add failing source-boundary tests**

```php
test('configuration fixes the database name to ainder', function (): void {
    $source = file_get_contents(dirname(__DIR__).'/web/lib/config.php');
    expect_same(true, str_contains($source, "'db_name' => 'ainder'"));
    expect_same(false, str_contains($source, "'db_name' => 'sweety'"));
});
test('new Google identity has no insert path', function (): void {
    $source = file_get_contents(dirname(__DIR__).'/web/auth/google.php');
    expect_same(true, str_contains($source, 'ainder_pending_identity'));
    expect_same(false, preg_match('/\\bINSERT\\b/i', $source) === 1);
});
```

- [ ] **Step 2: Run `php tests/run.php`; expect both new tests to fail on missing files**

- [ ] **Step 3: Implement configuration and session boundaries**

`config.php` must include the parent `/mysql.php` in `SWEETY_MYSQL_CONFIG_ONLY` mode, retain only its host/user/password, force `'db_name' => 'ainder'`, and load only the Google Client ID from `AINDER_GOOGLE_CLIENT_ID` or untracked `config.local.php`. `session.php` must use `AINDERSESSID` with `Secure`, `HttpOnly`, `SameSite=Lax`, and path `/ainder/`.

```php
<?php
// web/config.local.example.php
return [
    'google_client_id' => 'replace-with-web-client-id.apps.googleusercontent.com',
    'migration_token' => 'replace-with-one-time-random-token',
];
```

```php
<?php
// web/lib/config.php
declare(strict_types=1);
function ainder_config(): array {
    define('SWEETY_MYSQL_CONFIG_ONLY', true);
    require dirname(__DIR__, 2).'/mysql.php';
    $local = is_file(dirname(__DIR__).'/config.local.php') ? require dirname(__DIR__).'/config.local.php' : [];
    return [
        'db_host' => $mysqlhost, 'db_user' => $mysqluser, 'db_password' => $mysqlpasswd,
        'db_name' => 'ainder',
        'google_client_id' => getenv('AINDER_GOOGLE_CLIENT_ID') ?: (string) ($local['google_client_id'] ?? ''),
    ];
}
```

```php
<?php
// web/lib/session.php
declare(strict_types=1);
function ainder_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('AINDERSESSID');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/ainder/', 'secure' => true,
        'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
function ainder_clear_session(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}
```

- [ ] **Step 4: Implement the existing-member repository**

```php
<?php
declare(strict_types=1);
function ainder_database(array $config): mysqli {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = new mysqli($config['db_host'], $config['db_user'], $config['db_password'], $config['db_name']);
    $db->set_charset('utf8mb4'); return $db;
}
function ainder_find_member(mysqli $db, string $sub): ?array {
    $statement = $db->prepare('SELECT id, status FROM users WHERE google_sub = ? LIMIT 1');
    $statement->bind_param('s', $sub); $statement->execute();
    $member = $statement->get_result()->fetch_assoc();
    return is_array($member) ? $member : null;
}
function ainder_record_login(mysqli $db, int $id): void {
    $statement = $db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $statement->bind_param('i', $id); $statement->execute();
}
```

- [ ] **Step 5: Implement `google.php` with `Google\Client::verifyIdToken()` and reject empty credentials or Client IDs**

```php
<?php
declare(strict_types=1);
function ainder_verify_google_token(string $credential, string $clientId): ?array {
    require_once dirname(__DIR__, 2).'/vendor/autoload.php';
    if ($credential === '' || $clientId === '') return null;
    $client = new Google\Client(['client_id' => $clientId]);
    $payload = $client->verifyIdToken($credential);
    return is_array($payload) ? $payload : null;
}
```

- [ ] **Step 6: Implement the POST-only endpoint**

Validate Google's double-submit CSRF token, verify and normalize the ID token, query by `google_sub`, regenerate the session ID, then execute exactly one branch:

```php
if (($member['status'] ?? null) === 'active') {
    $_SESSION['ainder_member_id'] = (int) $member['id'];
    ainder_record_login($db, (int) $member['id']);
} else {
    $_SESSION['ainder_pending_identity'] = $identity;
    $_SESSION['ainder_pending_expires_at'] = time() + 1800;
}
header('Location: '.ainder_login_destination($member));
exit;
```

The complete endpoint includes the required files, rejects non-POST requests, uses generic failure responses, and contains no insert:

```php
<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/auth.php';
require_once dirname(__DIR__).'/lib/config.php';
require_once dirname(__DIR__).'/lib/database.php';
require_once dirname(__DIR__).'/lib/google.php';
require_once dirname(__DIR__).'/lib/session.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /ainder/'); exit; }
if (!ainder_google_csrf_is_valid((string) ($_COOKIE['g_csrf_token'] ?? ''), (string) ($_POST['g_csrf_token'] ?? ''))) {
    header('Location: /ainder/?login=failed'); exit;
}
$config = ainder_config();
$payload = ainder_verify_google_token((string) ($_POST['credential'] ?? ''), $config['google_client_id']);
$identity = is_array($payload) ? ainder_normalize_google_identity($payload) : null;
if ($identity === null) { header('Location: /ainder/?login=failed'); exit; }
try { $db = ainder_database($config); $member = ainder_find_member($db, $identity['google_sub']); }
catch (Throwable) { http_response_code(503); exit('Ainder is temporarily unavailable.'); }
ainder_start_session(); session_regenerate_id(true);
unset($_SESSION['ainder_member_id'], $_SESSION['ainder_pending_identity'], $_SESSION['ainder_pending_expires_at']);
if (($member['status'] ?? null) === 'active') {
    $_SESSION['ainder_member_id'] = (int) $member['id']; ainder_record_login($db, (int) $member['id']);
} else {
    $_SESSION['ainder_pending_identity'] = $identity; $_SESSION['ainder_pending_expires_at'] = time() + 1800;
}
header('Location: '.ainder_login_destination($member)); exit;
```

- [ ] **Step 7: Run `php tests/run.php` and PHP syntax checks; expect PASS**

- [ ] **Step 8: Commit with `git commit -m "feat: add Ainder Google authentication boundary"`**

### Task 3: Responsive Landing and Guarded Placeholders

**Files:**
- Create: `tests/page_contract_test.php`
- Create: `web/index.php`
- Create: `web/profile/index.php`
- Create: `web/app/index.php`
- Create: `web/placeholder.php`
- Create: `web/logout.php`
- Create: `web/assets/app.css`
- Copy: approved desktop hero, mobile hero, and white logo into `web/assets/`

- [ ] **Step 1: Write failing page contract tests**

```php
<?php
$root = dirname(__DIR__);
test('landing declares responsive hero, logo, and Google login', function () use ($root): void {
    $source = file_get_contents($root.'/web/index.php');
    foreach (['ainder-hero-mobile.webp', 'ainder-hero-desktop.webp', 'ainder-logo-white.webp',
              'accounts.google.com/gsi/client', '/ainder/auth/google.php'] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
});
test('placeholder pages enforce separate session states', function () use ($root): void {
    expect_same(true, str_contains(file_get_contents($root.'/web/profile/index.php'), 'ainder_pending_identity_is_valid'));
    expect_same(true, str_contains(file_get_contents($root.'/web/app/index.php'), 'ainder_member_id'));
});
test('web source contains no production credential', function () use ($root): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/web'));
    foreach ($files as $file) if ($file->isFile() && preg_match('/\\.(php|css|js|html)$/', $file->getFilename())) {
        $source = file_get_contents($file->getPathname());
        expect_same(false, preg_match('/Bobo@|sk-[A-Za-z0-9]|client_secret/i', $source) === 1);
    }
});
```

- [ ] **Step 2: Run `php tests/run.php`; expect missing-page failures**

- [ ] **Step 3: Copy approved assets**

```bash
mkdir -p web/assets
cp assets/images/ainder-hero-desktop-v2.webp web/assets/ainder-hero-desktop.webp
cp assets/images/ainder-hero-mobile-v2.webp web/assets/ainder-hero-mobile.webp
cp assets/images/ainder-logo-white.webp web/assets/ainder-logo-white.webp
```

- [ ] **Step 4: Implement `index.php`**

Use a `<picture>` with a `720px` mobile source, full-viewport image, dark overlay, white logo, and this Google markup. Redirect an existing member session to `/ainder/app/`.

```html
<script src="https://accounts.google.com/gsi/client" async defer></script>
<div id="g_id_onload"
     data-client_id="<?= htmlspecialchars($clientId, ENT_QUOTES, 'UTF-8') ?>"
     data-login_uri="https://sweety.tw/ainder/auth/google.php"
     data-auto_prompt="false"></div>
<div class="g_id_signin" data-type="standard" data-shape="pill"
     data-theme="outline" data-text="signin_with" data-size="large"></div>
```

Wrap that markup in a complete PHP page which starts Ainder's session, redirects `ainder_member_id` to `/ainder/app/`, loads `ainder_config()`, emits `<picture>` sources for both hero files, and HTML-escapes the Client ID. When the Client ID is empty, render a non-interactive `Google login unavailable` pill rather than an invalid button.

- [ ] **Step 5: Implement dark responsive CSS and placeholder guards**

```css
:root { color-scheme: dark; font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
* { box-sizing: border-box; }
html, body { margin: 0; min-height: 100%; background: #111319; color: #f7f5f2; }
.landing { min-height: 100dvh; overflow: hidden; }
.hero, .hero img, .shade { position: fixed; inset: 0; width: 100%; height: 100%; }
.hero img { object-fit: cover; object-position: center; }
.shade { background: linear-gradient(180deg, rgba(8,9,13,.54), transparent 32%, rgba(8,9,13,.2)); }
.corner-bar { position: relative; z-index: 1; display: flex; justify-content: space-between; padding: clamp(1rem, 2.3vw, 2rem); }
.logo { width: clamp(132px, 13vw, 210px); height: auto; }
.placeholder { min-height: 100dvh; display: grid; place-items: center; text-align: center; background: radial-gradient(circle at top, #262936, #111319 58%); }
@media (max-width: 720px) { .corner-bar { padding: 1rem; align-items: center; } .logo { width: 124px; } }
```

`profile/index.php` requires a valid pending identity; `app/index.php` requires `ainder_member_id`; invalid access redirects to `/ainder/`. `logout.php` clears only Ainder's session.

```php
<?php
// web/profile/index.php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/auth.php';
require_once dirname(__DIR__).'/lib/session.php';
ainder_start_session();
if (!ainder_pending_identity_is_valid($_SESSION, time())) { header('Location: /ainder/'); exit; }
$title = 'Complete your profile'; $message = 'Profile setup is coming next.';
require dirname(__DIR__).'/placeholder.php';
```

```php
<?php
// web/app/index.php
declare(strict_types=1);
require_once dirname(__DIR__).'/lib/session.php';
ainder_start_session();
if (!isset($_SESSION['ainder_member_id'])) { header('Location: /ainder/'); exit; }
$title = 'Ainder'; $message = 'Your Ainder home is coming next.';
require dirname(__DIR__).'/placeholder.php';
```

```php
<?php
// web/logout.php
declare(strict_types=1);
require_once __DIR__.'/lib/session.php';
ainder_start_session(); ainder_clear_session();
header('Location: /ainder/'); exit;
```

- [ ] **Step 6: Run tests and all PHP syntax checks; expect PASS**

- [ ] **Step 7: Commit with `git commit -m "feat: add Ainder landing and guarded placeholders"`**

### Task 4: Independent Database Migration

**Files:**
- Create: `web/migrations/001_create_ainder.php`
- Modify: `tests/page_contract_test.php`

- [ ] **Step 1: Add a failing migration contract test**

```php
test('migration creates only Ainder database and users table', function () use ($root): void {
    $source = file_get_contents($root.'/web/migrations/001_create_ainder.php');
    expect_same(true, str_contains($source, 'CREATE DATABASE IF NOT EXISTS ainder'));
    expect_same(true, str_contains($source, 'CREATE TABLE IF NOT EXISTS users'));
    expect_same(true, str_contains($source, 'UNIQUE KEY users_google_sub_unique'));
    expect_same(false, preg_match('/(?:INSERT|USE)\\s+sweety/i', $source) === 1);
});
```

- [ ] **Step 2: Run tests; expect the migration-file failure**

- [ ] **Step 3: Implement a token-protected migration**

Load the same host/user/password in config-only mode, connect without selecting Sweety, create `ainder` with `utf8mb4_unicode_ci`, select it, and create `users` with the approved schema. Accept the one-time token from CLI argument or HTTP query, return 403 on mismatch, and output only:

```json
{"ok":true,"database":"ainder","table":"users"}
```

```php
<?php
declare(strict_types=1);
$local = require dirname(__DIR__).'/config.local.php';
$provided = PHP_SAPI === 'cli' ? (string) ($argv[1] ?? '') : (string) ($_GET['token'] ?? '');
if (($local['migration_token'] ?? '') === '' || !hash_equals((string) $local['migration_token'], $provided)) {
    http_response_code(403); exit('Forbidden');
}
define('SWEETY_MYSQL_CONFIG_ONLY', true);
require dirname(__DIR__, 2).'/mysql.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = new mysqli($mysqlhost, $mysqluser, $mysqlpasswd); $db->set_charset('utf8mb4');
$db->query('CREATE DATABASE IF NOT EXISTS ainder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$db->select_db('ainder');
$db->query("CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, google_sub VARCHAR(255) NOT NULL,
 email VARCHAR(320) NOT NULL, display_name VARCHAR(120) NOT NULL, avatar_url TEXT NULL,
 status ENUM('active','disabled') NOT NULL DEFAULT 'active', last_login_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 PRIMARY KEY (id), UNIQUE KEY users_google_sub_unique (google_sub), KEY users_email_index (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'database' => 'ainder', 'table' => 'users']);
```

- [ ] **Step 4: Run tests and PHP syntax checks; expect PASS**

- [ ] **Step 5: Commit with `git commit -m "feat: add independent Ainder database migration"`**

### Task 5: Deploy and Verify Live Behavior

**Files:**
- Create locally, never commit: `web/config.local.php`
- Deploy: contents of `web/` to `/Volumes/sweety.tw/ainder/`
- Remove after use: deployed `migrations/001_create_ainder.php`

- [ ] **Step 1: Resolve the Google Client ID**

Confirm a Google web client authorizes origin `https://sweety.tw` and login URI `https://sweety.tw/ainder/auth/google.php`. Put its public Client ID and a fresh migration token in untracked `web/config.local.php`; store no Client Secret.

- [ ] **Step 2: Run final local verification**

```bash
php tests/run.php
find web tests -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check
git status --short
```

Expected: all tests and syntax checks pass; no credential is staged.

- [ ] **Step 3: Deploy to the mounted directory**

```bash
rsync -av --delete --exclude 'config.local.example.php' web/ /Volumes/sweety.tw/ainder/
```

Expected: `/Volumes/sweety.tw/ainder/` contains the application and production local config.

- [ ] **Step 4: Execute the migration and immediately remove only the deployed migration endpoint**

Expected migration response: `{"ok":true,"database":"ainder","table":"users"}`. A second request after removal returns 404.

- [ ] **Step 5: Verify HTTP guards**

```bash
curl -I https://sweety.tw/ainder/
curl -I https://sweety.tw/ainder/profile/
curl -I https://sweety.tw/ainder/app/
```

Expected: landing 200; unauthenticated placeholders redirect to `/ainder/`; the original 403 is gone.

- [ ] **Step 6: Verify desktop and mobile in a real browser**

Confirm correct responsive image/crop, logo and Google button positions, no navbar, no horizontal overflow, and readable dark treatment.

- [ ] **Step 7: Verify non-member routing without persistence**

Sign in with a Google account absent from `ainder.users`, confirm `/ainder/profile/`, and verify row count did not change. Exercise the active-member branch through tests or a separately authorized fixture; do not create a production member merely for testing.

- [ ] **Step 8: Verify hygiene**

Confirm the deployed migration endpoint is absent, `config.local.php` is untracked and not downloadable as source, no sensitive value appears in public HTML/logs, and all queries connect to `ainder` rather than `sweety`.

- [ ] **Step 9: Commit only if live verification required tracked corrections**

```bash
git add web tests assets
git commit -m "fix: finalize live Ainder authentication slice"
```
