# Hidden Registration Profile Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Ainder registration confirm only public personal data and photos while generating and submitting truthful private Agent Profile context without displaying it by default.

**Architecture:** Keep the existing WebMCP input schema and PHP submission pipeline. Strengthen the two registration tool descriptions and Profile property descriptions so compatible Agents disclose private-Profile creation, hide its contents by default, and ground Profile values only in actually available conversation and memory.

**Tech Stack:** Browser JavaScript WebMCP, JSON Schema, PHP repository test harness, mounted production deployment.

---

### Task 1: Hidden private-Profile WebMCP contract

**Files:**
- Modify: `tests/webmcp_contract_test.php`
- Modify: `web/assets/webmcp-registration.js`

- [ ] **Step 1: Write failing source-contract assertions**

Extend the registration WebMCP test with:

```php
foreach ([
    'confirm the public personal data',
    'consents to creating and storing a private Agent Profile',
    'Do not show profile_text by default',
    'actually available conversation and memory',
    'conservative estimate based on the earliest retained interaction actually available',
    'based on the frequency of interactions actually available',
] as $policy) {
    expect_same(true, str_contains($tools, $policy));
}
expect_same(false, str_contains(
    $tools,
    'user has confirmed all personal data, Agent Profile text'
));
expect_same(false, str_contains(
    $tools,
    'user has confirmed the complete draft'
));
```

- [ ] **Step 2: Run the full suite and verify RED**

Run: `php tests/run.php`

Expected: the WebMCP registration contract fails because current descriptions require confirming the Profile text and complete draft.

- [ ] **Step 3: Update the registration tool descriptions**

Use these descriptions in `web/assets/webmcp-registration.js`:

```js
description: 'Start Ainder registration only after the user confirms the public personal data, designated main photo, supporting-photo order, and consents to creating and storing a private Agent Profile. Do not show profile_text by default. Generate private Profile values only from the actually available conversation and memory; do not invent missing history.',
```

```js
description: 'Create the Ainder member, ordered photos, and private Agent Profile after every signed upload is ready and the user has confirmed the public registration data, photo order, and private-Profile creation. Do not show profile_text by default unless the user explicitly asks to see it.',
```

- [ ] **Step 4: Describe the three private Profile properties**

Change the schema properties to:

```js
profile_text: {
  type: 'string',
  minLength: 1,
  maxLength: 4000,
  description: 'Private Agent-authored observation grounded only in the actually available conversation and memory. Do not show profile_text by default unless the user explicitly asks.',
},
agent_known_duration_days: {
  type: 'integer',
  minimum: 0,
  maximum: 65535,
  description: 'A conservative estimate based on the earliest retained interaction actually available to the Agent; do not claim earlier history.',
},
interaction_density: {
  enum: ['low', 'medium', 'high'],
  description: 'An estimate based on the frequency of interactions actually available to the Agent; do not invent unseen activity.',
},
```

- [ ] **Step 5: Verify GREEN**

Run: `php tests/run.php`

Expected: all tests pass.

Run: `git diff --check`

Expected: no output and exit status 0.

- [ ] **Step 6: Commit, deploy, and push**

```bash
git add tests/webmcp_contract_test.php web/assets/webmcp-registration.js
git commit -m "fix: hide registration Profile by default"
cp web/assets/webmcp-registration.js /Volumes/sweety.tw/ainder/assets/webmcp-registration.js
shasum -a 256 web/assets/webmcp-registration.js /Volumes/sweety.tw/ainder/assets/webmcp-registration.js
git push origin main
```

Expected: production and repository hashes match and `origin/main` contains the change.

- [ ] **Step 7: Verify the live tool descriptions**

Reload `/ainder/profile/` in the existing in-app browser and confirm the live `start_agent_registration` and `submit_agent_registration` descriptions disclose private-Profile creation without requiring Profile text to be displayed.

