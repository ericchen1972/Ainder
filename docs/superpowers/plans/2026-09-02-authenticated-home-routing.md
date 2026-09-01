# Authenticated Home Routing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Route returning Google-authenticated visitors to the correct Ainder surface and provide a CSRF-protected sign-out button on the authenticated main page.

**Architecture:** Reuse the server-side Ainder session as the source of truth. Add one pure destination helper for landing-page routing, keep the existing profile-expiry helper, and harden the existing logout endpoint to accept only a valid POST request. Render the same logout form in desktop and mobile navigation with responsive CSS.

**Tech Stack:** PHP 8 strict types, PHP sessions, HTML/CSS, repository-local PHP test harness.

---

### Task 1: Landing-page session routing

**Files:**
- Modify: `tests/auth_test.php`
- Modify: `tests/page_contract_test.php`
- Modify: `web/lib/auth.php`
- Modify: `web/index.php`

- [ ] **Step 1: Write failing routing tests**

Add unit cases to `tests/auth_test.php`:

```php
test('home destination follows active and pending session state', function (): void {
    expect_same('/ainder/app/', ainder_home_destination([
        'ainder_member_id' => 42,
    ], 1000));
    expect_same('/ainder/profile/', ainder_home_destination([
        'ainder_pending_identity' => ['google_sub' => 'google-123'],
        'ainder_pending_expires_at' => 1001,
    ], 1000));
    expect_same(null, ainder_home_destination([
        'ainder_pending_identity' => ['google_sub' => 'google-123'],
        'ainder_pending_expires_at' => 1000,
    ], 1000));
    expect_same(null, ainder_home_destination([], 1000));
});
```

Extend the landing-page contract test to require `ainder_home_destination` in `web/index.php`.

- [ ] **Step 2: Run the tests and verify RED**

Run: `php tests/run.php`

Expected: failure because `ainder_home_destination()` does not exist or the landing page does not call it.

- [ ] **Step 3: Implement the minimal routing helper and landing redirect**

Add to `web/lib/auth.php`:

```php
function ainder_home_destination(array $session, int $now): ?string
{
    if (isset($session['ainder_member_id'])) {
        return '/ainder/app/';
    }

    return ainder_pending_identity_is_valid($session, $now)
        ? '/ainder/profile/'
        : null;
}
```

Update `web/index.php` to require `lib/auth.php`, compute the destination after session start, and redirect only when it is non-null:

```php
$destination = ainder_home_destination($_SESSION, time());
if ($destination !== null) {
    header('Location: '.$destination);
    exit;
}
```

- [ ] **Step 4: Run focused tests and verify GREEN**

Run: `php tests/run.php`

Expected: all tests pass.

- [ ] **Step 5: Commit the routing change**

```bash
git add tests/auth_test.php tests/page_contract_test.php web/lib/auth.php web/index.php
git commit -m "fix: route authenticated visitors from landing"
```

### Task 2: Secure main-page sign-out

**Files:**
- Modify: `tests/page_contract_test.php`
- Modify: `web/app/index.php`
- Modify: `web/logout.php`
- Modify: `web/assets/browse.css`

- [ ] **Step 1: Write failing sign-out contract tests**

Add a test to `tests/page_contract_test.php` that requires:

```php
test('authenticated app provides a CSRF-protected POST sign-out', function () use ($root): void {
    $app = file_get_contents($root.'/web/app/index.php');
    $logout = file_get_contents($root.'/web/logout.php');

    expect_same(2, substr_count($app, 'action="/ainder/logout.php"'));
    expect_same(2, substr_count($app, 'name="csrf_token"'));
    expect_same(true, str_contains($app, 'method="post"'));
    expect_same(true, str_contains($app, '>登出<'));
    expect_same(true, str_contains($logout, "\$_SERVER['REQUEST_METHOD']"));
    expect_same(true, str_contains($logout, 'ainder_form_csrf_is_valid'));
    expect_same(true, str_contains($logout, "header('Location: /ainder/app/')"));
});
```

- [ ] **Step 2: Run the tests and verify RED**

Run: `php tests/run.php`

Expected: the sign-out contract test fails because the app has no forms and the endpoint has no POST or CSRF gate.

- [ ] **Step 3: Harden the endpoint**

Before clearing the session in `web/logout.php`, add:

```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !ainder_form_csrf_is_valid((string) ($_POST['csrf_token'] ?? ''))) {
    header('Location: /ainder/app/');
    exit;
}
```

- [ ] **Step 4: Render desktop and mobile sign-out forms**

In `web/app/index.php`, compute `$csrfToken = ainder_form_csrf_token();` once and render this form in both `.member-bar` and `.mobile-bar`:

```php
<form class="logout-form" method="post" action="/ainder/logout.php">
    <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
    <button type="submit">登出</button>
</form>
```

Group the mobile avatar and form in a `.mobile-member-actions` wrapper so the logo remains on the left and member actions remain on the right.

- [ ] **Step 5: Style the responsive button**

Add focused rules to `web/assets/browse.css`:

```css
.logout-form { margin-left: auto; }
.logout-form button {
    padding: 7px 11px;
    border: 1px solid rgba(255,255,255,.48);
    border-radius: 999px;
    background: rgba(10,11,16,.2);
    color: #fff;
    font-size: 12px;
    font-weight: 750;
    cursor: pointer;
}
.logout-form button:focus-visible { outline: 3px solid #ff83a9; outline-offset: 3px; }
.mobile-member-actions { display: flex; align-items: center; gap: 10px; }
```

Update the mobile avatar selector to target `.mobile-member-actions > img`.

- [ ] **Step 6: Run focused and full verification**

Run: `php tests/run.php`

Expected: all tests pass with no failures.

Run: `git diff --check`

Expected: no output and exit status 0.

- [ ] **Step 7: Commit the sign-out change**

```bash
git add tests/page_contract_test.php web/app/index.php web/logout.php web/assets/browse.css
git commit -m "feat: add secure Ainder sign-out"
```

### Task 3: Live browser acceptance

**Files:**
- No source changes expected.

- [ ] **Step 1: Verify incomplete-profile routing**

In the signed-in in-app browser, navigate from `/ainder/profile/` to `/ainder/` and confirm the browser returns to `/ainder/profile/` without displaying Google sign-in.

- [ ] **Step 2: Verify active-member routing**

With an active member session, navigate to `/ainder/` and confirm the browser returns to `/ainder/app/`.

- [ ] **Step 3: Verify sign-out**

On `/ainder/app/`, confirm the `登出` button is visible at desktop and mobile widths. Submit it and confirm the browser reaches `/ainder/` with Google sign-in visible and no authenticated redirect loop.

