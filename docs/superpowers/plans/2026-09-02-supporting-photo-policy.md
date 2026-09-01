# Supporting Photo Policy Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Align the live WebMCP registration tool description with the approved rule that only the designated main photo requires one visible human face.

**Architecture:** Keep PHP upload validation unchanged because the server performs technical image validation, not semantic person detection. Encode the order-specific semantic policy in the WebMCP tool description and protect the wording with a source-contract regression test.

**Tech Stack:** Browser JavaScript WebMCP, PHP repository test harness, mounted production deployment.

---

### Task 1: Order-specific WebMCP photo policy

**Files:**
- Modify: `tests/webmcp_contract_test.php`
- Modify: `web/assets/webmcp-registration.js`

- [ ] **Step 1: Write the failing contract assertions**

Extend `registration page loads top-level JavaScript WebMCP tools` with:

```php
foreach ([
    'If sort_order is 1',
    'If sort_order is 2 through 6',
    'do not need a person or visible face',
] as $policy) {
    expect_same(true, str_contains($tools, $policy));
}
```

- [ ] **Step 2: Run the full test suite and verify RED**

Run: `php tests/run.php`

Expected: the WebMCP contract test fails because the current description applies the human-face rule without limiting it to order 1.

- [ ] **Step 3: Replace the ambiguous tool description**

Use this exact `prepare_photo_upload` description:

```js
description: 'Create one signed upload URL for a confirmed Ainder registration photo. If sort_order is 1, confirm the designated main photo is one real human with a visible face. If sort_order is 2 through 6, supporting photos may show pets, travel, scenery, or other lifestyle content and do not need a person or visible face. Reject clearly sexual, violent, illegal, or otherwise unsuitable supplied images at every order.',
```

- [ ] **Step 4: Run verification and verify GREEN**

Run: `php tests/run.php`

Expected: all tests pass.

Run: `git diff --check`

Expected: no output and exit status 0.

- [ ] **Step 5: Commit and deploy the reviewed asset**

```bash
git add tests/webmcp_contract_test.php web/assets/webmcp-registration.js
git commit -m "fix: allow non-person supporting photos"
cp web/assets/webmcp-registration.js /Volumes/sweety.tw/ainder/assets/webmcp-registration.js
shasum -a 256 web/assets/webmcp-registration.js /Volumes/sweety.tw/ainder/assets/webmcp-registration.js
git push origin main
```

Expected: local and production hashes match and `origin/main` contains the fix.

- [ ] **Step 6: Verify the live WebMCP description**

Reload the existing in-app browser registration page, fetch its WebMCP tools, and confirm `prepare_photo_upload` exposes the order-specific description before resuming the separately confirmed registration submission.

