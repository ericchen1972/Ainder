# Ainder Test Login Notice Refinement Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Shorten the two test-login labels, move the account controls upward, and add one English-only reset notice below them.

**Architecture:** Keep the existing database-backed login forms and transactional endpoint unchanged. Refine only the landing-page markup, test-login layout container, and responsive CSS, with source-contract and live browser verification.

**Tech Stack:** PHP server rendering, CSS media queries, existing custom PHP test harness, In Browser responsive verification.

---

### Task 1: Lock the refined copy and shared layout contract

**Files:**
- Modify: `tests/page_contract_test.php`
- Modify: `web/index.php`

- [ ] **Step 1: Write the failing contract test**

Add assertions requiring the short labels and exact notice while rejecting the old full-name labels:

```php
test('landing uses short test names and an English reset notice', function () use ($root): void {
    $page = file_get_contents($root.'/web/index.php');

    foreach ([
        'test-login-accounts',
        'test-login-alert',
        'Login as Grace',
        'Login as John',
        'Test account activity is reset, so Likes, Matches, and Messages are not retained.',
        'For the most accurate experience, sign in with your own Google account and use ChatGPT with long-term memory about you.',
    ] as $needle) {
        expect_same(true, str_contains($page, $needle));
    }
    expect_same(false, str_contains($page, 'Login as Grace Liu'));
    expect_same(false, str_contains($page, 'Login as John Carter'));
    expect_same(false, str_contains($page, 'HTTP_ACCEPT_LANGUAGE'));
    expect_same(false, str_contains($page, 'navigator.language'));
});
```

- [ ] **Step 2: Run and verify RED**

Run: `php tests/run.php`

Expected: FAIL because the old labels remain and the notice is absent.

- [ ] **Step 3: Implement the refined markup**

Inside `.test-login-panel`, wrap the two forms in `.test-login-accounts`. Render the label from the fixed slug so `grace` becomes `Grace` and `john` becomes `John`, without changing the full account label stored in the scenario. Add:

```html
<aside class="test-login-alert" role="note">
    <strong>Test account</strong>
    <span>Test account activity is reset, so Likes, Matches, and Messages are not retained. For the most accurate experience, sign in with your own Google account and use ChatGPT with long-term memory about you.</span>
</aside>
```

Keep the page English-only and do not add browser-language detection.

- [ ] **Step 4: Run and verify GREEN**

Run: `php tests/run.php`

Expected: all PHP tests pass.

### Task 2: Move the controls upward and style the alert responsively

**Files:**
- Modify: `tests/page_contract_test.php`
- Modify: `web/assets/app.css`

- [ ] **Step 1: Write the failing CSS contract**

Require `.test-login-panel` to be a vertical container, `.test-login-accounts` to retain the horizontal pair, and `.test-login-alert` to have a bounded width, translucent surface, border, and mobile safe-area behavior.

```php
foreach ([
    '.test-login-accounts',
    '.test-login-alert',
    'flex-direction: column',
    'backdrop-filter: blur',
    'env(safe-area-inset-bottom)',
] as $needle) {
    expect_same(true, str_contains($css, $needle));
}
```

- [ ] **Step 2: Run and verify RED**

Run: `php tests/run.php`

Expected: FAIL because the new layout selectors are absent.

- [ ] **Step 3: Implement desktop and mobile layout**

Make `.test-login-panel` a vertical lower-center container with a larger bottom offset than the current `clamp(2rem, 7vh, 5rem)`. Move the existing horizontal flex rules to `.test-login-accounts`. Style the notice as a centered translucent card no wider than 620 px. At 720 px or below, use a full-width bounded container, compact spacing, and `bottom: calc(1rem + env(safe-area-inset-bottom))`; reduce alert typography without hiding or truncating it.

- [ ] **Step 4: Run automated verification**

```bash
php tests/run.php
node --test tests/browse_model_test.mjs tests/profile_editor_model_test.mjs
find web tests -type f -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check
```

Expected: zero failures and zero syntax errors.

- [ ] **Step 5: Commit, deploy, and verify**

Commit the markup, CSS, and test changes. Deploy with the existing rsync exclusions for `config.local.php` and `uploads/`. Verify desktop and 393 CSS px mobile screenshots, exact English copy, undistorted circular photos, no overflow, and no console errors. Push `main` and confirm local HEAD equals `origin/main`.

