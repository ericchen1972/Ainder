> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Tinder-style mobile icon navigation, refine Likes and Message cards, and let the signed-in member update their name and append or replace up to six profile photos from an in-page Modal.

**Architecture:** Keep the existing server-rendered app and progressively enhance it with focused JavaScript modules. A new authenticated multipart endpoint delegates validation and ordered photo persistence to a profile-editor library; the existing `user_photos.sort_order` remains the photo-slot contract, so no migration is needed. The mobile navigation reuses the existing browse and sidebar panels while desktop keeps its current sidebar.

**Tech Stack:** PHP 8, mysqli, vanilla ES modules, HTML dialog, CSS media queries, Font Awesome Free SVG paths embedded locally, the existing 720×1280 WebP image pipeline, and the repository's PHP/Node test runners.

## File map

- Create `web/lib/profile_editor.php`: pure update validation plus transactional name/photo persistence.
- Create `web/api/profile/update.php`: authenticated, CSRF-protected multipart profile-update endpoint.
- Create `web/assets/client-photo-processor.js`: shared browser-side 9:16 WebP processor.
- Create `web/assets/profile-editor.js`: Profile Modal, incremental photo slots, upload processing, and save flow.
- Create `tests/profile_editor_test.php`: pure validation coverage for names, slots, replacement, and append rules.
- Create `tests/profile_editor_model_test.mjs`: browser-model coverage for slot state and six-photo limit.
- Modify `web/assets/profile.js`: import the shared client photo processor instead of defining a second implementation.
- Modify `web/profile/index.php`: load `profile.js` as an ES module.
- Modify `web/app/index.php`: render Likes labels, mobile icon navigation, avatar buttons, and Profile Modal data.
- Modify `web/assets/browse.js`: normalize destination state to Slide/Likes/Messages and coordinate conversation back behavior.
- Modify `web/assets/browse.css`: Card refinements, mobile bottom navigation, icon sizing, and responsive Profile Modal.
- Modify `tests/page_contract_test.php`: UI, endpoint, asset, accessibility, and WebMCP-boundary contracts.
- Modify `tests/profile_contract_test.php`: shared processor module contract.
- Modify `tests/run.php`: load profile-editor unit tests.

## Task 1: Lock the server-side profile update contract

**Files:**
- Create: `tests/profile_editor_test.php`
- Modify: `tests/run.php`
- Create: `web/lib/profile_editor.php`

- [ ] **Step 1: Write failing tests for names and ordered photo slots**

Add tests that call the wished-for pure APIs:

```php
require_once dirname(__DIR__).'/web/lib/profile_editor.php';

test('profile editor accepts a trimmed name and exact replacement slots', function (): void {
    expect_same([], ainder_validate_profile_name(' Eric Chen '));
    expect_same(
        [1 => '/new-main.webp', 2 => '/new-second.webp'],
        ainder_validate_profile_photo_changes(
            2,
            [1, 2],
            ['/new-main.webp', '/new-second.webp']
        )
    );
});

test('profile editor permits only contiguous additions through slot six', function (): void {
    expect_same(
        [3 => '/third.webp', 4 => '/fourth.webp'],
        ainder_validate_profile_photo_changes(
            2,
            [3, 4],
            ['/third.webp', '/fourth.webp']
        )
    );
    expect_throws(
        fn () => ainder_validate_profile_photo_changes(2, [4], ['/gap.webp']),
        InvalidArgumentException::class
    );
    expect_throws(
        fn () => ainder_validate_profile_photo_changes(6, [7], ['/seven.webp']),
        InvalidArgumentException::class
    );
});

test('profile editor rejects deletion-shaped or duplicate slot requests', function (): void {
    expect_throws(
        fn () => ainder_validate_profile_photo_changes(2, [1, 1], ['/a.webp', '/b.webp']),
        InvalidArgumentException::class
    );
    expect_throws(
        fn () => ainder_validate_profile_photo_changes(2, [0], ['/a.webp']),
        InvalidArgumentException::class
    );
});
```

Require the new test file from `tests/run.php`.

- [ ] **Step 2: Run the focused tests and verify RED**

Run:

```bash
php tests/run.php
```

Expected: FAIL because `web/lib/profile_editor.php` or its functions do not exist.

- [ ] **Step 3: Implement minimal pure validation**

Create `web/lib/profile_editor.php` with:

```php
function ainder_validate_profile_name(string $name): array
{
    $trimmed = trim($name);
    return $trimmed !== '' && mb_strlen($trimmed) <= 120
        ? []
        : ['display_name' => 'Name must contain 1–120 characters.'];
}

function ainder_validate_profile_photo_changes(
    int $existingCount,
    array $slots,
    array $paths
): array {
    if ($existingCount < 2 || $existingCount > 6 || count($slots) !== count($paths)) {
        throw new InvalidArgumentException('Invalid profile photo update.');
    }
    $changes = [];
    $nextAppend = $existingCount + 1;
    foreach ($slots as $index => $rawSlot) {
        $slot = (int) $rawSlot;
        if ($slot < 1 || $slot > 6 || isset($changes[$slot])) {
            throw new InvalidArgumentException('Invalid profile photo slot.');
        }
        if ($slot > $existingCount && $slot !== $nextAppend++) {
            throw new InvalidArgumentException('New profile photos must be contiguous.');
        }
        $changes[$slot] = (string) $paths[$index];
    }
    ksort($changes);
    return $changes;
}
```

Also define focused repository functions to fetch ordered member photos and transactionally update `users.display_name`, replace existing `user_photos` rows by `sort_order`, insert contiguous appended rows, reset replaced rows to `source_type='local'`, and return superseded local paths for post-commit cleanup.

- [ ] **Step 4: Run tests and verify GREEN**

Run `php tests/run.php`.

Expected: all existing and new PHP tests pass.

- [ ] **Step 5: Commit the server contract**

```bash
git add web/lib/profile_editor.php tests/profile_editor_test.php tests/run.php
git commit -m "feat: add profile update contract"
```

## Task 2: Add the authenticated multipart endpoint

**Files:**
- Create: `web/api/profile/update.php`
- Modify: `tests/page_contract_test.php`

- [ ] **Step 1: Write failing endpoint contract tests**

Add assertions that the endpoint:

```php
$source = file_get_contents($root.'/web/api/profile/update.php');
foreach ([
    'ainder_member_id',
    'ainder_form_csrf_is_valid',
    'ainder_normalize_uploads',
    'ainder_validate_photo_file',
    'ainder_stage_photos',
    'ainder_finalize_photos',
    'ainder_update_member_profile',
    'ainder_cleanup_photo_paths',
] as $needle) {
    expect_same(true, str_contains($source, $needle));
}
expect_same(false, str_contains($source, 'document.modelContext'));
```

- [ ] **Step 2: Run PHP tests and verify RED**

Run `php tests/run.php`.

Expected: FAIL because `web/api/profile/update.php` is missing.

- [ ] **Step 3: Implement the endpoint**

The endpoint must accept `display_name`, `csrf_token`, `photo_slots[]`, and matching `photos[]`. It must:

```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ainder_json_error('METHOD_NOT_ALLOWED', 'POST is required.', 405);
}
if (!isset($_SESSION['ainder_member_id'])) {
    ainder_json_error('AUTH_REQUIRED', 'Sign in is required.', 401);
}
if (!ainder_form_csrf_is_valid((string) ($_POST['csrf_token'] ?? ''))) {
    ainder_json_error('CSRF_INVALID', 'The form has expired.', 403);
}
```

Validate the current ordered photo count, normalize and validate every processed upload, stage/finalize files under the member's existing upload directory, call `ainder_update_member_profile()`, delete superseded local files only after commit, and return:

```php
ainder_json_response([
    'ok' => true,
    'profile' => [
        'display_name' => $displayName,
        'photos' => $updatedPhotoPaths,
        'avatar_path' => $updatedPhotoPaths[0],
    ],
]);
```

On any failure, clean newly staged/finalized files and preserve the original database state.

- [ ] **Step 4: Run PHP syntax and tests**

Run:

```bash
php -l web/api/profile/update.php
php -l web/lib/profile_editor.php
php tests/run.php
```

Expected: no syntax errors and all tests pass.

- [ ] **Step 5: Commit the endpoint**

```bash
git add web/api/profile/update.php web/lib/profile_editor.php tests/page_contract_test.php
git commit -m "feat: persist member profile edits"
```

## Task 3: Share the browser photo processor

**Files:**
- Create: `web/assets/client-photo-processor.js`
- Modify: `web/assets/profile.js`
- Modify: `web/profile/index.php`
- Modify: `tests/profile_contract_test.php`

- [ ] **Step 1: Write failing shared-processor contracts**

Assert that `client-photo-processor.js` exports `preprocessProfilePhoto`, contains the canonical constants `720`, `1280`, `0.84`, center-crop math, WebP encoding, and object URL cleanup. Assert that both profile registration and the app editor import this module.

- [ ] **Step 2: Run PHP tests and verify RED**

Run `php tests/run.php`.

Expected: FAIL because the shared module is missing and `profile.js` does not import it.

- [ ] **Step 3: Extract the existing processor without changing behavior**

Move decode, center-crop, WebP conversion, and filename normalization into:

```js
export const PROFILE_PHOTO_LIMIT = 6;

export async function preprocessProfilePhoto(file) {
  // Decode with image orientation, center-crop to 9:16,
  // render 720×1280, encode image/webp at 0.84, and return File.
}
```

Convert `profile.js` to an ES module importing this function. Load it with `type="module"` in `web/profile/index.php`. Preserve registration's current 2–6 validation and previews.

- [ ] **Step 4: Run tests and JavaScript syntax checks**

Run:

```bash
node --check web/assets/client-photo-processor.js
node --check web/assets/profile.js
php tests/run.php
```

Expected: all commands exit 0.

- [ ] **Step 5: Commit the shared processor**

```bash
git add web/assets/client-photo-processor.js web/assets/profile.js web/profile/index.php tests/profile_contract_test.php
git commit -m "refactor: share profile photo processing"
```

## Task 4: Render and operate the Profile Modal

**Files:**
- Create: `tests/profile_editor_model_test.mjs`
- Create: `web/assets/profile-editor-model.js`
- Create: `web/assets/profile-editor.js`
- Modify: `web/app/index.php`
- Modify: `web/assets/browse.css`
- Modify: `tests/page_contract_test.php`

- [ ] **Step 1: Write failing slot-model tests**

Create Node tests around a pure model:

```js
test('plus appends the next photo through six', () => {
  let state = createProfilePhotoState(['/1.webp', '/2.webp']);
  state = appendPendingPhoto(state, file('3.webp'));
  assert.equal(state.slots.length, 3);
  assert.equal(state.nextAddSlot, 4);
});

test('replacement preserves the selected position', () => {
  const state = replaceProfilePhoto(
    createProfilePhotoState(['/1.webp', '/2.webp']),
    1,
    file('main.webp')
  );
  assert.equal(state.slots[0].upload.name, 'main.webp');
  assert.equal(state.slots[1].path, '/2.webp');
});

test('a sixth photo removes the plus state and no delete operation exists', () => {
  const state = createProfilePhotoState(['/1','/2','/3','/4','/5','/6']);
  assert.equal(state.nextAddSlot, null);
  assert.equal('removeProfilePhoto' in state, false);
});
```

- [ ] **Step 2: Run Node tests and verify RED**

Run `node --test tests/profile_editor_model_test.mjs`.

Expected: FAIL because `profile-editor-model.js` is missing.

- [ ] **Step 3: Implement the pure slot model and Modal markup**

Implement immutable slot helpers in `profile-editor-model.js`. In `web/app/index.php`, load the member's complete ordered photo set and render:

- desktop and mobile avatar `<button aria-label="Edit profile">` controls;
- `<dialog class="profile-editor-modal">`;
- the current name input;
- ordered existing photo buttons with a `Main` marker on slot one;
- one `+` add button only when fewer than six photos exist;
- no remove controls;
- error/status region, close, and save controls;
- versioned `profile-editor.js` module;
- no `document.modelContext` or new WebMCP registration.

- [ ] **Step 4: Implement Modal behavior and multipart save**

`profile-editor.js` imports the slot model and `preprocessProfilePhoto`. It must open from either avatar button, replace an exact slot, append sequentially from `+`, rebuild previews with revoked object URLs, submit only changed photo files with matching `photo_slots[]`, disable Save during processing/submission, update both header avatars and the displayed name after success, then close the Modal.

- [ ] **Step 5: Run focused and complete tests**

Run:

```bash
node --test tests/profile_editor_model_test.mjs
node --check web/assets/profile-editor-model.js
node --check web/assets/profile-editor.js
php tests/run.php
```

Expected: all tests and syntax checks pass.

- [ ] **Step 6: Commit the Profile Modal**

```bash
git add tests/profile_editor_model_test.mjs web/assets/profile-editor-model.js web/assets/profile-editor.js web/app/index.php web/assets/browse.css tests/page_contract_test.php
git commit -m "feat: add in-app profile editor"
```

## Task 5: Refine Likes Cards and add mobile destination navigation

**Files:**
- Modify: `web/app/index.php`
- Modify: `web/assets/browse.js`
- Modify: `web/assets/browse.css`
- Modify: `tests/page_contract_test.php`

- [ ] **Step 1: Write failing UI contracts**

Assert:

- exactly the approved visible label `Likes`, with no visible `Agent Likes`;
- three mobile destination buttons with `aria-label="Slide"`, `aria-label="Likes"`, and `aria-label="Messages"`;
- local inline SVG markers for Font Awesome Free `fire-flame-curved`, `heart`, and `comment-dots` shapes;
- `data-destination="slide|likes|messages"` state hooks;
- `match-card-close` uses the smaller dimension;
- `match-card-opinion` clamps to three lines;
- desktop hides `.mobile-destination-nav` and mobile fixes it to the bottom.

- [ ] **Step 2: Run PHP tests and verify RED**

Run `php tests/run.php`.

Expected: FAIL on the old `Agent Likes` text tabs and missing mobile destination navigation.

- [ ] **Step 3: Implement destination state in markup and JavaScript**

Replace `agent-likes` state names with `likes`. Add a mobile-only three-button navigation using embedded Font Awesome Free SVG paths, all in normalized 24×24 boxes and `currentColor`.

Refactor `setActiveTab()` into `setDestination(destination)`:

```js
function setDestination(destination) {
  const isSlide = destination === 'slide';
  document.querySelectorAll('[data-destination]').forEach((button) => {
    button.setAttribute('aria-selected', String(button.dataset.destination === destination));
  });
  document.querySelectorAll('[data-panel]').forEach((panel) => {
    panel.hidden = isSlide || panel.dataset.panel !== destination;
  });
  browseContent.hidden = !isSlide;
  if (destination !== 'messages') messageView.hidden = true;
}
```

On mobile, Likes and Messages occupy the main content area; Slide restores the candidate card. Opening a Match selects Messages. The conversation back arrow selects Slide. Preserve desktop sidebar tab behavior.

- [ ] **Step 4: Implement responsive CSS and Card refinements**

At the existing 720px breakpoint:

- reserve the bottom navigation height with safe-area inset;
- hide text tabs;
- display the three equal icon buttons;
- use Ainder pink plus a top indicator for the selected icon;
- size each icon box to 24×24;
- keep top avatar controls and main content clear of the fixed bottom bar;
- prevent horizontal overflow.

On all sizes, reduce the Match close button and set opinion clamp to three lines.

- [ ] **Step 5: Run all automated checks**

Run:

```bash
php tests/run.php
node --test tests/browse_model_test.mjs tests/profile_editor_model_test.mjs
node --check web/assets/browse.js
node --check web/assets/profile-editor.js
git diff --check
```

Expected: all tests pass, syntax checks exit 0, and no whitespace errors appear.

- [ ] **Step 6: Commit the responsive UI**

```bash
git add web/app/index.php web/assets/browse.js web/assets/browse.css tests/page_contract_test.php
git commit -m "feat: add mobile destination navigation"
```

## Task 6: Deploy and verify production

**Files:**
- No new source files.

- [ ] **Step 1: Run the fresh full verification suite**

Run every PHP test, both Node suites, all changed PHP/JavaScript syntax checks, and `git diff --check`. Confirm zero failures before pushing.

- [ ] **Step 2: Push `main`**

```bash
git push origin main
```

Expected: remote `main` advances to the final implementation commit.

- [ ] **Step 3: Deploy the web tree safely**

Sync `web/` to `/Volumes/sweety.tw/ainder/` while excluding `config.local.php`, `.user.ini`, and `uploads/`. No database migration is required.

- [ ] **Step 4: Verify production desktop behavior**

In the signed-in production session, verify:

- desktop says Likes;
- Match Card close is smaller and opinion shows three lines;
- avatar opens Profile Modal with the current name and ordered existing photos;
- replacing one photo updates that slot only;
- adding a photo appends the next slot;
- reloading preserves the updated name and photos;
- Messages, conversation, opinion Modal, and unmatch confirmation still work.

Use disposable test media only if the user explicitly authorizes changing a real profile photo. Name-only persistence may be verified with a no-op save when photo mutation is not authorized.

- [ ] **Step 5: Verify production mobile behavior**

At a phone-sized viewport, verify:

- top text tabs are absent;
- bottom fire, heart, and comment-dots icons have matched optical size;
- Slide, Likes, and Messages switch the full main area;
- a conversation keeps Messages selected and its back arrow returns to Slide;
- Profile Modal is scrollable and does not overflow horizontally;
- console warnings/errors are empty.

- [ ] **Step 6: Record final evidence**

Report the pushed commit, production state, automated test counts, desktop/mobile checks, and any profile fields intentionally left unchanged during verification.

