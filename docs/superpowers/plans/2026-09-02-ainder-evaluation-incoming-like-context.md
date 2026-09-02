# Ainder Evaluation Incoming Like Context Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the existing candidate evaluation so the AI knows when the current candidate has already Liked the signed-in member and can read that Like's Agent opinion with correct source semantics.

**Architecture:** Keep `evaluate_current_candidate` and its endpoint unchanged as the single entry point. Add one focused incoming-Like lookup to `agent_actions.php`, normalize its optional response through a small pure helper, and append `incoming_like_context` only when a non-empty candidate-to-requester Like opinion exists. Update WebMCP metadata so the AI understands that this is the candidate Agent's opinion about the signed-in member, not candidate Profile text.

**Tech Stack:** PHP 8 strict mode, mysqli prepared statements, vanilla JavaScript WebMCP registration, existing PHP contract/unit test harness, mounted production deployment.

---

### Task 1: Define and implement the incoming Like evaluation context

**Files:**
- Modify: `tests/agent_actions_test.php`
- Modify: `web/lib/agent_actions.php`

- [ ] **Step 1: Write the failing pure-behavior and source-contract tests**

Add tests that require valid rows to produce the exact public context, empty rows to produce no context, and the SQL direction to remain candidate-to-requester:

```php
test('incoming Like context preserves source meaning and omits empty opinions', function (): void {
    expect_same(null, ainder_incoming_like_context_from_row(null));
    expect_same(null, ainder_incoming_like_context_from_row([
        'agent_opinion' => '   ',
    ]));
    expect_same([
        'has_incoming_like' => true,
        'agent_opinion' => 'Chloe Agent opinion about Eric.',
    ], ainder_incoming_like_context_from_row([
        'agent_opinion' => '  Chloe Agent opinion about Eric.  ',
    ]));
});

test('candidate evaluation reads only candidate-to-requester incoming Likes', function () use ($root): void {
    $actions = file_get_contents($root.'/web/lib/agent_actions.php');
    expect_same(true, str_contains($actions, 'sender_user_id = ? AND recipient_user_id = ?'));
    expect_same(true, str_contains($actions, 'ainder_find_incoming_like_context'));
    expect_same(true, str_contains($actions, "'incoming_like_context'"));
});
```

- [ ] **Step 2: Run the focused test to verify RED**

Run:

```bash
php tests/run.php
```

Expected: FAIL because `ainder_incoming_like_context_from_row()` and the evaluation lookup do not exist.

- [ ] **Step 3: Add the minimal normalization and database lookup**

Add to `web/lib/agent_actions.php`:

```php
function ainder_incoming_like_context_from_row(?array $row): ?array
{
    $opinion = trim((string) ($row['agent_opinion'] ?? ''));
    if ($opinion === '') {
        return null;
    }

    return [
        'has_incoming_like' => true,
        'agent_opinion' => $opinion,
    ];
}

function ainder_find_incoming_like_context(
    mysqli $database,
    int $requesterId,
    int $candidateId
): ?array {
    $statement = $database->prepare(
        'SELECT agent_opinion FROM likes '
        .'WHERE sender_user_id = ? AND recipient_user_id = ? LIMIT 1'
    );
    $statement->bind_param('ii', $candidateId, $requesterId);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();

    return ainder_incoming_like_context_from_row(
        is_array($row) ? $row : null
    );
}
```

In `ainder_create_candidate_evaluation()`, build the existing result, look up the
incoming context, and append it only when non-null:

```php
$result = [
    'candidate' => [
        'id' => (int) $candidate['id'],
        'display_name' => (string) $candidate['display_name'],
        'profile_text' => (string) $targetProfile['profile_text'],
        'agent_known_duration_days' => (int) $targetProfile['agent_known_duration_days'],
        'interaction_density' => (string) $targetProfile['interaction_density'],
    ],
    'evaluation_token' => $token,
    'expires_at' => $expires,
];
$incomingLikeContext = ainder_find_incoming_like_context(
    $database,
    $requesterId,
    $candidateId
);
if ($incomingLikeContext !== null) {
    $result['incoming_like_context'] = $incomingLikeContext;
}

return $result;
```

- [ ] **Step 4: Run the focused test to verify GREEN**

Run:

```bash
php tests/run.php
```

Expected: all PHP tests pass.

- [ ] **Step 5: Commit the backend contract**

```bash
git add tests/agent_actions_test.php web/lib/agent_actions.php
git commit -m "feat: expose incoming Like evaluation context"
```

### Task 2: Teach WebMCP the context semantics

**Files:**
- Modify: `tests/webmcp_contract_test.php`
- Modify: `web/assets/webmcp-app.js`

- [ ] **Step 1: Write the failing WebMCP metadata test**

Extend the existing evaluation tool contract test:

```php
expect_same(true, str_contains(
    $tools,
    'incoming_like_context'
));
expect_same(true, str_contains(
    $tools,
    "candidate's Agent opinion about the signed-in member"
));
```

- [ ] **Step 2: Run the test to verify RED**

Run:

```bash
php tests/run.php
```

Expected: FAIL because the evaluation tool description does not mention the new
context or its source semantics.

- [ ] **Step 3: Update the existing tool description**

Replace the `evaluate_current_candidate` description with:

```js
description: 'Get the current Ainder candidate Agent Profile so you can answer the user question about this person. When incoming_like_context is present, tell the user that this candidate already sent them a Like and use agent_opinion only as the candidate\'s Agent opinion about the signed-in member; do not treat it as part of the candidate Profile. If Profile state is missing or expired, return the structured error and do not evaluate.',
```

- [ ] **Step 4: Run the test to verify GREEN**

Run:

```bash
php tests/run.php
node --check web/assets/webmcp-app.js
```

Expected: all PHP tests pass and JavaScript syntax exits 0.

- [ ] **Step 5: Commit WebMCP metadata**

```bash
git add tests/webmcp_contract_test.php web/assets/webmcp-app.js
git commit -m "feat: describe incoming Like context to AI"
```

### Task 3: Full verification, deployment, and production proof

**Files:**
- Deploy: `web/lib/agent_actions.php`
- Deploy: `web/assets/webmcp-app.js`
- Preserve: `/Volumes/sweety.tw/ainder/config.local.php`
- Preserve: `/Volumes/sweety.tw/ainder/.user.ini`
- Preserve: `/Volumes/sweety.tw/ainder/uploads/`

- [ ] **Step 1: Run the complete local evidence loop**

Run:

```bash
php tests/run.php
php -l web/lib/agent_actions.php
php -l web/api/candidates/evaluate.php
node --check web/assets/webmcp-app.js
git diff --check 93f6f93..HEAD
```

Expected: every test passes, all syntax checks exit 0, and the diff check is
clean.

- [ ] **Step 2: Push the approved main-branch implementation**

```bash
git push origin main
```

Expected: remote `main` advances to the implementation commit.

- [ ] **Step 3: Deploy only the reviewed runtime**

```bash
cp web/lib/agent_actions.php /Volumes/sweety.tw/ainder/lib/agent_actions.php
cp web/assets/webmcp-app.js /Volumes/sweety.tw/ainder/assets/webmcp-app.js
shasum web/lib/agent_actions.php /Volumes/sweety.tw/ainder/lib/agent_actions.php
shasum web/assets/webmcp-app.js /Volumes/sweety.tw/ainder/assets/webmcp-app.js
```

Expected: each local/deployed hash pair matches.

- [ ] **Step 4: Verify the authenticated production WebMCP response**

Reload `https://sweety.tw/ainder/app/` in the signed-in In Browser session, move
the Slide view to Chloe Park if necessary, and call
`evaluate_current_candidate`. Confirm the response contains:

```json
{
  "candidate": {
    "display_name": "Chloe Park"
  },
  "incoming_like_context": {
    "has_incoming_like": true,
    "agent_opinion": "<the existing stored Chloe-Agent opinion about Eric>"
  }
}
```

Do not call `send_like_to_current_candidate`; Eric-to-Chloe Like and Match must
remain absent.

- [ ] **Step 5: Confirm repository and production state**

Run:

```bash
git status --short
git rev-parse HEAD
git ls-remote origin refs/heads/main
```

Expected: only the pre-existing `.DS_Store` and `.superpowers/` remain
untracked, and local/remote `main` revisions match.
