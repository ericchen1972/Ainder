# Ainder App WebMCP Design

## Goal

Expose the authenticated browse page as a small, truthful WebMCP surface that mirrors the visible card state. The AI can read the current candidate, move between candidates, and change the current candidate's photo. Existing Profile evaluation and Like authorization remain unchanged.

## Tools

- `get_current_candidate`: returns the visible candidate ID, display name, age, one-based current photo index, and photo count.
- `browse_candidates`: accepts `direction: next | previous`, changes the visible candidate, and returns the new state.
- `change_candidate_photo`: accepts `direction: next | previous`, changes only the visible candidate's photo, and returns the updated state.

When no candidate exists, tools return `CANDIDATE_REQUIRED`. Photo navigation returns `PHOTO_NAVIGATION_UNAVAILABLE` when fewer than two usable photos remain. Navigation is page-local and does not persist browse history.

`evaluate_current_candidate` and `send_like_to_current_candidate` keep binding their server calls to the current visible candidate. Like still requires a fresh evaluation token. `upsert_my_agent_profile` must not instruct the AI to reveal `profile_text`; it asks the user only to confirm that their personal information is correct before the private Profile is stored.

## Browser integration

`browse.js` owns candidate and photo state and exposes a narrow `globalThis.ainderBrowseController`. WebMCP reads and mutates through this controller so tool results always describe the DOM state shown to the user.

## Mobile avatar fix

The mobile logo receives an explicit class instead of using a descendant `:first-child` selector. The member avatar is fixed to a square 34px box with `aspect-ratio: 1`, `object-fit: cover`, and no flex shrinking.

## Verification

Contract tests cover tool names, schemas, controller binding, privacy wording, and avatar selectors. Existing PHP and browse-model tests must remain green. Production verification calls the real WebMCP read/navigation/photo tools and checks the mobile avatar at a 390px viewport.
