# Ainder Test Account Login Design

**Date:** 2026-09-02  
**Status:** Approved for planning  
**Scope:** Landing-page test logins for Grace Liu and John Carter with deterministic incoming Like state

## Goal

Add two public test-login entries to the Ainder landing page so a reviewer can enter the application without Google authentication. Each login restores only that test member's relationship state and guarantees one unanswered incoming Like.

The two independent scenarios are:

- `Ethan Park -> Grace Liu`
- `Evelyn Grant -> John Carter`

The independent senders prevent logging in as one test member from removing the other test member's incoming Like.

## Existing Demo Identity Selection

- Grace Liu remains the existing deterministic member `demo:010`.
- Existing member `demo:011` is renamed from Liam Carter to John Carter. Its curated photos, birth date, gender, and Agent Profile remain attached to the same deterministic identity; the profile text is updated only to use the new name.
- Ethan Park remains `demo:001`.
- Evelyn Grant remains `demo:020`.

The login and reset code resolves these members by their deterministic `google_sub` values. It must never resolve or mutate a member by display name, email, URL parameter, or arbitrary numeric ID.

## Considered Approaches

### 1. Server-side transactional test login (selected)

A dedicated POST endpoint validates a fixed account slug, resets the selected member, creates the incoming Like, and establishes the session. This provides an atomic state transition and a narrow allowlist.

### 2. Browser-side sequence of existing APIs

The landing page could log in and call several remove/create endpoints. This would expose intermediate states, require broader unauthenticated APIs, and could leave partial data when a request fails.

### 3. Scheduled or deployment-time seed reset

A seed job could recreate both Likes periodically. It would not guarantee the expected state at the moment a reviewer logs in and could race with active testing.

## Landing Page UI

The landing page renders two test account buttons in addition to Google login:

- a circular main photo;
- the text `Login as Grace` or `Login as John` below the photo;
- the entire photo-and-label unit is one semantic submit button.

Desktop placement is centered above the lower part of the landing hero. On screens at or below the existing 720 px breakpoint, the two buttons form a compact row above a notice and device safe-area insets.

The test-login group includes an English-only alert below the two account buttons:

> Test account activity is reset, so Likes, Matches, and Messages are not retained. For the most accurate experience, sign in with your own Google account and use ChatGPT with long-term memory about you.

The buttons and alert share one responsive layout container so they move together and cannot overlap. Ainder does not add locale detection for this notice or these labels; they remain English in every browser language.

Photos come from each test member's current `user_photos` row where `sort_order = 1`. The landing page attempts to load these records without making the entire landing page dependent on database availability. If the database or either required record is unavailable, the test-login group is omitted while Google login and the hero remain available.

The two forms use POST and the existing Ainder form CSRF token. No member ID is placed in the form; only a fixed public slug (`grace` or `john`) is submitted.

## Test Login Endpoint

Create a dedicated endpoint under `web/auth/` for test login. It accepts only POST requests with:

- a valid session CSRF token;
- one of the exact slugs `grace` or `john`.

The server maps the slug internally:

| Login | Member | Incoming Like sender |
| --- | --- | --- |
| `grace` | `demo:010` | `demo:001` (Ethan Park) |
| `john` | `demo:011` (John Carter) | `demo:020` (Evelyn Grant) |

All four users must exist, be active, have the expected gender relationship, have a main photo, and have Agent Profiles. A failure returns to the landing page with a generic test-login error and does not change the session or relationship data.

## Atomic Reset and Seed Transaction

After locking the selected member and incoming sender rows, one database transaction performs the following operations:

1. Delete candidate evaluations where the selected member is requester or candidate, preventing stale evaluation tokens from surviving a reset.
2. Delete Matches containing the selected member. The `messages.match_id` foreign key removes all messages belonging to those Matches.
3. Delete all Likes sent or received by the selected member.
4. Insert exactly one Like from the configured sender to the selected member with a non-empty deterministic Agent opinion.
5. Commit.

The endpoint then regenerates the PHP session ID, clears pending Google-registration state, stores the selected member ID as `ainder_member_id`, records the login time, and redirects to `/ainder/app/`.

The reset deliberately affects only relationship and conversation state involving the selected test member. It does not reset names, photos, birth dates, gender, or Agent Profiles.

## Deterministic Test Agent Opinions

The seeded opinions are stable test fixtures based on the existing seeded Agent Profiles rather than generated during login:

- Ethan about Grace: `Grace's warmth, creativity, and respect for emotional boundaries look promising. Ethan's steady listening may suit her need for gentleness, while both should be careful not to postpone difficult conversations.`
- Evelyn about John: `John's reliability, humor, and active lifestyle look compatible with Evelyn's practical and health-conscious approach. They may connect through shared routines, as long as solutions do not replace emotional listening.`

These strings satisfy the existing requirement that every Like contain an Agent opinion and make repeated test runs deterministic.

## Coexistence Guarantee

Because Grace's scenario uses Ethan and John's scenario uses Evelyn:

- logging in as Grace resets only Grace-related data and preserves `Evelyn -> John`;
- logging in as John resets only John-related data and preserves `Ethan -> Grace`;
- after both accounts have been used at least once, both unanswered Likes can coexist;
- repeatedly logging in as either test account restores that account to exactly one unanswered incoming Like.

## Error Handling and Security

- Reject GET and unsupported methods.
- Reject missing or invalid CSRF tokens.
- Reject every slug outside the two-entry allowlist.
- Roll back the transaction on any missing member, invalid member state, missing Profile, missing photo, SQL error, or Like creation error.
- Do not expose database errors or member IDs to the browser.
- Regenerate the session ID only after the transaction commits.
- Keep Google authentication unchanged.

## Tests

Automated coverage will verify:

- the slug mapping contains only Grace and John and uses stable Demo identities;
- the landing page renders two POST forms, CSRF fields, labels, and main-photo sources;
- the reset function uses one transaction, deletes evaluations, Matches and Likes in the required order, and inserts one incoming Like;
- Match deletion remains responsible for cascading message deletion;
- an invalid slug or missing member cannot mutate data or establish a session;
- John Carter replaces Liam Carter consistently in the Demo seed and Agent Profile text;
- desktop and mobile CSS provide circular, non-distorted avatars and bottom safe-area spacing;
- the short labels and English-only test-account alert render inside the shared responsive container;
- existing PHP contract, model, syntax, and browser tests remain green.

Browser verification will cover both landing-page layouts, Grace login, John login, coexistence of both Likes, logout return behavior, and the absence of console errors.

## Deployment

After local verification, deploy the `web/` tree to the existing production mount while preserving `config.local.php` and uploads. Rerun the deterministic Demo seed so `demo:011` becomes John Carter, then verify both test logins against the production database and UI.
