# Ainder Agent Likes Inbox Design

## Scope

This increment completes the first inbound Like workflow without adding acceptance, messaging, or Match UI.

- A successful outgoing Like stores a non-empty Agent opinion.
- Candidates already Liked by the signed-in member are excluded from browse results and removed from the current DOM after Like succeeds.
- If the Agent already expressed an opinion about the candidate in the current conversation, it reuses that opinion. Otherwise it evaluates the candidate and forms an opinion before sending the Like.
- Eric receives a seeded one-way Like from demo member Chloe Park, including Chloe's Agent opinion about Eric. Eric has not reciprocated, so no Match is created.
- Pending inbound Likes appear in the desktop `Agent Likes` list. Clicking a row moves the existing candidate slider to that member.
- The remove icon deletes only that Like and its list row. It does not block, reject, or otherwise prevent the sender from sending another Like later.

## Data and API

Migration 005 adds nullable `likes.agent_opinion` for legacy compatibility. Every new Agent Like is nevertheless rejected by the API unless the trimmed opinion contains 1–1000 characters. Empty input returns `AGENT_OPINION_REQUIRED` before token consumption or persistence.

`send_like_to_current_candidate` requires `evaluation_token` and `opinion`. Its instructions require reuse of an already stated opinion and generation only when none exists.

`POST /api/likes/remove.php` accepts `like_id`, verifies the signed-in member is the recipient, and deletes only a pending incoming Like with no reciprocal Like. A missing row returns `LIKE_NOT_FOUND`; a reciprocal pair cannot be removed through this inbox action.

## Browse and inbox queries

The browse query receives the viewer member ID and excludes any candidate for whom a sender-to-recipient Like already exists. Incoming Likes remain browseable until Eric reciprocates.

The inbox query returns pending incoming Likes only: sender ID, Like ID, public display name, age, primary thumbnail, and stored Agent opinion. It excludes reciprocal pairs.

## UI behavior

The desktop empty state is replaced by a compact list when pending Likes exist. Each row contains a circular thumbnail, name, smaller age, and a right-aligned remove icon. Clicking the main row calls the shared browse controller to select the corresponding existing card. Removing a row updates the DOM only after the server confirms deletion; when the final row is removed, the empty state reappears.

## Test seed

A migration-token-protected seed endpoint inserts or refreshes one demo-to-real-member pending Like by exact display names. It requires the sender to be a demo member, the recipient to be a non-demo member, both Profiles to exist, a non-empty opinion, and no reciprocal Like.

## Out of scope

Accept/Like-back controls, Match presentation, Messages, blocking, rejection state, and mobile inbox layout are not added in this increment.
