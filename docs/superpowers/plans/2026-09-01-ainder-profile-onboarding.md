# Ainder Profile Onboarding Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enable the supplied Google Client ID and let new verified Google users register as Ainder members by submitting valid personal data and 2–6 profile photos.

**Architecture:** Extend the existing PHP application with pure registration validators, a photo-upload service, a transactional member repository, and a POST registration endpoint. The profile page uses the approved in-place expansion design; Google identity remains in the pending session until a complete registration succeeds.

**Tech Stack:** PHP 8.2, mysqli/InnoDB, Google Identity Services, plain PHP tests, HTML/CSS, vanilla JavaScript, multipart image uploads.

---

## File Map

- `web/lib/registration.php` — name, gender, age, and photo-count validation.
- `web/lib/photos.php` — uploaded-image validation, staging, final moves, and cleanup.
- `web/lib/database.php` — transactional member/photo creation and duplicate lookup.
- `web/lib/session.php` — form CSRF and flash state helpers.
- `web/profile/index.php` — Agent-first onboarding page and manual form.
- `web/profile/register.php` — POST-only registration orchestration.
- `web/assets/profile.js` — expand form, preview/remove files, and enable submit.
- `web/assets/app.css` — onboarding layout and responsive styling.
- `web/migrations/001_create_ainder.php` — final initial `users` and `user_photos` schema.
- `tests/registration_test.php` — age and field validation.
- `tests/photo_test.php` — upload count, size, and MIME validation.
- `tests/profile_contract_test.php` — rendered-field and endpoint contracts.
- `tests/run.php` — load the new suites.

### Task 1: Registration Rules

**Files:**
- Create: `tests/registration_test.php`
- Create: `web/lib/registration.php`
- Modify: `tests/run.php`

- [ ] **Step 1: Add the failing registration tests**

```php
<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/web/lib/registration.php';

test('exact eighteenth birthday is accepted', function (): void {
    expect_same([], ainder_validate_registration_fields(
        ['display_name' => 'Eva', 'birth_date' => '2008-09-01', 'gender' => 'female'],
        new DateTimeImmutable('2026-09-01')
    ));
});

test('one day under eighteen is rejected', function (): void {
    $errors = ainder_validate_registration_fields(
        ['display_name' => 'Eva', 'birth_date' => '2008-09-02', 'gender' => 'female'],
        new DateTimeImmutable('2026-09-01')
    );
    expect_same('你必須年滿 18 歲。', $errors['birth_date']);
});

test('name and binary gender are required', function (): void {
    $errors = ainder_validate_registration_fields(
        ['display_name' => '', 'birth_date' => '1990-01-01', 'gender' => 'other'],
        new DateTimeImmutable('2026-09-01')
    );
    expect_same(true, isset($errors['display_name']));
    expect_same(true, isset($errors['gender']));
});

test('invalid calendar date is rejected', function (): void {
    $errors = ainder_validate_registration_fields(
        ['display_name' => 'Eric', 'birth_date' => '2000-02-30', 'gender' => 'male'],
        new DateTimeImmutable('2026-09-01')
    );
    expect_same(true, isset($errors['birth_date']));
});
```

Add `require __DIR__.'/registration_test.php';` to `tests/run.php`.

- [ ] **Step 2: Run `php tests/run.php`; expect failure because `registration.php` is absent**

- [ ] **Step 3: Implement the minimal validator**

```php
<?php
declare(strict_types=1);

function ainder_validate_registration_fields(array $input, DateTimeImmutable $today): array
{
    $errors = [];
    $name = trim((string) ($input['display_name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 120) {
        $errors['display_name'] = '請輸入 1–120 個字的名字。';
    }

    $birthValue = (string) ($input['birth_date'] ?? '');
    $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $birthValue);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (!$birth || ($dateErrors !== false && ($dateErrors['warning_count'] || $dateErrors['error_count']))) {
        $errors['birth_date'] = '請輸入有效的生日。';
    } elseif ($birth->modify('+18 years') > $today->setTime(0, 0)) {
        $errors['birth_date'] = '你必須年滿 18 歲。';
    }

    if (!in_array($input['gender'] ?? '', ['male', 'female'], true)) {
        $errors['gender'] = '請選擇男性或女性。';
    }
    return $errors;
}
```

- [ ] **Step 4: Run `php tests/run.php`; expect all registration tests to pass**

- [ ] **Step 5: Commit**

```bash
git add tests/run.php tests/registration_test.php web/lib/registration.php
git commit -m "feat: validate Ainder registration fields"
```

### Task 2: Photo Validation and Staging

**Files:**
- Create: `tests/photo_test.php`
- Create: `web/lib/photos.php`
- Modify: `tests/run.php`

- [ ] **Step 1: Add failing tests for counts, size, and detected MIME**

```php
<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/web/lib/photos.php';

test('two through six photos are accepted', function (): void {
    expect_same([], ainder_validate_photo_count([['error' => UPLOAD_ERR_OK], ['error' => UPLOAD_ERR_OK]]));
    expect_same([], ainder_validate_photo_count(array_fill(0, 6, ['error' => UPLOAD_ERR_OK])));
});
test('one or seven photos are rejected', function (): void {
    expect_same(true, isset(ainder_validate_photo_count([['error' => UPLOAD_ERR_OK]])['photos']));
    expect_same(true, isset(ainder_validate_photo_count(array_fill(0, 7, ['error' => UPLOAD_ERR_OK]))['photos']));
});
test('oversized photo is rejected', function (): void {
    expect_same('每張照片不可超過 10MB。', ainder_validate_photo_file(
        ['error' => UPLOAD_ERR_OK, 'size' => 10485761, 'tmp_name' => __FILE__]
    ));
});
test('non-image content is rejected', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'ainder-photo-');
    file_put_contents($path, 'not an image');
    expect_same('只接受 JPG、PNG 或 WebP 圖片。', ainder_validate_photo_file(
        ['error' => UPLOAD_ERR_OK, 'size' => filesize($path), 'tmp_name' => $path]
    ));
    unlink($path);
});
```

Add `require __DIR__.'/photo_test.php';` to `tests/run.php`.

- [ ] **Step 2: Run `php tests/run.php`; expect failure because `photos.php` is absent**

- [ ] **Step 3: Implement upload normalization and validation**

```php
<?php
declare(strict_types=1);
const AINDER_MAX_PHOTO_BYTES = 10 * 1024 * 1024;
const AINDER_PHOTO_MIME_EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

function ainder_normalize_uploads(array $files): array
{
    $normalized = [];
    foreach (($files['name'] ?? []) as $index => $name) {
        $normalized[] = [
            'name' => (string) $name,
            'tmp_name' => (string) ($files['tmp_name'][$index] ?? ''),
            'size' => (int) ($files['size'][$index] ?? 0),
            'error' => (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
        ];
    }
    return $normalized;
}

function ainder_validate_photo_count(array $photos): array
{
    $successful = array_values(array_filter($photos, fn (array $photo): bool => $photo['error'] === UPLOAD_ERR_OK));
    return count($successful) >= 2 && count($successful) <= 6
        ? [] : ['photos' => '請上傳 2–6 張照片。'];
}

function ainder_validate_photo_file(array $photo): ?string
{
    if ($photo['error'] !== UPLOAD_ERR_OK) return '照片上傳失敗，請重新選擇。';
    if ($photo['size'] > AINDER_MAX_PHOTO_BYTES) return '每張照片不可超過 10MB。';
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($photo['tmp_name']);
    return isset(AINDER_PHOTO_MIME_EXTENSIONS[$mime]) ? null : '只接受 JPG、PNG 或 WebP 圖片。';
}
```

- [ ] **Step 4: Add staging, final-move, and cleanup functions using random 32-character hex names and `move_uploaded_file()`**

The functions return `{temporary_path, extension}` entries, move them into `uploads/profiles/<member-id>/`, and delete every staged or final path passed to cleanup. Unit tests use a move callback so real temporary files exercise cleanup without test-only production methods.

- [ ] **Step 5: Run tests and syntax checks; expect PASS**

- [ ] **Step 6: Commit**

```bash
git add tests/run.php tests/photo_test.php web/lib/photos.php
git commit -m "feat: validate and stage Ainder photos"
```

### Task 3: Initial Database Schema and Transactional Repository

**Files:**
- Modify: `web/migrations/001_create_ainder.php`
- Modify: `web/lib/database.php`
- Modify: `tests/page_contract_test.php`

- [ ] **Step 1: Extend the failing migration contract**

Assert the migration contains `birth_date DATE NOT NULL`, `gender ENUM('male', 'female')`, `CREATE TABLE IF NOT EXISTS user_photos`, the unique `(user_id, sort_order)` key, and the foreign key to `users`.

- [ ] **Step 2: Run `php tests/run.php`; expect schema assertions to fail**

- [ ] **Step 3: Update the initial migration with the exact approved `users` and `user_photos` schemas**

The migration has not run in production, so it creates the final schema directly and does not include an ALTER path.

- [ ] **Step 4: Add `ainder_create_member_with_photos()`**

```php
function ainder_create_member_with_photos(mysqli $db, array $identity, array $input, array $paths): int
{
    $db->begin_transaction();
    try {
        $user = $db->prepare('INSERT INTO users (google_sub,email,display_name,birth_date,gender) VALUES (?,?,?,?,?)');
        $user->bind_param('sssss', $identity['google_sub'], $identity['email'], $input['display_name'], $input['birth_date'], $input['gender']);
        $user->execute();
        $userId = (int) $db->insert_id;
        $photo = $db->prepare('INSERT INTO user_photos (user_id,file_path,sort_order) VALUES (?,?,?)');
        foreach ($paths as $index => $path) {
            $order = $index + 1;
            $photo->bind_param('isi', $userId, $path, $order);
            $photo->execute();
        }
        $db->commit();
        return $userId;
    } catch (Throwable $error) {
        $db->rollback();
        throw $error;
    }
}
```

- [ ] **Step 5: Run tests and syntax checks; expect PASS**

- [ ] **Step 6: Commit**

```bash
git add web/migrations/001_create_ainder.php web/lib/database.php tests/page_contract_test.php
git commit -m "feat: add Ainder member and photo schema"
```

### Task 4: Registration Endpoint and Failure Cleanup

**Files:**
- Modify: `web/lib/session.php`
- Create: `web/profile/register.php`
- Create: `tests/profile_contract_test.php`
- Modify: `tests/run.php`

- [ ] **Step 1: Write failing endpoint and session contract tests**

```php
<?php
declare(strict_types=1);
$root = dirname(__DIR__);
test('registration endpoint requires pending identity and CSRF', function () use ($root): void {
    $source = file_get_contents($root.'/web/profile/register.php');
    expect_same(true, str_contains($source, 'ainder_pending_identity_is_valid'));
    expect_same(true, str_contains($source, 'ainder_form_csrf_is_valid'));
});
test('registration endpoint never accepts email or google sub from post', function () use ($root): void {
    $source = file_get_contents($root.'/web/profile/register.php');
    expect_same(false, preg_match('/_POST[^;]*(email|google_sub)/', $source) === 1);
});
test('registration failure cleans staged and final files', function () use ($root): void {
    $source = file_get_contents($root.'/web/profile/register.php');
    expect_same(true, str_contains($source, 'ainder_cleanup_photo_paths'));
    expect_same(true, str_contains($source, 'catch (Throwable'));
});
```

Load `profile_contract_test.php` from `tests/run.php`.

- [ ] **Step 2: Run tests; expect failure because `register.php` is absent**

- [ ] **Step 3: Add form CSRF and one-request flash helpers**

```php
function ainder_form_csrf_token(): string {
    if (!isset($_SESSION['ainder_form_csrf'])) $_SESSION['ainder_form_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['ainder_form_csrf'];
}
function ainder_form_csrf_is_valid(string $submitted): bool {
    return isset($_SESSION['ainder_form_csrf']) && $submitted !== ''
        && hash_equals($_SESSION['ainder_form_csrf'], $submitted);
}
function ainder_set_form_flash(array $errors, array $input): void {
    $_SESSION['ainder_form_flash'] = ['errors' => $errors, 'input' => $input];
}
function ainder_pull_form_flash(): array {
    $flash = $_SESSION['ainder_form_flash'] ?? ['errors' => [], 'input' => []];
    unset($_SESSION['ainder_form_flash']);
    return $flash;
}
```

- [ ] **Step 4: Implement the POST-only endpoint**

The endpoint starts the Ainder session, verifies pending identity and CSRF, reads only `display_name`, `birth_date`, and `gender` from POST, normalizes uploads, validates everything, stages photos, begins member creation, moves files, inserts ordered rows, clears pending state, stores `ainder_member_id`, and redirects to `/ainder/app/`.

Validation failure stores field/input flash state and redirects to `/ainder/profile/?manual=1`. Any exception calls `ainder_cleanup_photo_paths()` for all paths from the attempt and returns the same generic form error. A duplicate-member database error re-queries by session `google_sub` and signs in the existing active member.

- [ ] **Step 5: Run all tests and syntax checks; expect PASS**

- [ ] **Step 6: Commit**

```bash
git add web/lib/session.php web/profile/register.php tests/run.php tests/profile_contract_test.php
git commit -m "feat: register Ainder members atomically"
```

### Task 5: Agent-First Profile Page

**Files:**
- Modify: `web/profile/index.php`
- Create: `web/assets/profile.js`
- Modify: `web/assets/app.css`
- Modify: `tests/profile_contract_test.php`

- [ ] **Step 1: Add failing page contract tests**

```php
test('profile page leads with Agent message and manual action', function () use ($root): void {
    $source = file_get_contents($root.'/web/profile/index.php');
    expect_same(true, str_contains($source, '你可以讓 Agent 為你填寫個人資訊'));
    expect_same(true, str_contains($source, '手動填寫'));
    expect_same(true, str_contains($source, 'aria-expanded'));
});
test('profile form contains only approved personal fields', function () use ($root): void {
    $source = file_get_contents($root.'/web/profile/index.php');
    foreach (['display_name', 'birth_date', 'gender', 'photos[]'] as $field) {
        expect_same(true, str_contains($source, $field));
    }
    foreach (['有興趣的對象', '我想尋找', '是否在個人資料顯示性別'] as $excluded) {
        expect_same(false, str_contains($source, $excluded));
    }
});
test('photo input enforces two to six in script and server', function () use ($root): void {
    $script = file_get_contents($root.'/web/assets/profile.js');
    expect_same(true, str_contains($script, 'selectedFiles.length >= 2'));
    expect_same(true, str_contains($script, 'selectedFiles.length <= 6'));
});
```

- [ ] **Step 2: Run tests; expect the new UI assertions to fail**

- [ ] **Step 3: Render the guarded onboarding page**

The page pulls flash state, computes the maximum selectable birth date as `today - 18 years`, pre-fills the verified Google name/email, and renders:

```html
<header class="profile-header"><img src="/ainder/assets/ainder-logo-white.webp" alt="Ainder"></header>
<main class="onboarding-shell">
  <section class="agent-intro">
    <h1>你可以讓 Agent 為你填寫個人資訊</h1>
    <button type="button" class="manual-toggle" aria-expanded="false" aria-controls="manual-form">手動填寫</button>
  </section>
  <section id="manual-form" class="manual-form" hidden>
    <form action="/ainder/profile/register.php" method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(ainder_form_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
      <label>名字<input name="display_name" maxlength="120" required></label>
      <label>電子郵件<input value="<?= htmlspecialchars($identity['email'], ENT_QUOTES, 'UTF-8') ?>" readonly></label>
      <label>生日<input type="date" name="birth_date" max="<?= $maximumBirthDate ?>" required></label>
      <fieldset><legend>性別</legend>
        <label><input type="radio" name="gender" value="male" required>男性</label>
        <label><input type="radio" name="gender" value="female" required>女性</label>
      </fieldset>
      <label class="photo-picker">新增照片<input id="photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required></label>
      <div class="photo-grid" aria-live="polite"></div>
      <button class="register-submit" type="submit" disabled>建立帳號</button>
    </form>
  </section>
</main>
<script src="/ainder/assets/profile.js" defer></script>
```

Each field renders its flash error. If `?manual=1` or flash errors exist, render the section visible and `aria-expanded=true`.

- [ ] **Step 4: Implement file selection, preview, removal, and submit state**

```js
const input = document.querySelector('#photos');
const submit = document.querySelector('.register-submit');
let selectedFiles = [];
function syncInput() {
  const transfer = new DataTransfer();
  selectedFiles.forEach((file) => transfer.items.add(file));
  input.files = transfer.files;
  submit.disabled = !(selectedFiles.length >= 2 && selectedFiles.length <= 6);
}
input.addEventListener('change', () => {
  selectedFiles = [...selectedFiles, ...input.files].slice(0, 6);
  syncInput();
  renderPhotoSlots();
});
```

`renderPhotoSlots()` creates object-URL previews, revokes old URLs before re-render, and gives every populated slot a remove button. The manual toggle removes `hidden`, updates `aria-expanded`, and scrolls the form into view.

- [ ] **Step 5: Add responsive styles**

Desktop uses a centered two-column dark surface with fields left and a three-by-two photo grid right. At `720px` and below, use one column, retain the six-slot grid, keep 44px minimum interactive controls, and enforce `overflow-x: hidden`.

- [ ] **Step 6: Run tests, syntax checks, and a local static rendering check; expect PASS**

- [ ] **Step 7: Commit**

```bash
git add web/profile/index.php web/assets/profile.js web/assets/app.css tests/profile_contract_test.php
git commit -m "feat: add Agent-first Ainder onboarding form"
```

### Task 6: Configure, Deploy, Migrate, and Verify

**Files:**
- Create locally, do not commit: `web/config.local.php`
- Deploy: full `web/` runtime to `/Volumes/sweety.tw/ainder/`
- Remove after use: deployed `migrations/001_create_ainder.php`

- [ ] **Step 1: Create untracked production configuration**

Generate a token with `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`, then use the exact 64-character output with the supplied Client ID:

```php
<?php
return [
    'google_client_id' => '315346868518-hu4t4do82agusauffh5tdva68a0tbjge.apps.googleusercontent.com',
    'migration_token' => 'the-exact-64-character-output-from-the-command-above',
];
```

Verify `git status --short` does not list `web/config.local.php`.

- [ ] **Step 2: Verify host upload limits before migration**

Use a temporary token-protected diagnostic endpoint to read only `upload_max_filesize`, `post_max_size`, `max_file_uploads`, the `fileinfo` extension, and directory writeability. Remove it immediately. Stop if the host cannot accept six 10MB files or write the profile-upload directory.

- [ ] **Step 3: Run fresh local verification**

```bash
php tests/run.php
find web tests -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check
git status --short
```

Expected: every test passes, every PHP file is valid, and no secret/config file is tracked.

- [ ] **Step 4: Deploy the full runtime**

```bash
rsync -av --delete --exclude 'config.local.example.php' web/ /Volumes/sweety.tw/ainder/
```

- [ ] **Step 5: Run the token-protected migration, verify both tables, and remove the deployed migration endpoint**

Expected JSON: `{"ok":true,"database":"ainder","tables":["users","user_photos"]}`. A subsequent request to the removed endpoint returns 404.

- [ ] **Step 6: Verify Google routing and no early persistence**

Sign in using the configured Google button. Confirm a new identity reaches `/ainder/profile/`; query `ainder.users` and confirm no row exists before registration.

- [ ] **Step 7: Verify validation and successful registration**

Confirm under-18, invalid gender/request manipulation, 1 photo, and 7 photos are rejected. Submit valid data with 2 photos, then verify exactly one member and two ordered photo rows exist and the session redirects to `/ainder/app/`.

- [ ] **Step 8: Verify repeat login and responsive layout**

Log out and sign in again; confirm direct routing to `/ainder/app/`. Check desktop and mobile for initial Agent message, manual expansion, readonly email, 2–6 photo previews/removal, no excluded fields, and no overflow.

- [ ] **Step 9: Verify production hygiene**

Confirm the migration/diagnostic endpoints are absent, PHP execution is disabled under the upload directory where supported, no credentials/tokens appear in HTML or logs, and the application queries only the `ainder` database.
