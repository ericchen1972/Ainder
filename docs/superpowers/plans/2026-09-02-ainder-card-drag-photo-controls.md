# Ainder Card Drag and Photo Controls Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the candidate card own horizontal drag gestures, move photo navigation into compact option-C pill controls inside the image, and make age visually secondary.

**Architecture:** Keep candidate direction and photo wrapping in the pure browse model, add one pure target-classification helper for pointer routing, and let the DOM controller own pointer capture and failed-photo state. PHP renders only multi-photo controls, CSS supplies a 26×54px visual pill inside a 44×54px target, and native image dragging is disabled in markup, CSS, and JavaScript.

**Tech Stack:** PHP 8.2 templates, browser ES modules, semantic HTML, CSS, Node.js built-in test runner, existing PHP contract harness, authenticated in-app browser, mounted production deployment.

---

## File Structure

- Modify `tests/browse_model_test.mjs`: prove pointer targets are classified without a DOM runtime and preserve candidate/photo direction behavior.
- Modify `tests/page_contract_test.php`: lock the PHP markup, CSS, event-boundary, typography, and no-Like contracts.
- Modify `web/assets/browse-model.js`: add the pure `isPhotoControlTarget()` routing helper.
- Modify `web/app/index.php`: remove external candidate buttons, render option-C photo controls only for multi-photo candidates, disable native image dragging, separate name and age spans, and update guidance text.
- Modify `web/assets/browse.css`: style the inside pill targets, disable native media drag/selection, and size age independently.
- Modify `web/assets/browse.js`: prevent native `dragstart`, isolate photo-control pointer sequences, and hide controls when load failures leave fewer than two usable photos.

### Task 1: Lock the approved markup and styling contract

**Files:**
- Modify: `tests/page_contract_test.php`
- Test: `tests/page_contract_test.php`

- [ ] **Step 1: Replace the broad browse contract additions with focused failing assertions**

Add this test after `browse page exposes no visible Like control`:

```php
test('browse card separates candidate drag from inside photo controls', function () use ($root): void {
    $page = file_get_contents($root.'/web/app/index.php');
    $script = file_get_contents($root.'/web/assets/browse.js');
    $style = file_get_contents($root.'/web/assets/browse.css');

    foreach ([
        'draggable="false"',
        "count(\$candidate['photos']) > 1",
        'class="photo-control photo-previous"',
        'class="photo-control photo-next"',
        'candidate-name',
        'candidate-age',
        '拖曳卡片換人 · 卡片內箭頭換照片',
    ] as $needle) {
        expect_same(true, str_contains($page, $needle));
    }

    foreach ([
        'dragstart',
        'isPhotoControlTarget',
        "addEventListener('pointerdown'",
        'updatePhotoControls',
    ] as $needle) {
        expect_same(true, str_contains($script, $needle));
    }

    foreach ([
        '-webkit-user-drag: none',
        'user-select: none',
        '.photo-control::before',
        'width: 26px',
        'height: 54px',
        '.candidate-age',
        'font-size: 18px',
    ] as $needle) {
        expect_same(true, str_contains($style, $needle));
    }

    expect_same(false, str_contains($page, 'candidate-control'));
});
```

- [ ] **Step 2: Run the PHP suite and confirm the new contract fails**

Run:

```bash
lean-ctx -c 'php tests/run.php > /tmp/ainder-card-controls-red.log'
rg -n 'FAIL browse card separates' /tmp/ainder-card-controls-red.log
```

Expected: one `FAIL browse card separates candidate drag from inside photo controls` line because the new markup and CSS do not exist.

- [ ] **Step 3: Commit the red contract test**

```bash
git add tests/page_contract_test.php
git commit -m "test: define Ainder card photo controls"
```

### Task 2: Add a pure pointer-routing rule

**Files:**
- Modify: `tests/browse_model_test.mjs`
- Modify: `web/assets/browse-model.js`
- Test: `tests/browse_model_test.mjs`

- [ ] **Step 1: Write the failing pointer-target unit test**

Add `isPhotoControlTarget` to the imported names and append:

```js
test('photo controls are excluded from candidate drag starts', () => {
  const control = {
    closest: (selector) => selector === '.photo-control' ? control : null,
  };
  const image = { closest: () => null };

  assert.equal(isPhotoControlTarget(control), true);
  assert.equal(isPhotoControlTarget(image), false);
  assert.equal(isPhotoControlTarget(null), false);
});
```

- [ ] **Step 2: Run the Node test and confirm the missing export fails**

Run:

```bash
lean-ctx -c 'node --test tests/browse_model_test.mjs'
```

Expected: FAIL because `isPhotoControlTarget` is not exported.

- [ ] **Step 3: Implement the minimal pure helper**

Append to `web/assets/browse-model.js`:

```js
export function isPhotoControlTarget(target) {
  return Boolean(
    target
      && typeof target.closest === 'function'
      && target.closest('.photo-control'),
  );
}
```

- [ ] **Step 4: Run the model tests**

Run:

```bash
lean-ctx -c 'node --test tests/browse_model_test.mjs'
```

Expected: all four model tests pass, including existing left-next/right-previous and photo wrapping assertions.

- [ ] **Step 5: Commit the routing helper**

```bash
git add tests/browse_model_test.mjs web/assets/browse-model.js
git commit -m "test: isolate Ainder photo control pointers"
```

### Task 3: Render only inside photo controls

**Files:**
- Modify: `web/app/index.php`
- Test: `tests/page_contract_test.php`

- [ ] **Step 1: Remove external candidate buttons**

Delete these two elements from `web/app/index.php`:

```php
<button class="candidate-control candidate-next" type="button" aria-label="下一位候選人">‹</button>
<button class="candidate-control candidate-previous" type="button" aria-label="上一位候選人">›</button>
```

- [ ] **Step 2: Disable native image dragging in rendered markup**

Change every candidate image to include the explicit attribute:

```php
<img
    src="<?= $escape($photo['file_path']) ?>"
    alt="<?= $escape($candidate['display_name']) ?> 的照片 <?= $photoIndex + 1 ?>"
    draggable="false"
>
```

- [ ] **Step 3: Replace invisible photo zones with approved multi-photo pills**

Replace the two unconditional `.photo-zone` buttons with:

```php
<?php if (count($candidate['photos']) > 1): ?>
    <button class="photo-control photo-previous" type="button" aria-label="上一張照片">
        <span aria-hidden="true">‹</span>
    </button>
    <button class="photo-control photo-next" type="button" aria-label="下一張照片">
        <span aria-hidden="true">›</span>
    </button>
<?php endif; ?>
```

- [ ] **Step 4: Separate name and age typography**

Replace the current heading with:

```php
<h1>
    <span class="candidate-name"><?= $escape($candidate['display_name']) ?></span>
    <span class="candidate-age"><?= (int) $candidate['age'] ?></span>
</h1>
```

- [ ] **Step 5: Update the desktop interaction hint**

Use:

```php
<p class="browse-hint">拖曳卡片換人 · 卡片內箭頭換照片</p>
```

- [ ] **Step 6: Run the PHP tests to observe the remaining CSS/JavaScript contract failure**

Run:

```bash
lean-ctx -c 'php tests/run.php > /tmp/ainder-card-markup.log'
rg -n 'FAIL browse card separates' /tmp/ainder-card-markup.log
```

Expected: the focused contract still fails because CSS and JavaScript behavior are not yet implemented; existing PHP page contracts remain passing.

- [ ] **Step 7: Commit the semantic markup**

```bash
git add web/app/index.php
git commit -m "feat: move Ainder photo controls into cards"
```

### Task 4: Style option-C pills and secondary age

**Files:**
- Modify: `web/assets/browse.css`
- Test: `tests/page_contract_test.php`

- [ ] **Step 1: Prevent native selection and image dragging**

Replace the one-line candidate image rule with:

```css
.candidate-photo img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    user-select: none;
    -webkit-user-select: none;
    -webkit-user-drag: none;
}
```

- [ ] **Step 2: Replace `.photo-zone` rules with the option-C control**

Use:

```css
.photo-control {
    position: absolute;
    z-index: 7;
    top: 50%;
    width: 44px;
    height: 54px;
    display: grid;
    place-items: center;
    padding: 0;
    border: 0;
    background: transparent;
    color: #fff;
    cursor: pointer;
    transform: translateY(-50%);
}

.photo-control::before {
    position: absolute;
    width: 26px;
    height: 54px;
    border: 1px solid rgba(255,255,255,.18);
    border-radius: 999px;
    background: rgba(10,11,16,.38);
    box-shadow: 0 2px 12px rgba(0,0,0,.28);
    backdrop-filter: blur(5px);
    content: '';
}

.photo-control span {
    position: relative;
    z-index: 1;
    font-size: 22px;
    line-height: 1;
}

.photo-previous { left: 1px; }
.photo-next { right: 1px; }

.photo-control[hidden] { display: none; }
```

The 44px target centered around the 26px pill places the visible surface 10px from the card edge.

- [ ] **Step 3: Separate name and age scale**

Replace the heading rules with:

```css
.candidate-copy h1 {
    display: flex;
    align-items: baseline;
    gap: 6px;
    margin: 0;
    letter-spacing: -.025em;
}

.candidate-name {
    font-size: clamp(24px, 2.5vw, 34px);
    font-weight: 800;
}

.candidate-age {
    font-size: 18px;
    font-weight: 450;
    letter-spacing: 0;
}
```

Inside the existing `@media (max-width: 720px)` block add:

```css
.candidate-age { font-size: 17px; }
```

- [ ] **Step 4: Update focus and responsive selectors**

Change the focus selector from `.photo-zone:focus-visible` to `.photo-control:focus-visible`. Delete the full `.candidate-control` block, `.candidate-next`, `.candidate-previous`, and the mobile `.candidate-control` hide selector. Keep `.browse-hint` hidden on mobile:

```css
@media (max-width: 720px) {
    .browse-hint { display: none; }
}
```

- [ ] **Step 5: Run PHP tests to confirm only the JavaScript contract remains red**

Run:

```bash
lean-ctx -c 'php tests/run.php > /tmp/ainder-card-style.log'
rg -n 'FAIL browse card separates' /tmp/ainder-card-style.log
```

Expected: the focused test still fails only on missing JavaScript event-boundary strings.

- [ ] **Step 6: Commit the selected visual design**

```bash
git add web/assets/browse.css
git commit -m "style: add compact Ainder photo pills"
```

### Task 5: Make the card own drag gestures

**Files:**
- Modify: `web/assets/browse.js`
- Test: `tests/browse_model_test.mjs`
- Test: `tests/page_contract_test.php`

- [ ] **Step 1: Import the target helper and remove outside-button listeners**

Use this import:

```js
import {
  wrapIndex,
  candidateStepForDrag,
  photoIndexAfterStep,
  isPhotoControlTarget,
} from 'ainder-browse-model';
```

Delete the `.candidate-next` and `.candidate-previous` click listeners. Keep the existing Left Arrow and Right Arrow candidate behavior.

- [ ] **Step 2: Add failed-photo control state**

Add after `movePhoto()`:

```js
function updatePhotoControls(card) {
  const availableCount = card.querySelectorAll(
    '.candidate-photo:not(.has-error)',
  ).length;
  card.querySelectorAll('.photo-control').forEach((control) => {
    control.hidden = availableCount < 2;
  });
}
```

Call `updatePhotoControls(card)` after marking a photo `.has-error` and after selecting the first available fallback.

- [ ] **Step 3: Isolate photo-control pointer sequences**

Replace each photo-control listener block with:

```js
card.querySelector('.photo-previous')?.addEventListener('pointerdown', (event) => {
  event.stopPropagation();
});
card.querySelector('.photo-previous')?.addEventListener('click', (event) => {
  event.stopPropagation();
  movePhoto(card, -1);
});
card.querySelector('.photo-next')?.addEventListener('pointerdown', (event) => {
  event.stopPropagation();
});
card.querySelector('.photo-next')?.addEventListener('click', (event) => {
  event.stopPropagation();
  movePhoto(card, 1);
});
```

- [ ] **Step 4: Prevent residual native image dragging**

Inside the candidate image loop, before the `error` listener, add:

```js
image.addEventListener('dragstart', (event) => {
  event.preventDefault();
});
```

- [ ] **Step 5: Guard the candidate pointer start**

Begin the stack `pointerdown` listener with:

```js
if (isPhotoControlTarget(event.target)) return;
```

Then preserve the existing pointer start, pointer capture, transform, threshold, cancellation, direction, and circular navigation logic unchanged.

- [ ] **Step 6: Run focused and complete tests**

Run:

```bash
lean-ctx -c 'node --test tests/browse_model_test.mjs'
lean-ctx -c 'php tests/run.php > /tmp/ainder-card-green.log'
rg -c '^PASS ' /tmp/ainder-card-green.log
rg -n '^FAIL ' /tmp/ainder-card-green.log || true
node --check web/assets/browse.js
```

Expected: four Node model tests pass, the PHP pass count increases by one with no `FAIL` line, and JavaScript syntax is valid.

- [ ] **Step 7: Commit the root-cause fix**

```bash
git add web/assets/browse.js web/assets/browse-model.js tests/browse_model_test.mjs tests/page_contract_test.php
git commit -m "fix: restore Ainder candidate card dragging"
```

### Task 6: Verify desktop and mobile behavior locally

**Files:**
- No persistent file changes expected.

- [ ] **Step 1: Confirm source and test cleanliness**

Run:

```bash
lean-ctx -c 'git diff --check && git status --short --branch'
```

Expected: `main` contains only the task commits; pre-existing `.DS_Store` and `.superpowers/` remain untracked and untouched.

- [ ] **Step 2: Inspect the rendered desktop card**

Use the authenticated in-app browser at `/ainder/app/` and confirm:

```text
- no large buttons exist outside the card;
- two narrow pills appear inside a multi-photo card;
- the age is 18px and smaller than the name;
- clicking each pill changes only the photo and segment;
- dragging the visible image left changes the candidate;
- dragging right returns to the previous candidate;
- no browser ghost image appears.
```

- [ ] **Step 3: Inspect a mobile viewport**

Use a viewport at or below 720px and confirm:

```text
- the card remains edge-to-edge below the mobile bar;
- pills stay inside the image and retain a 44px target;
- age is 17px;
- vertical touch scrolling remains available;
- horizontal card drag still changes candidates.
```

- [ ] **Step 4: Verify accessibility behavior**

Confirm keyboard Left Arrow selects the next candidate, Right Arrow selects the previous candidate, pill focus outlines remain visible, and reduced-motion emulation disables candidate entrance animation.

### Task 7: Deploy, push, and verify production

**Files:**
- Deploy only `web/` to `/Volumes/sweety.tw/ainder/`.
- Preserve `/Volumes/sweety.tw/ainder/config.local.php` and `/Volumes/sweety.tw/ainder/uploads/`.

- [ ] **Step 1: Run the final evidence loop**

Run:

```bash
lean-ctx -c 'node --test tests/browse_model_test.mjs && php tests/run.php > /tmp/ainder-card-final.log && node --check web/assets/browse.js && git diff --check'
rg -c '^PASS ' /tmp/ainder-card-final.log
rg -n '^FAIL ' /tmp/ainder-card-final.log || true
```

Expected: all Node and PHP tests pass, syntax checks pass, and no whitespace errors exist.

- [ ] **Step 2: Preview production synchronization**

Run:

```bash
lean-ctx -c 'rsync -avn --delete --exclude config.local.php --exclude uploads/ web/ /Volumes/sweety.tw/ainder/'
```

Expected: only Ainder runtime files are updated; `config.local.php` and `uploads/` are not deleted or replaced.

- [ ] **Step 3: Synchronize the approved runtime**

Run:

```bash
lean-ctx -c 'rsync -av --delete --exclude config.local.php --exclude uploads/ web/ /Volumes/sweety.tw/ainder/'
```

Expected: synchronization succeeds with no unrelated target removal.

- [ ] **Step 4: Push main**

Run:

```bash
lean-ctx -c 'git push origin main'
```

Expected: `origin/main` advances to the final implementation commit.

- [ ] **Step 5: Verify deployed asset identity**

Run:

```bash
shasum -a 256 \
  web/assets/browse.js /Volumes/sweety.tw/ainder/assets/browse.js \
  web/assets/browse.css /Volumes/sweety.tw/ainder/assets/browse.css \
  web/app/index.php /Volumes/sweety.tw/ainder/app/index.php
git rev-parse HEAD origin/main
```

Expected: each local/deployed hash pair matches and both Git revisions are identical.

- [ ] **Step 6: Perform authenticated production verification**

Reload `https://sweety.tw/ainder/app/` with the existing signed-in in-app browser session and repeat Task 6 desktop/mobile interaction checks. Confirm WebMCP tools still bind to the current candidate after both drag directions and photo changes.

- [ ] **Step 7: Record final status**

Report the implementation commits, test counts, deployed hash verification, desktop/mobile evidence, and any browser limitation. Do not claim drag success unless the production interaction was actually performed.
