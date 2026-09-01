# Ainder Agent Registration and Profile-Gated Actions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

> **Execution choice for this project:** Use `superpowers:executing-plans` inline in the current session. Do not dispatch subagents.

**Goal:** Add WebMCP Agent registration with signed photo uploads, shared 720 × 1280 WebP processing, automatic initial Agent Profile creation, and Profile-gated candidate evaluation and Like actions while preserving manual registration and browse-only website behavior.

**Architecture:** Keep the existing PHP session and MySQLi application, add a rerunnable migration for short-lived Agent registration state, evaluation tokens, Likes, and Matches, and route manual and signed uploads through one GD image processor. Top-level JavaScript WebMCP tools call narrow JSON endpoints; signed image bytes use an HMAC-protected PUT endpoint, while final registration atomically consumes ready uploads and creates the member, photos, and Agent Profile. Candidate evaluation and Like share one server-side Profile gate that checks both Profiles but enforces the three-month expiry only on the requesting member.

**Tech Stack:** PHP 8.2 strict mode, MariaDB/MySQLi, PHP GD/fileinfo/EXIF, server sessions, HMAC-SHA256 signed URLs, top-level JavaScript WebMCP (`document.modelContext.registerTool`), browser ES modules, existing PHP test harness, Node.js built-in test runner, mounted production deployment.

---

## File Map

- Create `web/migrations/004_add_agent_actions.php`: rerunnable schema for Agent registration sessions/uploads, evaluation tokens, Likes, and Matches.
- Modify `web/config.local.example.php` and `web/lib/config.php`: add the private upload-signing key and public Ainder base URL.
- Create `web/lib/api.php`: consistent JSON responses, request decoding, session/CSRF guards, and structured error envelopes.
- Create `web/lib/image_processor.php`: decode, orient, metadata-strip, proportional cover crop, and WebP output.
- Modify `web/lib/photos.php`, `web/lib/database.php`, and `web/profile/register.php`: make manual registration use processed WebPs and the shared member-creation service.
- Create `web/lib/signed_uploads.php`: registration IDs, upload IDs, signed URL generation/verification, and upload-state validation.
- Create `web/lib/agent_profiles.php`: Profile validation, three-calendar-month expiry, upsert, lookup, and shared gate results.
- Create `web/lib/agent_registration.php`: registration-session persistence, prepared upload persistence, atomic Agent member/Profile creation, and cleanup.
- Create `web/api/agent-registration/start.php`, `prepare-photo.php`, `upload.php`, and `submit.php`: Agent registration HTTP surface.
- Create `web/api/profile/upsert.php`: confirmed Profile creation and refresh for existing manual members.
- Create `web/lib/agent_actions.php`: evaluation-token and Like/Match transaction logic.
- Create `web/api/candidates/evaluate.php` and `web/api/candidates/like.php`: Profile-gated Agent actions bound to the currently displayed candidate.
- Create `web/assets/webmcp-common.js`: JSON request helper, error normalization, feature detection, and current-candidate lookup.
- Create `web/assets/webmcp-registration.js`: registration and Profile-related WebMCP tools on the onboarding page.
- Create `web/assets/webmcp-app.js`: Profile upsert, candidate evaluation, and Like WebMCP tools on the browse page.
- Modify `web/profile/index.php` and `web/app/index.php`: publish CSRF metadata and load the appropriate top-level modules without adding an Agent or Like button.
- Create `web/maintenance/cleanup_agent_uploads.php`: token-protected cleanup of expired sessions and orphaned temporary WebPs.
- Create `tests/image_processor_test.php`, `tests/signed_upload_test.php`, `tests/agent_profile_test.php`, `tests/agent_registration_test.php`, `tests/agent_actions_test.php`, and `tests/webmcp_contract_test.php`: pure behavior and source-contract coverage.
- Modify `tests/run.php`, `tests/photo_test.php`, `tests/profile_contract_test.php`, and `tests/page_contract_test.php`: load and enforce the new contracts.

### Task 1: Add the Agent Workflow Schema and Configuration

**Files:**
- Create: `web/migrations/004_add_agent_actions.php`
- Modify: `web/config.local.example.php`
- Modify: `web/lib/config.php`
- Modify: `tests/page_contract_test.php`

- [ ] **Step 1: Write the failing schema and configuration contracts**

Append these tests to `tests/page_contract_test.php`:

```php
test('fourth migration creates Agent workflow tables', function () use ($root): void {
    $source = file_get_contents($root.'/web/migrations/004_add_agent_actions.php');

    foreach ([
        'agent_registration_sessions',
        'agent_registration_uploads',
        'candidate_evaluations',
        'likes',
        'matches',
        'UNIQUE KEY likes_sender_recipient_unique',
        'UNIQUE KEY matches_pair_unique',
    ] as $needle) {
        expect_same(true, str_contains($source, $needle));
    }
});

test('signed uploads require an untracked signing key and public base URL', function () use ($root): void {
    $config = file_get_contents($root.'/web/lib/config.php');
    $example = file_get_contents($root.'/web/config.local.example.php');

    foreach (['upload_signing_key', 'public_base_url'] as $key) {
        expect_same(true, str_contains($config, $key));
        expect_same(true, str_contains($example, $key));
    }
    expect_same(false, str_contains($config, 'replace-with-64-random-hex-characters'));
});
```

- [ ] **Step 2: Run the contracts and verify they fail**

Run:

```bash
lean-ctx -c --raw php tests/run.php
```

Expected: FAIL because migration 004 and both configuration keys are absent.

- [ ] **Step 3: Create the rerunnable migration**

Create `web/migrations/004_add_agent_actions.php` with the same token validation and `ainder` database selection used by migration 003, then execute these exact table definitions inside the guarded `try` block:

```php
$database->query(
    "CREATE TABLE IF NOT EXISTS agent_registration_sessions (
        id CHAR(32) NOT NULL,
        google_sub VARCHAR(255) NOT NULL,
        idempotency_key CHAR(64) NOT NULL,
        status ENUM('active', 'consumed', 'expired') NOT NULL DEFAULT 'active',
        member_id BIGINT UNSIGNED NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY agent_registration_identity_attempt_unique
            (google_sub, idempotency_key),
        KEY agent_registration_expiry_index (status, expires_at),
        CONSTRAINT agent_registration_member_foreign
            FOREIGN KEY (member_id) REFERENCES users (id)
            ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$database->query(
    "CREATE TABLE IF NOT EXISTS agent_registration_uploads (
        id CHAR(32) NOT NULL,
        registration_id CHAR(32) NOT NULL,
        sort_order TINYINT UNSIGNED NOT NULL,
        declared_mime VARCHAR(64) NOT NULL,
        declared_size INT UNSIGNED NOT NULL,
        processed_path VARCHAR(500) NULL,
        status ENUM('prepared', 'ready', 'consumed', 'failed')
            NOT NULL DEFAULT 'prepared',
        expires_at DATETIME NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY agent_registration_upload_order_unique
            (registration_id, sort_order),
        KEY agent_registration_upload_expiry_index (status, expires_at),
        CONSTRAINT agent_registration_upload_session_foreign
            FOREIGN KEY (registration_id)
            REFERENCES agent_registration_sessions (id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$database->query(
    "CREATE TABLE IF NOT EXISTS candidate_evaluations (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        token_hash CHAR(64) NOT NULL,
        requester_user_id BIGINT UNSIGNED NOT NULL,
        candidate_user_id BIGINT UNSIGNED NOT NULL,
        expires_at DATETIME NOT NULL,
        consumed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY candidate_evaluations_token_unique (token_hash),
        KEY candidate_evaluations_expiry_index (expires_at),
        CONSTRAINT candidate_evaluations_requester_foreign
            FOREIGN KEY (requester_user_id) REFERENCES users (id)
            ON DELETE CASCADE,
        CONSTRAINT candidate_evaluations_candidate_foreign
            FOREIGN KEY (candidate_user_id) REFERENCES users (id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$database->query(
    "CREATE TABLE IF NOT EXISTS likes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        sender_user_id BIGINT UNSIGNED NOT NULL,
        recipient_user_id BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY likes_sender_recipient_unique
            (sender_user_id, recipient_user_id),
        CONSTRAINT likes_sender_foreign FOREIGN KEY (sender_user_id)
            REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT likes_recipient_foreign FOREIGN KEY (recipient_user_id)
            REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

$database->query(
    "CREATE TABLE IF NOT EXISTS matches (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_low_id BIGINT UNSIGNED NOT NULL,
        user_high_id BIGINT UNSIGNED NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY matches_pair_unique (user_low_id, user_high_id),
        CONSTRAINT matches_low_foreign FOREIGN KEY (user_low_id)
            REFERENCES users (id) ON DELETE CASCADE,
        CONSTRAINT matches_high_foreign FOREIGN KEY (user_high_id)
            REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
```

Return a JSON migration result listing all five new tables. Keep the migration rerunnable with `CREATE TABLE IF NOT EXISTS`.

- [ ] **Step 4: Add configuration without committing a secret**

Add these example values to `web/config.local.example.php`:

```php
'upload_signing_key' => 'replace-with-64-random-hex-characters',
'public_base_url' => 'https://sweety.tw/ainder',
```

Add these runtime keys in `ainder_config()`:

```php
'upload_signing_key' => (string) ($local['upload_signing_key'] ?? ''),
'public_base_url' => rtrim(
    (string) ($local['public_base_url'] ?? 'https://sweety.tw/ainder'),
    '/'
),
```

Do not add the real production key to Git. At deployment time generate it with `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'` and add it only to the existing untracked production `config.local.php`.

- [ ] **Step 5: Run tests and commit the schema**

Run:

```bash
lean-ctx -c --raw php tests/run.php
git diff --check
```

Expected: all tests PASS and `git diff --check` prints nothing.

Commit:

```bash
git add web/migrations/004_add_agent_actions.php web/config.local.example.php web/lib/config.php tests/page_contract_test.php
git commit -m "feat: add Ainder Agent workflow schema"
```

### Task 2: Build the Shared 720 × 1280 WebP Processor

**Files:**
- Create: `web/lib/image_processor.php`
- Create: `tests/image_processor_test.php`
- Modify: `tests/run.php`

- [ ] **Step 1: Write failing image behavior tests**

Create `tests/image_processor_test.php`. Generate test sources with GD so no binary fixture is committed:

```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/image_processor.php';

function ainder_test_image(string $path, int $width, int $height, string $type): void
{
    $image = imagecreatetruecolor($width, $height);
    $left = imagecolorallocate($image, 220, 40, 80);
    $right = imagecolorallocate($image, 30, 90, 210);
    imagefilledrectangle($image, 0, 0, intdiv($width, 2), $height, $left);
    imagefilledrectangle($image, intdiv($width, 2), 0, $width, $height, $right);
    match ($type) {
        'jpeg' => imagejpeg($image, $path, 92),
        'png' => imagepng($image, $path),
        'webp' => imagewebp($image, $path, 92),
    };
    imagedestroy($image);
}

test('JPEG PNG and WebP normalize to exact portrait WebP', function (): void {
    foreach (['jpeg', 'png', 'webp'] as $type) {
        $source = tempnam(sys_get_temp_dir(), 'ainder-source-');
        $target = tempnam(sys_get_temp_dir(), 'ainder-target-').'.webp';
        ainder_test_image($source, 1600, 900, $type);

        ainder_process_image($source, $target);
        $info = getimagesize($target);

        expect_same(720, $info[0]);
        expect_same(1280, $info[1]);
        expect_same('image/webp', $info['mime']);
        unlink($source);
        unlink($target);
    }
});

test('orientation six rotates an image before cropping', function (): void {
    $image = imagecreatetruecolor(40, 80);
    $rotated = ainder_apply_exif_orientation($image, 6);
    expect_same(80, imagesx($rotated));
    expect_same(40, imagesy($rotated));
    imagedestroy($rotated);
});

test('invalid image data is rejected without output', function (): void {
    $source = tempnam(sys_get_temp_dir(), 'ainder-invalid-');
    $target = $source.'.webp';
    file_put_contents($source, 'not an image');

    try {
        ainder_process_image($source, $target);
        throw new RuntimeException('Expected invalid image rejection.');
    } catch (InvalidArgumentException) {
        expect_same(false, is_file($target));
    } finally {
        unlink($source);
    }
});
```

Add `require __DIR__.'/image_processor_test.php';` immediately after `photo_test.php` in `tests/run.php`.

- [ ] **Step 2: Run the image tests and verify they fail**

Run:

```bash
lean-ctx -c --raw php tests/run.php
```

Expected: FAIL because `web/lib/image_processor.php` and its functions do not exist.

- [ ] **Step 3: Implement decode, orientation, cover crop, and WebP output**

Create `web/lib/image_processor.php` with these public functions and constants:

```php
<?php

declare(strict_types=1);

const AINDER_OUTPUT_WIDTH = 720;
const AINDER_OUTPUT_HEIGHT = 1280;
const AINDER_OUTPUT_WEBP_QUALITY = 84;

function ainder_apply_exif_orientation(GdImage $image, int $orientation): GdImage
{
    return match ($orientation) {
        3 => imagerotate($image, 180, 0),
        6 => imagerotate($image, -90, 0),
        8 => imagerotate($image, 90, 0),
        default => $image,
    };
}

function ainder_decode_image(string $path): GdImage
{
    $bytes = file_get_contents($path);
    $image = is_string($bytes) ? @imagecreatefromstring($bytes) : false;
    if (!$image instanceof GdImage) {
        throw new InvalidArgumentException('Invalid image data.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $oriented = ainder_apply_exif_orientation($image, $orientation);
        if ($oriented !== $image) {
            imagedestroy($image);
            $image = $oriented;
        }
    }

    return $image;
}

function ainder_process_image(string $sourcePath, string $targetPath): void
{
    $source = ainder_decode_image($sourcePath);
    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    if ($sourceWidth < 1 || $sourceHeight < 1) {
        imagedestroy($source);
        throw new InvalidArgumentException('Invalid image dimensions.');
    }

    $scale = max(
        AINDER_OUTPUT_WIDTH / $sourceWidth,
        AINDER_OUTPUT_HEIGHT / $sourceHeight
    );
    $cropWidth = AINDER_OUTPUT_WIDTH / $scale;
    $cropHeight = AINDER_OUTPUT_HEIGHT / $scale;
    $sourceX = max(0.0, ($sourceWidth - $cropWidth) / 2);
    $sourceY = max(0.0, ($sourceHeight - $cropHeight) / 2);
    $output = imagecreatetruecolor(AINDER_OUTPUT_WIDTH, AINDER_OUTPUT_HEIGHT);

    $copied = imagecopyresampled(
        $output,
        $source,
        0,
        0,
        (int) round($sourceX),
        (int) round($sourceY),
        AINDER_OUTPUT_WIDTH,
        AINDER_OUTPUT_HEIGHT,
        (int) round($cropWidth),
        (int) round($cropHeight)
    );
    $written = $copied && imagewebp($output, $targetPath, AINDER_OUTPUT_WEBP_QUALITY);
    imagedestroy($output);
    imagedestroy($source);

    if (!$written) {
        if (is_file($targetPath)) {
            unlink($targetPath);
        }
        throw new RuntimeException('Unable to write processed image.');
    }
}
```

The output is a fresh GD canvas and therefore does not carry source EXIF metadata.

- [ ] **Step 4: Run the focused and complete test suites**

Run:

```bash
lean-ctx -c --raw php tests/run.php
```

Expected: all image processor tests PASS and no previous suite regresses.

- [ ] **Step 5: Commit the image processor**

```bash
git add web/lib/image_processor.php tests/image_processor_test.php tests/run.php
git commit -m "feat: normalize Ainder photos to portrait WebP"
```

### Task 3: Route Manual Uploads Through the Shared Processor

**Files:**
- Modify: `web/lib/photos.php`
- Modify: `web/lib/database.php`
- Modify: `web/profile/register.php`
- Modify: `tests/photo_test.php`
- Modify: `tests/profile_contract_test.php`

- [ ] **Step 1: Replace raw-extension expectations with processed-WebP contracts**

Change the successful staging test in `tests/photo_test.php` so it requires `image_processor.php`, stages a generated PNG, and asserts:

```php
expect_same('webp', pathinfo($staged[0], PATHINFO_EXTENSION));
$info = getimagesize($staged[0]);
expect_same(720, $info[0]);
expect_same(1280, $info[1]);
expect_same('image/webp', $info['mime']);
```

Append this source contract to `tests/profile_contract_test.php`:

```php
test('manual and Agent uploads share the image processor', function () use ($profileRoot): void {
    $photos = file_get_contents($profileRoot.'/web/lib/photos.php');
    $register = file_get_contents($profileRoot.'/web/profile/register.php');

    expect_same(true, str_contains($photos, 'ainder_process_image'));
    expect_same(true, str_contains($register, "require_once dirname(__DIR__).'/lib/image_processor.php'"));
    expect_same(false, str_contains($photos, "'.'.\$extension"));
});
```

- [ ] **Step 2: Run tests and verify the raw-file pipeline fails the new contract**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: FAIL because staged files retain their input extension and dimensions.

- [ ] **Step 3: Refactor staging and finalization**

Require `image_processor.php` from `web/lib/photos.php`. In `ainder_stage_photos()`, move each upload to a random `.source` temporary path, process it into a different random `.webp`, and remove the source in `finally`:

```php
$sourcePath = rtrim($directory, '/').'/'.bin2hex(random_bytes(16)).'.source';
$processedPath = rtrim($directory, '/').'/'.bin2hex(random_bytes(16)).'.webp';
if (!$mover($photo['tmp_name'], $sourcePath)) {
    throw new RuntimeException('Unable to stage uploaded photo.');
}
try {
    ainder_process_image($sourcePath, $processedPath);
    $staged[] = $processedPath;
} finally {
    if (is_file($sourcePath)) {
        unlink($sourcePath);
    }
}
```

Change `ainder_finalize_photos()` to reject a non-WebP staged path and always create a random `.webp` final name:

```php
if (strtolower((string) pathinfo($stagedPath, PATHINFO_EXTENSION)) !== 'webp') {
    throw new RuntimeException('Only processed WebP files can be finalized.');
}
$finalPath = $directory.'/'.bin2hex(random_bytes(16)).'.webp';
```

Require `image_processor.php` explicitly in `web/profile/register.php` before `photos.php`. Keep all existing 2–6 count, 10 MiB, detected MIME, transaction, and cleanup behavior.

- [ ] **Step 4: Run tests and inspect the manual transaction**

Run:

```bash
lean-ctx -c --raw php tests/run.php
lean-ctx -c 'git diff --check'
```

Expected: all tests PASS. Inspect the diff and confirm `ainder_create_member_with_photos()` still receives only final public WebP paths and rollback still removes staged/final files.

- [ ] **Step 5: Commit the manual image integration**

```bash
git add web/lib/photos.php web/lib/database.php web/profile/register.php tests/photo_test.php tests/profile_contract_test.php
git commit -m "feat: process manual Ainder uploads as WebP"
```

### Task 4: Add JSON and Signed-Upload Foundations

**Files:**
- Create: `web/lib/api.php`
- Create: `web/lib/signed_uploads.php`
- Create: `tests/signed_upload_test.php`
- Modify: `tests/run.php`

- [ ] **Step 1: Write failing signing, expiry, and structured-error tests**

Create `tests/signed_upload_test.php`:

```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/signed_uploads.php';

test('signed upload token binds upload id and expiry', function (): void {
    $signature = ainder_sign_upload('upload-1', 1788300000, 'secret');
    expect_same(true, ainder_verify_upload_signature(
        'upload-1',
        1788300000,
        $signature,
        'secret',
        1788299900
    ));
    expect_same(false, ainder_verify_upload_signature(
        'upload-2',
        1788300000,
        $signature,
        'secret',
        1788299900
    ));
});

test('expired signed upload is rejected', function (): void {
    $signature = ainder_sign_upload('upload-1', 100, 'secret');
    expect_same(false, ainder_verify_upload_signature(
        'upload-1', 100, $signature, 'secret', 101
    ));
});

test('Agent registration identifiers are opaque lowercase hex', function (): void {
    expect_same(1, preg_match('/^[a-f0-9]{32}$/', ainder_agent_identifier()));
});
```

Add `require __DIR__.'/signed_upload_test.php';` to `tests/run.php`.

- [ ] **Step 2: Run tests and verify the helpers are missing**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: FAIL because `signed_uploads.php` does not exist.

- [ ] **Step 3: Implement the API response helpers**

Create `web/lib/api.php`:

```php
<?php

declare(strict_types=1);

function ainder_json_body(): array
{
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('INVALID_JSON');
    }
    return $decoded;
}

function ainder_json_success(array $data = [], int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_SLASHES);
    exit;
}

function ainder_json_error(string $code, string $message, int $status): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => ['code' => $code, 'message' => $message],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function ainder_require_post_json(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        ainder_json_error('METHOD_NOT_ALLOWED', 'POST required.', 405);
    }
}
```

Endpoint tasks below must call existing session helpers, verify pending/member identity, and verify `X-Ainder-CSRF` with `ainder_form_csrf_is_valid()` before a JSON write.

- [ ] **Step 4: Implement pure signed-upload helpers**

Create `web/lib/signed_uploads.php`:

```php
<?php

declare(strict_types=1);

function ainder_agent_identifier(): string
{
    return bin2hex(random_bytes(16));
}

function ainder_sign_upload(string $uploadId, int $expires, string $key): string
{
    if ($key === '') {
        throw new RuntimeException('Upload signing is not configured.');
    }
    return hash_hmac('sha256', $uploadId.'.'.$expires, $key);
}

function ainder_verify_upload_signature(
    string $uploadId,
    int $expires,
    string $signature,
    string $key,
    int $now
): bool {
    return $uploadId !== ''
        && $signature !== ''
        && $expires >= $now
        && $key !== ''
        && hash_equals(ainder_sign_upload($uploadId, $expires, $key), $signature);
}

function ainder_signed_upload_url(
    array $config,
    string $uploadId,
    int $expires
): string {
    $signature = ainder_sign_upload(
        $uploadId,
        $expires,
        (string) $config['upload_signing_key']
    );
    return (string) $config['public_base_url']
        .'/api/agent-registration/upload.php?id='.rawurlencode($uploadId)
        .'&expires='.$expires
        .'&signature='.rawurlencode($signature);
}
```

- [ ] **Step 5: Run tests and commit the foundations**

Run `lean-ctx -c --raw php tests/run.php`; expect all tests PASS.

Commit:

```bash
git add web/lib/api.php web/lib/signed_uploads.php tests/signed_upload_test.php tests/run.php
git commit -m "feat: add Ainder signed upload foundation"
```

### Task 5: Implement Agent Profile and Registration Services

**Files:**
- Create: `web/lib/agent_profiles.php`
- Create: `web/lib/agent_registration.php`
- Create: `tests/agent_profile_test.php`
- Create: `tests/agent_registration_test.php`
- Modify: `tests/run.php`

- [ ] **Step 1: Write failing pure Profile and registration validation tests**

Create `tests/agent_profile_test.php`:

```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/agent_profiles.php';

test('Profile expiry is three calendar months', function (): void {
    $generated = new DateTimeImmutable('2026-09-02 12:00:00');
    expect_same(
        '2026-12-02 12:00:00',
        ainder_profile_expiry($generated)->format('Y-m-d H:i:s')
    );
});

test('Profile gate checks self freshness and target existence only', function (): void {
    $now = new DateTimeImmutable('2026-09-02 12:00:00');
    expect_same('SELF_PROFILE_MISSING', ainder_profile_gate(null, ['expires_at' => '2020-01-01 00:00:00'], $now));
    expect_same('SELF_PROFILE_EXPIRED', ainder_profile_gate(['expires_at' => '2026-09-02 11:59:59'], ['expires_at' => '2030-01-01 00:00:00'], $now));
    expect_same('TARGET_PROFILE_MISSING', ainder_profile_gate(['expires_at' => '2026-12-02 12:00:00'], null, $now));
    expect_same(null, ainder_profile_gate(['expires_at' => '2026-12-02 12:00:00'], ['expires_at' => '2020-01-01 00:00:00'], $now));
});
```

Create `tests/agent_registration_test.php` with pure validation:

```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/agent_registration.php';

test('Agent registration requires ordered two through six ready uploads', function (): void {
    $ready = [
        [
            'id' => 'a',
            'sort_order' => 1,
            'status' => 'ready',
            'processed_path' => '/tmp/a.webp',
        ],
        [
            'id' => 'b',
            'sort_order' => 2,
            'status' => 'ready',
            'processed_path' => '/tmp/b.webp',
        ],
    ];
    expect_same([], ainder_validate_ready_uploads($ready));
    expect_same('PHOTO_COUNT_INVALID', ainder_validate_ready_uploads([$ready[0]])[0]);
    $ready[1]['sort_order'] = 1;
    expect_same('PHOTO_ORDER_INVALID', ainder_validate_ready_uploads($ready)[0]);
});

test('Agent Profile payload is bounded and typed', function (): void {
    expect_same([], ainder_validate_agent_profile([
        'profile_text' => 'A thoughtful and independent person.',
        'agent_known_duration_days' => 180,
        'interaction_density' => 'high',
    ]));
    expect_same(true, count(ainder_validate_agent_profile([
        'profile_text' => '',
        'agent_known_duration_days' => -1,
        'interaction_density' => 'constant',
    ])) > 0);
});
```

Load both files from `tests/run.php`.

- [ ] **Step 2: Run tests and verify the services are missing**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: FAIL because both new service files are absent.

- [ ] **Step 3: Implement Profile validation and the shared gate**

Create `web/lib/agent_profiles.php` with:

```php
<?php

declare(strict_types=1);

function ainder_profile_expiry(DateTimeImmutable $generatedAt): DateTimeImmutable
{
    return $generatedAt->modify('+3 months');
}

function ainder_validate_agent_profile(array $profile): array
{
    $errors = [];
    $text = trim((string) ($profile['profile_text'] ?? ''));
    $duration = filter_var(
        $profile['agent_known_duration_days'] ?? null,
        FILTER_VALIDATE_INT
    );
    if ($text === '' || mb_strlen($text) > 4000) {
        $errors[] = 'PROFILE_TEXT_INVALID';
    }
    if ($duration === false || $duration < 0 || $duration > 65535) {
        $errors[] = 'PROFILE_DURATION_INVALID';
    }
    if (!in_array($profile['interaction_density'] ?? '', ['low', 'medium', 'high'], true)) {
        $errors[] = 'PROFILE_DENSITY_INVALID';
    }
    return $errors;
}

function ainder_profile_gate(
    ?array $selfProfile,
    ?array $targetProfile,
    DateTimeImmutable $now
): ?string {
    if ($selfProfile === null) {
        return 'SELF_PROFILE_MISSING';
    }
    if (new DateTimeImmutable((string) $selfProfile['expires_at']) <= $now) {
        return 'SELF_PROFILE_EXPIRED';
    }
    return $targetProfile === null ? 'TARGET_PROFILE_MISSING' : null;
}
```

Add database functions in the same file:

- `ainder_find_agent_profile(mysqli $database, int $userId): ?array` selecting only Profile fields;
- `ainder_upsert_agent_profile(mysqli $database, int $userId, array $profile, DateTimeImmutable $generatedAt): array` using `INSERT ... ON DUPLICATE KEY UPDATE` and the computed expiry;
- bind `profile_text`, integer duration, enum density, generated timestamp, and expiry timestamp; never accept `user_id` from the Agent payload.

- [ ] **Step 4: Implement registration-session and upload validation services**

Create `web/lib/agent_registration.php` and require `agent_profiles.php`, `registration.php`, and `signed_uploads.php`. Add these pure validators exactly:

```php
function ainder_validate_ready_uploads(array $uploads): array
{
    $count = count($uploads);
    if ($count < 2 || $count > 6) {
        return ['PHOTO_COUNT_INVALID'];
    }
    $orders = array_map(static fn (array $upload): int => (int) ($upload['sort_order'] ?? 0), $uploads);
    sort($orders);
    if ($orders !== range(1, $count)) {
        return ['PHOTO_ORDER_INVALID'];
    }
    foreach ($uploads as $upload) {
        if (($upload['status'] ?? '') !== 'ready' || trim((string) ($upload['processed_path'] ?? '')) === '') {
            return ['PHOTO_NOT_READY'];
        }
    }
    return [];
}
```

Add focused database functions:

- `ainder_start_agent_registration(mysqli $database, string $googleSub, string $idempotencyKey, DateTimeImmutable $now): array` returning an existing same-attempt session or inserting a 30-minute active session;
- `ainder_prepare_agent_upload(mysqli $database, string $registrationId, string $googleSub, int $order, string $mime, int $size, DateTimeImmutable $now): array` enforcing order 1–6, allowed declared MIME, 1–10 MiB, active ownership, and a 15-minute expiry;
- `ainder_mark_agent_upload_ready(mysqli $database, string $uploadId, string $processedPath): void` transitioning only `prepared` to `ready`;
- `ainder_find_ready_agent_uploads(mysqli $database, string $registrationId): array` ordered by `sort_order`;
- `ainder_complete_agent_registration(...)` that validates registration fields and Profile, begins a transaction, inserts the user, moves ready WebPs into `/uploads/profiles/{memberId}`, inserts `user_photos`, inserts `agent_profiles`, marks uploads/session consumed, commits, and rolls back plus removes moved files on error.

Reuse or extract the current user/photo insert logic from `ainder_create_member_with_photos()` so manual and Agent paths share the same user and ordered-photo persistence. Do not create a second independent SQL definition for member fields.

- [ ] **Step 5: Run tests and commit the services**

Run `lean-ctx -c --raw php tests/run.php`; expect all tests PASS.

Commit:

```bash
git add web/lib/agent_profiles.php web/lib/agent_registration.php tests/agent_profile_test.php tests/agent_registration_test.php tests/run.php
git commit -m "feat: add Ainder Agent registration services"
```

### Task 6: Expose Agent Registration and Signed PUT Endpoints

**Files:**
- Create: `web/api/agent-registration/start.php`
- Create: `web/api/agent-registration/prepare-photo.php`
- Create: `web/api/agent-registration/upload.php`
- Create: `web/api/agent-registration/submit.php`
- Modify: `tests/agent_registration_test.php`
- Modify: `tests/profile_contract_test.php`

- [ ] **Step 1: Add failing endpoint source contracts**

Append to `tests/agent_registration_test.php`:

```php
test('Agent endpoints separate WebMCP JSON from signed image bytes', function (): void {
    $root = dirname(__DIR__);
    $start = file_get_contents($root.'/web/api/agent-registration/start.php');
    $prepare = file_get_contents($root.'/web/api/agent-registration/prepare-photo.php');
    $upload = file_get_contents($root.'/web/api/agent-registration/upload.php');
    $submit = file_get_contents($root.'/web/api/agent-registration/submit.php');

    expect_same(true, str_contains($start, 'ainder_pending_identity_is_valid'));
    expect_same(true, str_contains($prepare, 'ainder_signed_upload_url'));
    expect_same(true, str_contains($upload, "file_get_contents('php://input')"));
    expect_same(true, str_contains($upload, 'ainder_verify_upload_signature'));
    expect_same(true, str_contains($upload, 'ainder_process_image'));
    expect_same(true, str_contains($submit, 'ainder_complete_agent_registration'));
    expect_same(false, str_contains($submit, '$_FILES'));
});
```

- [ ] **Step 2: Run tests and verify all four endpoints are absent**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: FAIL on missing endpoint files.

- [ ] **Step 3: Implement start and prepare endpoints**

Both endpoints must require `POST`, start the Ainder session, validate the pending identity, validate `X-Ainder-CSRF`, decode JSON, and return only structured JSON.

`start.php` accepts:

```json
{"idempotency_key":"64-lowercase-hex-characters"}
```

It calls `ainder_start_agent_registration()` with the server-side pending `google_sub` and returns:

```json
{"ok":true,"registration_id":"...","expires_at":"2026-09-02T12:30:00+08:00"}
```

`prepare-photo.php` accepts:

```json
{
  "registration_id":"...",
  "filename":"portrait.jpg",
  "mime_type":"image/jpeg",
  "byte_size":2456789,
  "sort_order":1
}
```

It ignores the filename for storage, calls `ainder_prepare_agent_upload()`, signs the returned upload ID, and returns `upload_id`, absolute `upload_url`, `method: "PUT"`, required `Content-Type`, and expiry.

- [ ] **Step 4: Implement signed PUT and final submit endpoints**

`upload.php` must:

1. require `PUT`;
2. read `id`, `expires`, and `signature` from the query string;
3. validate HMAC and expiry before reading the body;
4. load the prepared upload record and reject non-`prepared` status;
5. stream `php://input` to a random `.source` file under `web/uploads/.agent/{registration_id}` while enforcing the declared and 10 MiB limits;
6. verify detected JPEG/PNG/WebP MIME and declared MIME agreement;
7. call `ainder_process_image()` into a random `.webp` in the same temporary registration directory;
8. delete the source in `finally`;
9. mark the upload `ready` only after successful conversion;
10. return `201` JSON with `upload_id` and `status: "ready"`.

`submit.php` accepts the confirmed text and ordered upload IDs:

```json
{
  "registration_id":"...",
  "idempotency_key":"...",
  "display_name":"Eric Chen",
  "birth_date":"1972-06-18",
  "gender":"male",
  "upload_ids":["main-upload-id","support-upload-id"],
  "profile_text":"Agent-authored description approved by the user.",
  "agent_known_duration_days":365,
  "interaction_density":"high"
}
```

It uses the pending server identity, calls `ainder_complete_agent_registration()`, replaces the pending identity with `ainder_member_id`, regenerates the session ID, and returns:

```json
{"ok":true,"member_id":123,"redirect_url":"/ainder/app/"}
```

Map validation failures to stable error codes and 422, expired identity/session to 401/409, ownership failures to 403, duplicates to the idempotent existing result, and unexpected failures to `REGISTRATION_FAILED` without raw details.

- [ ] **Step 5: Run tests and commit the HTTP surface**

Run:

```bash
lean-ctx -c --raw php tests/run.php
find web/api/agent-registration -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: tests PASS and each file reports `No syntax errors detected`.

Commit:

```bash
git add web/api/agent-registration tests/agent_registration_test.php tests/profile_contract_test.php
git commit -m "feat: expose Ainder Agent registration APIs"
```

### Task 7: Register Agent Onboarding WebMCP Tools

**Files:**
- Create: `web/assets/webmcp-common.js`
- Create: `web/assets/webmcp-registration.js`
- Create: `tests/webmcp_contract_test.php`
- Modify: `web/profile/index.php`
- Modify: `tests/run.php`
- Modify: `tests/profile_contract_test.php`

- [ ] **Step 1: Write failing top-level WebMCP contracts**

Create `tests/webmcp_contract_test.php`:

```php
<?php

declare(strict_types=1);

$webmcpRoot = dirname(__DIR__);

test('registration page loads top-level JavaScript WebMCP tools', function () use ($webmcpRoot): void {
    $page = file_get_contents($webmcpRoot.'/web/profile/index.php');
    $tools = file_get_contents($webmcpRoot.'/web/assets/webmcp-registration.js');

    expect_same(true, str_contains($page, 'webmcp-registration.js'));
    expect_same(true, str_contains($page, 'ainder-csrf-token'));
    expect_same(true, str_contains($tools, 'document.modelContext?.registerTool'));
    foreach (['start_agent_registration', 'prepare_photo_upload', 'submit_agent_registration'] as $name) {
        expect_same(true, str_contains($tools, $name));
    }
    expect_same(false, str_contains($tools, 'openai/fileParams'));
    expect_same(false, str_contains($tools, 'registration-form'));
});
```

Load it from `tests/run.php`.

- [ ] **Step 2: Run tests and verify the WebMCP modules are absent**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: FAIL because the modules do not exist and the page does not load them.

- [ ] **Step 3: Implement the common browser helper**

Create `web/assets/webmcp-common.js`:

```js
export function csrfToken() {
  return document.querySelector('meta[name="ainder-csrf-token"]')?.content ?? '';
}

export async function postJson(path, payload) {
  const response = await fetch(path, {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-Ainder-CSRF': csrfToken(),
    },
    body: JSON.stringify(payload),
  });
  const body = await response.json();
  if (!response.ok || body.ok !== true) {
    return {
      ok: false,
      error: body.error ?? { code: 'REQUEST_FAILED', message: 'Ainder request failed.' },
    };
  }
  return body;
}

export function currentCandidateId() {
  const value = Number(document.documentElement.dataset.currentCandidateId ?? 0);
  return Number.isInteger(value) && value > 0 ? value : null;
}
```

- [ ] **Step 4: Register the onboarding tools**

Create `web/assets/webmcp-registration.js` as a top-level ES module. Guard registration exactly once:

```js
import { postJson } from './webmcp-common.js';

const context = document.modelContext;
if (typeof context?.registerTool === 'function') {
  await context.registerTool({
    name: 'start_agent_registration',
    description: 'Start Ainder registration only after the user has confirmed all personal data, Agent Profile text, and photo order.',
    inputSchema: {
      type: 'object',
      properties: {
        idempotency_key: { type: 'string', pattern: '^[a-f0-9]{64}$' },
      },
      required: ['idempotency_key'],
      additionalProperties: false,
    },
    execute: (input) => postJson('/ainder/api/agent-registration/start.php', input),
  });

  await context.registerTool({
    name: 'prepare_photo_upload',
    description: 'Create one signed upload URL for a confirmed Ainder registration photo. Before calling, the user must designate sort order 1 as the main photo; confirm that it is one real human with a visible face, and reject clearly sexual, violent, illegal, or otherwise unsuitable supplied images.',
    inputSchema: {
      type: 'object',
      properties: {
        registration_id: { type: 'string', pattern: '^[a-f0-9]{32}$' },
        filename: { type: 'string', minLength: 1, maxLength: 255 },
        mime_type: { enum: ['image/jpeg', 'image/png', 'image/webp'] },
        byte_size: { type: 'integer', minimum: 1, maximum: 10485760 },
        sort_order: { type: 'integer', minimum: 1, maximum: 6 },
      },
      required: ['registration_id', 'filename', 'mime_type', 'byte_size', 'sort_order'],
      additionalProperties: false,
    },
    execute: (input) => postJson('/ainder/api/agent-registration/prepare-photo.php', input),
  });

  await context.registerTool({
    name: 'submit_agent_registration',
    description: 'Create the Ainder member, ordered photos, and Agent Profile after every signed upload is ready and the user has confirmed the complete draft.',
    inputSchema: {
      type: 'object',
      properties: {
        registration_id: { type: 'string', pattern: '^[a-f0-9]{32}$' },
        idempotency_key: { type: 'string', pattern: '^[a-f0-9]{64}$' },
        display_name: { type: 'string', minLength: 1, maxLength: 120 },
        birth_date: { type: 'string', format: 'date' },
        gender: { enum: ['male', 'female'] },
        upload_ids: { type: 'array', minItems: 2, maxItems: 6, uniqueItems: true, items: { type: 'string', pattern: '^[a-f0-9]{32}$' } },
        profile_text: { type: 'string', minLength: 1, maxLength: 4000 },
        agent_known_duration_days: { type: 'integer', minimum: 0, maximum: 65535 },
        interaction_density: { enum: ['low', 'medium', 'high'] },
      },
      required: ['registration_id', 'idempotency_key', 'display_name', 'birth_date', 'gender', 'upload_ids', 'profile_text', 'agent_known_duration_days', 'interaction_density'],
      additionalProperties: false,
    },
    execute: async (input) => {
      const result = await postJson('/ainder/api/agent-registration/submit.php', input);
      if (result.ok && result.redirect_url) window.location.assign(result.redirect_url);
      return result;
    },
  });
}
```

In `web/profile/index.php`, add a CSRF meta tag and versioned module load:

```php
<meta name="ainder-csrf-token" content="<?= htmlspecialchars(ainder_form_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
<script type="module" src="/ainder/assets/webmcp-registration.js?v=<?= rawurlencode((string) filemtime($assetRoot.'/webmcp-registration.js')) ?>"></script>
```

Do not add an Agent submit button and do not query or mutate `.registration-form` from the WebMCP module.

- [ ] **Step 5: Run tests and commit registration WebMCP**

Run:

```bash
lean-ctx -c --raw php tests/run.php
node --check web/assets/webmcp-common.js
node --check web/assets/webmcp-registration.js
```

Expected: all checks PASS.

Commit:

```bash
git add web/assets/webmcp-common.js web/assets/webmcp-registration.js web/profile/index.php tests/webmcp_contract_test.php tests/run.php tests/profile_contract_test.php
git commit -m "feat: register Ainder onboarding WebMCP tools"
```

### Task 8: Add Confirmed Profile Creation and Refresh for Existing Members

**Files:**
- Create: `web/api/profile/upsert.php`
- Modify: `web/assets/webmcp-app.js`
- Modify: `tests/agent_profile_test.php`
- Modify: `tests/webmcp_contract_test.php`

- [ ] **Step 1: Write failing Profile endpoint and tool contracts**

Append:

```php
test('existing members can confirm Profile create or refresh through WebMCP', function (): void {
    $root = dirname(__DIR__);
    $endpoint = file_get_contents($root.'/web/api/profile/upsert.php');
    $tools = file_get_contents($root.'/web/assets/webmcp-app.js');

    expect_same(true, str_contains($endpoint, 'ainder_member_id'));
    expect_same(true, str_contains($endpoint, 'ainder_upsert_agent_profile'));
    expect_same(true, str_contains($tools, 'upsert_my_agent_profile'));
});
```

Place the endpoint assertion in `tests/agent_profile_test.php` and the tool assertion in `tests/webmcp_contract_test.php`.

- [ ] **Step 2: Run tests and verify the endpoint and app module are missing**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: FAIL on both absent files.

- [ ] **Step 3: Implement the confirmed Profile endpoint**

Create `web/api/profile/upsert.php`. Require POST JSON, authenticated `ainder_member_id`, valid `X-Ainder-CSRF`, and this payload:

```json
{
  "profile_text":"User-approved Agent description.",
  "agent_known_duration_days":270,
  "interaction_density":"high"
}
```

Validate with `ainder_validate_agent_profile()`, call `ainder_upsert_agent_profile()` using the authenticated member ID and server time, then return only:

```json
{
  "ok":true,
  "profile":{"generated_at":"...","expires_at":"..."}
}
```

Use `PROFILE_INVALID` for 422, `AUTH_REQUIRED` for 401, and `PROFILE_UPDATE_FAILED` for unexpected failures. Never return or accept another `user_id`.

- [ ] **Step 4: Add the Profile WebMCP tool to the app module**

Create `web/assets/webmcp-app.js` initially with `upsert_my_agent_profile`, using the same Profile input schema as registration and `postJson('/ainder/api/profile/upsert.php', input)`. Its description must state that the Agent must show the proposed Profile text and obtain user confirmation before calling it.

Load the versioned module and CSRF meta tag from `web/app/index.php`. Keep all current browse behavior and do not add a visible Profile or Like button.

- [ ] **Step 5: Run checks and commit Profile refresh**

Run:

```bash
lean-ctx -c --raw php tests/run.php
node --check web/assets/webmcp-app.js
```

Expected: PASS.

Commit:

```bash
git add web/api/profile/upsert.php web/assets/webmcp-app.js web/app/index.php tests/agent_profile_test.php tests/webmcp_contract_test.php
git commit -m "feat: add confirmed Ainder Profile refresh"
```

### Task 9: Implement Profile-Gated Evaluation, Like, and Match

**Files:**
- Create: `web/lib/agent_actions.php`
- Create: `web/api/candidates/evaluate.php`
- Create: `web/api/candidates/like.php`
- Create: `tests/agent_actions_test.php`
- Modify: `tests/run.php`

- [ ] **Step 1: Write failing action and error-code tests**

Create `tests/agent_actions_test.php`:

```php
<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/agent_actions.php';

test('evaluation tokens are opaque and stored as hashes', function (): void {
    $token = ainder_evaluation_token();
    expect_same(64, strlen($token));
    expect_same(1, preg_match('/^[a-f0-9]{64}$/', $token));
    expect_same(64, strlen(ainder_evaluation_token_hash($token)));
});

test('match pairs have stable low and high order', function (): void {
    expect_same([4, 19], ainder_match_pair(19, 4));
    expect_same([4, 19], ainder_match_pair(4, 19));
});

test('evaluation and Like endpoints expose Profile errors', function (): void {
    $root = dirname(__DIR__);
    foreach (['evaluate.php', 'like.php'] as $file) {
        $source = file_get_contents($root.'/web/api/candidates/'.$file);
        foreach (['SELF_PROFILE_MISSING', 'SELF_PROFILE_EXPIRED', 'TARGET_PROFILE_MISSING'] as $code) {
            expect_same(true, str_contains($source, $code));
        }
    }
});
```

Load it from `tests/run.php`.

- [ ] **Step 2: Run tests and verify action files are absent**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: FAIL on the absent service and endpoints.

- [ ] **Step 3: Implement evaluation-token and Like transaction services**

Create `web/lib/agent_actions.php` with:

```php
<?php

declare(strict_types=1);

function ainder_evaluation_token(): string
{
    return bin2hex(random_bytes(32));
}

function ainder_evaluation_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function ainder_match_pair(int $firstUserId, int $secondUserId): array
{
    return [min($firstUserId, $secondUserId), max($firstUserId, $secondUserId)];
}
```

Add database functions:

- `ainder_create_candidate_evaluation(mysqli $database, int $requesterId, int $candidateId, DateTimeImmutable $now): array`: load both Profiles, call `ainder_profile_gate()`, ensure candidate is active and opposite gender, create a random token, store only its SHA-256 hash with 10-minute expiry, and return candidate display name plus candidate `profile_text`, `agent_known_duration_days`, `interaction_density`, token, and expiry;
- `ainder_send_agent_like(mysqli $database, int $requesterId, int $candidateId, string $token, DateTimeImmutable $now): array`: begin a transaction, rerun the same Profile gate, lock and validate the unconsumed matching token, insert the Like idempotently, consume the token, and create a stable ordered Match only when a reciprocal Like exists and neither user is Demo;
- reject self-Like, inactive candidate, wrong candidate token, expired token, replay, and non-opposite-gender candidate;
- return `['liked' => true, 'matched' => bool]` after commit.

The Profile gate must be the sole place that decides missing/expired status. Do not duplicate expiry comparisons in the action service.

- [ ] **Step 4: Implement candidate evaluation and Like endpoints**

Both endpoints require authenticated session, POST JSON, valid CSRF, and a positive `candidate_id`. They must map shared gate codes without side effects:

```php
$profileStatuses = [
    'SELF_PROFILE_MISSING',
    'SELF_PROFILE_EXPIRED',
    'TARGET_PROFILE_MISSING',
];
```

`evaluate.php` accepts `{"candidate_id": 20}` and returns:

```json
{
  "ok":true,
  "candidate":{
    "id":20,
    "display_name":"Eva",
    "profile_text":"...",
    "agent_known_duration_days":180,
    "interaction_density":"high"
  },
  "evaluation_token":"...",
  "expires_at":"..."
}
```

`like.php` accepts `{"candidate_id":20,"evaluation_token":"..."}` and returns `liked` and `matched`. Demo candidates persist the Like and always return `matched: false`.

- [ ] **Step 5: Run tests and commit Agent actions**

Run:

```bash
lean-ctx -c --raw php tests/run.php
find web/api/candidates -name '*.php' -print0 | xargs -0 -n1 php -l
```

Expected: PASS.

Commit:

```bash
git add web/lib/agent_actions.php web/api/candidates tests/agent_actions_test.php tests/run.php
git commit -m "feat: add Profile-gated Ainder Agent actions"
```

### Task 10: Register Candidate Evaluation and Like WebMCP Tools

**Files:**
- Modify: `web/assets/webmcp-app.js`
- Modify: `tests/webmcp_contract_test.php`
- Modify: `tests/page_contract_test.php`

- [ ] **Step 1: Write failing tool and no-visible-Like contracts**

Append to `tests/webmcp_contract_test.php`:

```php
test('browse tools bind evaluation and Like to the current card', function () use ($webmcpRoot): void {
    $tools = file_get_contents($webmcpRoot.'/web/assets/webmcp-app.js');
    foreach (['evaluate_current_candidate', 'send_like_to_current_candidate'] as $name) {
        expect_same(true, str_contains($tools, $name));
    }
    expect_same(true, str_contains($tools, 'currentCandidateId()'));
    expect_same(true, str_contains($tools, '/api/candidates/evaluate.php'));
    expect_same(true, str_contains($tools, '/api/candidates/like.php'));
});
```

Append to `tests/page_contract_test.php`:

```php
test('browse page exposes no visible Like control', function () use ($root): void {
    $page = file_get_contents($root.'/web/app/index.php');
    expect_same(false, preg_match('/<button[^>]*>[^<]*Like/i', $page) === 1);
    expect_same(true, str_contains($page, 'webmcp-app.js'));
});
```

- [ ] **Step 2: Run tests and verify the two tools are absent**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: FAIL because the app module currently registers only Profile upsert.

- [ ] **Step 3: Register evaluation against the live candidate**

Import `currentCandidateId` from `webmcp-common.js` and add:

```js
await context.registerTool({
  name: 'evaluate_current_candidate',
  description: 'Get the current Ainder candidate Agent Profile so you can answer the user question about this person. If Profile state is missing or expired, return the structured error and do not evaluate.',
  inputSchema: { type: 'object', properties: {}, additionalProperties: false },
  annotations: { readOnlyHint: true },
  execute: () => {
    const candidateId = currentCandidateId();
    if (!candidateId) return { ok: false, error: { code: 'CANDIDATE_REQUIRED', message: 'No current candidate.' } };
    return postJson('/ainder/api/candidates/evaluate.php', { candidate_id: candidateId });
  },
});
```

The tool must read the candidate ID when invoked, not capture the initial page candidate during module load.

- [ ] **Step 4: Register Like with an evaluation token**

Add:

```js
await context.registerTool({
  name: 'send_like_to_current_candidate',
  description: 'Send an Ainder Like to the current candidate only after the user asks for it. Requires a fresh evaluation token and repeats all Profile checks.',
  inputSchema: {
    type: 'object',
    properties: {
      evaluation_token: { type: 'string', pattern: '^[a-f0-9]{64}$' },
    },
    required: ['evaluation_token'],
    additionalProperties: false,
  },
  execute: (input) => {
    const candidateId = currentCandidateId();
    if (!candidateId) return { ok: false, error: { code: 'CANDIDATE_REQUIRED', message: 'No current candidate.' } };
    return postJson('/ainder/api/candidates/like.php', {
      candidate_id: candidateId,
      evaluation_token: input.evaluation_token,
    });
  },
});
```

Do not add any Like DOM control. Keep `browse.js` unchanged so both swipe directions remain pure loop navigation.

- [ ] **Step 5: Run PHP/JavaScript tests and commit the tools**

Run:

```bash
lean-ctx -c --raw php tests/run.php
node --check web/assets/webmcp-app.js
node --test tests/browse_model_test.mjs
```

Expected: all checks PASS.

Commit:

```bash
git add web/assets/webmcp-app.js tests/webmcp_contract_test.php tests/page_contract_test.php
git commit -m "feat: expose Ainder evaluation and Like tools"
```

### Task 11: Add Expired Upload Cleanup and Complete Verification

**Files:**
- Create: `web/maintenance/cleanup_agent_uploads.php`
- Modify: `tests/agent_registration_test.php`
- Modify: `web/config.local.example.php`

- [ ] **Step 1: Write the failing cleanup contract**

Append to `tests/agent_registration_test.php`:

```php
test('expired Agent uploads have a token-protected cleanup entrypoint', function (): void {
    $source = file_get_contents(
        dirname(__DIR__).'/web/maintenance/cleanup_agent_uploads.php'
    );
    expect_same(true, str_contains($source, 'migration_token'));
    expect_same(true, str_contains($source, 'agent_registration_uploads'));
    expect_same(true, str_contains($source, 'agent_registration_sessions'));
    expect_same(true, str_contains($source, 'ainder_cleanup_photo_paths'));
});
```

- [ ] **Step 2: Run tests and verify cleanup is absent**

Run `lean-ctx -c --raw php tests/run.php`.

Expected: FAIL because the maintenance entrypoint does not exist.

- [ ] **Step 3: Implement token-protected cleanup**

Create `web/maintenance/cleanup_agent_uploads.php` using the same CLI/POST `migration_token` guard as migrations. It must:

1. select `prepared`, `ready`, or `failed` upload rows whose expiry is older than server time and whose registration is not consumed;
2. collect only paths rooted under the explicit `web/uploads/.agent/` directory;
3. delete those files with `ainder_cleanup_photo_paths()`;
4. mark active expired sessions `expired`;
5. delete expired upload rows after file cleanup;
6. remove empty per-registration directories without recursive broad deletion;
7. return counts of expired sessions, removed rows, and removed files.

Never accept a directory from request input and never recursively target `web/uploads`, the repository root, or a user-supplied path.

- [ ] **Step 4: Run the complete local verification matrix**

Run:

```bash
lean-ctx -c --raw php tests/run.php
node --test tests/browse_model_test.mjs
find web -name '*.php' -print0 | xargs -0 -n1 php -l
node --check web/assets/profile.js
node --check web/assets/browse.js
node --check web/assets/webmcp-common.js
node --check web/assets/webmcp-registration.js
node --check web/assets/webmcp-app.js
git diff --check
```

Expected: all PHP tests PASS, Node tests PASS, every PHP file has no syntax errors, all JavaScript syntax checks pass, and the diff check is empty.

- [ ] **Step 5: Commit cleanup and push the tested implementation**

```bash
git add web/maintenance/cleanup_agent_uploads.php tests/agent_registration_test.php web/config.local.example.php
git commit -m "feat: clean expired Ainder Agent uploads"
git push origin main
```

Expected: push succeeds and GitHub `main` contains every task commit.

### Task 12: Deploy, Migrate, and Verify the Live Vertical Flow

**Files:**
- Deploy: `web/` to `/volume1/sweety.tw/ainder/`
- Do not deploy: `tests/`, `docs/`, `.superpowers/`, `.DS_Store`, or `pic/`

- [ ] **Step 1: Prepare the production-only signing configuration**

Generate a key locally:

```bash
php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

Add it as `upload_signing_key` and confirm `public_base_url` is `https://sweety.tw/ainder` in the existing production-only `/volume1/sweety.tw/ainder/config.local.php`. Do not print the key in logs, tests, diagnostics, or the final response.

- [ ] **Step 2: Upload the staged application files**

Use the existing mounted-host or FTP workflow to transfer only tracked files under `web/` into `/volume1/sweety.tw/ainder/`, preserving the directory structure. Confirm `web/uploads/.agent` and `web/uploads/profiles` are writable by the HTTP user without broadening permissions outside Ainder.

Expected: new API, library, asset, migration, and maintenance paths exist on the host; unrelated Sweety files are untouched.

- [ ] **Step 3: Run migration 004 once and rerun it**

Execute the deployed migration using the existing production migration token, then execute it a second time.

Expected for both runs: HTTP/CLI success JSON listing all five Agent workflow tables; no duplicate-table or duplicate-key error.

- [ ] **Step 4: Verify manual registration and normalized images**

With a fresh Google identity or a controlled test identity:

1. open `/ainder/profile/` in an ordinary browser;
2. reveal the manual form;
3. submit valid data with one landscape and one portrait source image;
4. confirm one member and two ordered `user_photos` rows are created;
5. inspect both stored files and confirm WebP MIME and exact 720 × 1280 dimensions;
6. confirm `/ainder/app/` loads and no Like control is rendered.

- [ ] **Step 5: Verify Agent registration without the form**

In a compatible Codex/ChatGPT desktop built-in browser using GPT-5.6 Sol or Terra:

1. open a new pending registration at `/ainder/profile/`;
2. inspect available site tools and confirm all three registration tools are present;
3. provide 2–6 local images, explicitly designate the main image, and confirm the Agent draft;
4. call start and prepare tools, PUT each local file to its signed URL, and call final submit;
5. confirm the page navigates to `/ainder/app/`;
6. confirm one member, ordered processed photos, and one Agent Profile with a three-calendar-month expiry exist;
7. confirm the manual form was never opened or submitted.

- [ ] **Step 6: Verify Profile errors, evaluation, and Like**

Use controlled members covering these states:

- self missing Profile;
- self expired Profile;
- target missing Profile;
- self fresh plus target old but present Profile;
- Demo target;
- two real members able to reciprocate.

Expected:

- the first three cases return their exact structured Profile error and create no evaluation or Like;
- an old target Profile is accepted when the self Profile is fresh;
- swiping in either direction creates no Like row;
- evaluation returns only the candidate Profile context and a short-lived token;
- a token sends one idempotent Like, cannot be replayed for another candidate, and expires;
- a Demo member receives a Like but never creates a Match;
- reciprocal real-member Likes create one stable ordered Match;
- existing Matches remain unaffected after a Profile is expired manually for the test.

- [ ] **Step 7: Configure cleanup and perform final safety checks**

Run the cleanup entrypoint once with a controlled expired upload and confirm only that attempt's temporary WebP is removed. Schedule the same token-protected command using the host's existing scheduler at a modest interval such as hourly.

Finally confirm:

- no secret or signed URL appears in Git or public diagnostics;
- the live page loads versioned WebMCP modules;
- unsupported browsers retain manual registration and browsing;
- the deployed commit matches Git `main`;
- temporary verification users/uploads are removed only if explicitly approved and precisely identified.
