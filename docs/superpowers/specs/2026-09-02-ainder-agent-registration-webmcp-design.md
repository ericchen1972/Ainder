# Ainder Agent Registration and Profile-Gated Actions Design

**Date:** 2026-09-02
**Status:** Approved for implementation planning

## Objective

Add an Agent-first Ainder registration path for AI clients that support WebMCP and can upload local image bytes. The Agent gathers and confirms the member data, uploads photos through short-lived signed URLs, and submits the text data through WebMCP without interacting with the website form.

Keep manual registration available for ordinary browsers. Manual members can browse immediately but cannot send Likes through the website. Like remains an Agent action, and both candidate evaluation and Like are gated by Agent Profile state.

## Superseded Decisions

For the areas covered here, this document supersedes conflicting statements in the earlier onboarding and hackathon specifications:

- Agent registration is now in scope.
- Agent registration creates the Agent Profile together with the member.
- Agent Profile freshness is three calendar months, not 30 days.
- Every real registration requires 2–6 photos.
- Every uploaded member photo is normalized to a 720 × 1280 WebP.
- Swiping is browsing only and never sends a Like.
- The normal website does not expose a Like action.

Unrelated decisions in the earlier specifications remain unchanged.

## Product Boundary

Ainder supports two registration experiences over one member model and one shared validation layer.

### Manual browser registration

- A new Google identity can reveal and submit the existing manual form.
- Manual registration creates the member and 2–6 processed photos.
- Manual registration does not create an Agent Profile.
- A manual member can browse opposite-gender candidates.
- The website provides no Like button or ordinary Like workflow.
- A manual member can later open Ainder in a compatible AI client, create an Agent Profile with user confirmation, and then use Agent actions.

### Agent registration

- The client must support top-level JavaScript WebMCP tools and direct byte upload to a signed URL.
- The Agent does not read, fill, or submit the manual HTML form.
- The Agent gathers the required data from memory and the current conversation, asks only for missing information, and obtains the required images.
- The Agent presents the complete public registration draft, discloses that a private Agent Profile will also be created, and performs no write until the user confirms the public data, photos, and private-Profile creation.
- Successful Agent registration atomically creates the member, processed photos, and Agent Profile.

Registration source does not permanently classify the member. Current Agent Profile state, not the original registration path, determines access to evaluation and Like.

## Required Registration Data

Both paths require:

- display name: 1–120 characters;
- birth date: a valid date proving the member is at least 18 years old;
- gender: exactly `male` or `female`;
- verified Google identity from the pending Ainder session;
- 2–6 photos in explicit display order.

The Google subject and email always come from the verified pending server session. They are never accepted from Agent or form input as authoritative identity data.

Agent registration additionally requires:

- `profile_text`: the Agent-authored private description grounded only in the conversation and memory actually available to the Agent;
- `agent_known_duration_days`: the Agent's estimate of how long it has known the user;
- `interaction_density`: `low`, `medium`, or `high`;
- generation time used to calculate a three-calendar-month expiry.

`profile_text`, familiarity duration, and interaction density are private Agent context. They are not exposed as editable website fields and are not shown by default during registration. The Agent must not invent missing evidence, but registration does not require the user to review or approve the Profile wording.

## Agent Conversation Contract

When the user asks the Agent to register for Ainder:

1. Summarize the public registration fields: display name, birth date, gender, designated main photo, and supporting-photo order.
2. Ask for each missing required field without inventing an answer.
3. Request 2–6 images if the user has not supplied enough.
4. Require the user to designate one supplied image as the main photo.
5. If the user has not designated a main photo, ask which image should be first. The Agent must not choose silently.
6. Validate the designated main photo and perform a basic inappropriate-content check over all supplied images.
7. Present the final public registration data, main photo, and supporting-photo order without showing `profile_text`, familiarity duration, or interaction density by default.
8. Tell the user that registration also creates a private Agent Profile from the Agent's actually available conversation and memory, then ask for a single explicit confirmation covering the public data, photos, and consent to create and store that private Profile.
9. Begin the WebMCP and signed-upload writes only after confirmation.

If the user changes any confirmed public field or image, present the revised final set and obtain confirmation again. The Agent may show the private Profile only when the user explicitly asks to see it.

## Main Photo and Content Rules

Before upload, the Agent checks only the designated main photo for portrait suitability:

- it is a photograph of one real human;
- no second person is present;
- a human face is visible;
- glasses, sunglasses, masks, hats, natural angles, and partial facial obstruction are allowed;
- the image need not resemble an identity document and the Agent does not verify that the person is the user;
- a back-only image, a missing face, a group photo, an illustration, scenery, or an animal-only image is not an acceptable main photo.

Supporting photos do not need a person and receive no person-count or face-visibility check.

The Agent performs a basic pre-upload check for clearly sexual, violent, illegal, or otherwise unsuitable material across all images. The MVP does not add server-side AI or semantic image moderation. Manual uploads likewise receive no semantic moderation in this phase.

The backend remains authoritative for file count, size, detected MIME type, exact processed dimensions, image decoding, ownership, and upload state.

## Shared Image Processing

Manual multipart uploads and Agent signed uploads must preprocess every selected source image before it reaches PHP. Both clients use the same canonical output contract: center-cropped 9:16, exactly 720 × 1280 pixels, WebP quality 84. No original-format member photo is uploaded or committed directly.

The manual registration JavaScript decodes each selected JPEG, PNG, or WebP with browser orientation handling, calculates a centered cover crop, draws it to a 720 × 1280 canvas, and replaces the form's `FileList` with generated WebP files. The preview shows those generated files, so it matches the stored crop. Selection and submission stay disabled while conversion is in progress.

The Agent must perform the same conversion locally before calling `prepare_photo_upload`; the declared filename ends in `.webp`, MIME is `image/webp`, and byte size describes the generated file.

For each accepted client-processed image, the backend:

1. accepts only detected WebP input;
2. rejects files larger than 10 MiB;
3. checks dimensions are exactly 720 × 1280 before full GD decoding;
4. decodes and rewrites the already-bounded image to strip metadata and verify valid pixels;
5. outputs exactly 720 × 1280 pixels as WebP quality 84;
6. stores a server-generated random filename;
7. removes the temporary upload after successful conversion or terminal failure.

An original JPEG/PNG, a differently sized WebP, or a spoofed MIME is rejected with a structured validation error before the memory-intensive decode path. JavaScript is required for the manual photo form; the submit control remains unavailable until client processing succeeds.

All `user_photos.file_path` values for real members point to the processed WebP. Photo order remains 1–6, and order 1 is the user-designated main photo.

## Agent Registration Architecture

### `start_agent_registration`

This WebMCP write tool:

- requires a valid pending Google identity session;
- creates a short-lived registration session bound to that browser session and Google subject;
- records no member and no Agent Profile;
- returns a registration session identifier and expiry;
- supports an idempotency key so retries do not create parallel active sessions for the same logical attempt.

### `prepare_photo_upload`

This WebMCP write tool accepts the registration session identifier plus the generated WebP filename, `image/webp` MIME type, generated byte size, and requested display order.

Its tool description must make the display-order policy explicit: `sort_order = 1` is the designated main photo and requires one real human with a visible face; `sort_order = 2–6` are supporting photos and may contain pets, travel, scenery, or other lifestyle content without a person-count or face-visibility check. The basic inappropriate-content check still applies to every order.

It returns:

- a short-lived signed upload URL;
- the required HTTP method and headers;
- an opaque `upload_id`;
- maximum byte size and expiry.

The signature binds the upload to the registration session, upload ID, expected size range, permitted input type, and expiry. It cannot read or replace another member's files.

### Signed image upload

The AI client first applies orientation, proportional cover scaling, and centered 9:16 cropping, then sends the resulting 720 × 1280 WebP bytes to the signed URL. The signed endpoint verifies the signature, detected WebP MIME, exact dimensions, declared byte size, and valid image data. It rewrites the bounded image through the shared server processor and marks the upload `ready` only after the verified WebP has been committed to temporary registration storage.

An upload record progresses through `prepared`, `ready`, `consumed`, or `failed`. A failed or expired upload cannot be referenced by final registration.

### `submit_agent_registration`

This WebMCP write tool accepts:

- registration session identifier;
- display name, birth date, and gender;
- ordered `upload_id` values;
- private Agent Profile text grounded in the Agent's actually available conversation and memory;
- Agent familiarity duration and interaction density;
- the same logical idempotency key.

The backend validates the pending Google identity, all fields, photo count, unique order, upload ownership, upload readiness, and Profile data. It then creates the member, moves the processed WebPs into the member directory, inserts the photo rows, inserts the Agent Profile, and consumes the registration session in one logical transaction.

On success, the tool authenticates the member session and returns `/ainder/app/`. The top-level page navigates to that route. Tool response data alone is not considered proof that navigation or registration succeeded; the resulting authenticated page and member state remain verifiable.

## Atomicity and Cleanup

- No member row is created by Google sign-in, registration start, signed URL creation, or image upload.
- Agent registration succeeds only when the member, all 2–6 final photos, and Agent Profile are complete.
- A database or filesystem failure rolls back database writes and removes files created by the failed attempt.
- A duplicate final request with the same idempotency key returns the already-created member result instead of creating a duplicate.
- Expired registration sessions and their unconsumed processed photos are periodically removed.
- Manual and Agent registrations use the same member-creation and final-photo persistence services to avoid divergent validation.

## Agent Profile Lifecycle

Agent Profile creation and refresh are confirmed write operations.

- Agent registration creates the initial Profile as part of registration.
- Manual registration creates no Profile.
- A compatible Agent can later propose a Profile for a manual member.
- The user reviews the proposed Profile text and can request changes or confirm submission.
- Updating a Profile replaces the active Profile content and refreshes its generation and expiry timestamps.
- A Profile expires three calendar months after its generation or last confirmed refresh.
- Existing Matches are unaffected by missing or expired Profile state; unmatching is out of scope.

The current single-row-per-user `agent_profiles` model remains sufficient for the MVP. `profile_text`, `agent_known_duration_days`, `interaction_density`, `generated_at`, and `expires_at` remain the core fields.

## Core Agent Intent: Evaluate Candidate

When the user asks an Agent a question such as "對方如何？", the page's evaluation WebMCP tool checks:

1. the authenticated member has an Agent Profile;
2. the authenticated member's Agent Profile has not expired;
3. the current candidate has an Agent Profile.

The tool does not reject a candidate because the candidate Profile is older than three months. Only the requesting member's freshness is checked.

When valid, the tool returns the Profile context needed for the user's Agent to form a qualitative opinion, including the candidate Agent's familiarity duration and interaction density. Ainder does not calculate a match percentage or promise a relationship outcome.

## Core Agent Intent: Send Like

Swiping left or right changes the visible candidate and never creates a Like.

The ordinary website exposes no Like button. A compatible Agent sends a Like through the WebMCP action after the user's request. The Like workflow repeats the Profile checks rather than relying on a stale earlier result.

For the MVP, a successful evaluation can issue a short-lived, single-use `evaluation_token` bound to the requesting member and candidate. `send_like` consumes that token. If the user requests a Like directly, the Agent can obtain the required evaluation token in the background without adding another user-facing confirmation beyond the consequential Like confirmation required by the client.

Like is unavailable when either party has no Profile or when the requesting member's Profile is expired. Demo members can receive Likes but never produce a Match because they do not log in or reciprocate.

The absence of a website control and the evaluation-token workflow are product-level restrictions, not cryptographic proof that a request came from an AI. Deliberate API emulation is outside the MVP threat boundary. Remote MCP, client attestation, and Agent-specific OAuth are out of scope.

## Structured Profile Errors

Evaluation and Like tools return machine-readable errors and perform no partial action:

- `SELF_PROFILE_MISSING`: the requesting member has no Agent Profile;
- `SELF_PROFILE_EXPIRED`: the requesting member's Profile is older than three calendar months;
- `TARGET_PROFILE_MISSING`: the current candidate has no Agent Profile;
- `PROFILE_REQUIRED`: a fallback code when the specific missing party cannot safely be disclosed;
- `LIKE_NOT_SENT`: the Like was not persisted.

The Agent explains the result to the user in natural language. For a missing or expired self Profile, it can offer to draft a new Profile, obtain confirmation, submit it through the independent Profile tool, and then resume the original evaluation or Like request. The tool never silently creates or refreshes a Profile.

## Manual Browser Behavior

Unsupported clients preserve the normal manual experience:

- feature detection failure does not break the page or form;
- the user can register, browse, change photos, and move through the candidate loop;
- Agent-only controls are not replaced with misleading disabled Like controls;
- attempting to invoke an unavailable Agent flow presents guidance to use a compatible AI client;
- the same authentication, authorization, registration validation, and processed-photo rules apply.

## Error Handling

- Missing or expired pending Google identity prevents either registration path.
- Signed upload preparation rejects invalid order, count, declared size, or unsupported declared type.
- The upload endpoint rejects invalid signatures, expired URLs, size mismatches, unsupported decoded content, or undecodable images.
- A failed conversion marks the upload failed, removes partial output, and allows the Agent to request a replacement URL.
- Final Agent submission rejects missing, duplicate, expired, failed, already-consumed, or cross-session upload IDs.
- An expired Agent Profile blocks evaluation and Like but not browsing or an existing Match.
- Registration and Like retries are idempotent.
- User-facing errors never expose server paths, SQL, signatures, session identifiers, or raw exception details.

## Testing and Verification

Automated coverage must include:

- identical field validation for manual and Agent registration;
- exact 18th-birthday acceptance and underage rejection;
- 2 and 6 photos accepted, 1 and 7 rejected;
- JPEG, PNG, and WebP decoding; spoofed MIME and invalid image rejection;
- EXIF orientation handling;
- proportional cover scaling, centered crop, exact 720 × 1280 dimensions, and WebP output;
- temporary original and metadata removal;
- signed URL ownership, expiry, tamper, size, and replay rejection;
- upload state transitions and orphan cleanup;
- no member persistence before final Agent submission;
- atomic member, photo, and Profile creation with rollback on every failure boundary;
- registration idempotency;
- manual registration creates no Agent Profile;
- Agent registration creates a fresh three-month Profile;
- evaluation and Like behavior for every self/target Profile state;
- only the requesting member's Profile date is enforced;
- swipes never create Likes;
- `evaluation_token` binding, expiry, single use, and candidate mismatch rejection;
- demo members can receive a Like but cannot Match;
- unsupported WebMCP feature detection leaves manual registration and browsing usable.

Live verification must demonstrate:

1. manual registration uploads are stored as 720 × 1280 WebPs;
2. a compatible Agent registers without touching the manual form;
3. signed uploads and final WebMCP submission create one member, ordered photos, and one fresh Agent Profile;
4. a registered Agent member lands at `/ainder/app/`;
5. a manual member can browse but sees no Like control;
6. a manual member later creates a confirmed Agent Profile through WebMCP;
7. missing and expired Profile errors are returned to and explained by the Agent;
8. candidate evaluation succeeds with a fresh self Profile and any existing target Profile;
9. an Agent-requested Like persists exactly once and swiping persists none.

## Acceptance Criteria

- Agent registration uses WebMCP for text and signed upload URLs for image bytes, never the manual form.
- The user confirms the complete public registration data, photo order, and consent to create and store a private Agent Profile before any write; Profile contents remain hidden by default.
- Agent registration atomically creates member, 2–6 processed photos, and Agent Profile.
- Manual registration remains available and creates no Agent Profile.
- Both upload paths produce centered-crop 720 × 1280 WebP member photos through one shared processor.
- The user explicitly designates the main photo and the Agent applies the approved lenient single-human visible-face rule.
- Ainder's normal website supports browsing but does not provide Like.
- Evaluation and Like both enforce Profile existence for both parties and three-month freshness only for the requester.
- Missing or expired Profile state returns a structured error for the Agent to explain and causes no evaluation or Like.
- Existing Matches remain usable regardless of later Profile expiry.
- The implementation remains a WebMCP-focused MVP without server-side AI image moderation or cryptographic Agent attestation.
