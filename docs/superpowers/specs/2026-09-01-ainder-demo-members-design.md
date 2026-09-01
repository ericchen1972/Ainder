# Ainder Demo Members and Public Intro Design

**Date:** 2026-09-01

## Purpose

Ainder needs a useful candidate pool before real members join. The first demo cohort contains 20 clearly labeled virtual members backed by curated Unsplash images and fictional English-language data. Demo members may receive Likes but can never create a Match because no person controls or logs into those accounts.

This design also adds a short public introduction to every member. The public introduction is shown with the member's photos. It is separate from the private Agent Profile, which is never rendered by default and is disclosed only when a user explicitly asks their Agent to evaluate that member.

## Scope

This phase includes:

- the database changes required for demo members, mixed photo sources, public introductions, and Agent Profiles;
- the required 50-character public-introduction field in real-member onboarding;
- an idempotent, temporary seed flow for 20 demo members;
- Unsplash API retrieval, attribution, download-event tracking, and image-domain validation;
- English fictional member data and English Agent Profiles;
- a visible `Demo` badge and a durable data rule that prevents demo accounts from matching.

This phase does not build the candidate browser, Like UI, Match UI, messaging, or general Agent evaluation UI. Those consumers must use the boundaries defined here when implemented.

## Confirmed Cohort

The seed creates exactly 20 demo members:

| Cohort | Count |
| --- | ---: |
| Asian men | 5 |
| Asian women | 5 |
| Western men | 5 |
| Western women | 5 |

Additional rules:

- 10 men and 10 women in total;
- ages distributed from 25 through 55 rather than clustered in one decade;
- English names, occupations, public introductions, interests, and Agent Profile text;
- exactly two photos per member;
- the primary photo is a clear portrait;
- the secondary photo may show travel, a pet, coffee, work, design, fitness, or another plausible interest and does not need to show the same person;
- ethnicity is a curation cohort only and is not stored or displayed as a member attribute.

## Public and Private Profile Boundary

### Public member data

Candidate-facing surfaces may display only:

- display name;
- age derived from birth date;
- gender when the product surface calls for it;
- `basic_intro`;
- ordered photos and required Unsplash attribution;
- the `Demo` badge when `is_demo = 1`.

`basic_intro` is required and limited to 50 Unicode characters. The onboarding placeholder is exactly:

> 工作、居住地等短文字介紹（50字內）

Demo introductions are English. Real members may write in any language.

### Private Agent Profile

The Agent Profile is not included in normal candidate queries, HTML, client-side state, or public APIs. It may be retrieved only through the future Agent-evaluation path after the user explicitly asks their Agent to evaluate a candidate.

The private record contains:

- long-form `profile_text`;
- how many days the Agent has known the member;
- interaction density;
- generation time;
- expiry time;
- update time.

An Agent Profile expires three months after generation. The date check applies only to the member asking their Agent for an evaluation: that requester's own Profile must be current. The candidate must also have a Profile, but its date does not block the evaluation. Demo Profiles use the same stored timestamps, while another member's Agent checks only that the Demo Profile exists.

## Database Design

The production database is the independent `ainder` database.

### `users` additions

```sql
ALTER TABLE users
    ADD COLUMN basic_intro VARCHAR(50) NOT NULL DEFAULT '' AFTER gender,
    ADD COLUMN is_demo TINYINT(1) NOT NULL DEFAULT 0 AFTER basic_intro;
```

New real registrations require a nonblank `basic_intro`. Any pre-existing real member with an empty introduction must complete it before entering discovery.

Demo users retain the existing non-null identity columns with internal, non-login values:

- `google_sub`: deterministic `demo:<stable-id>`;
- `email`: deterministic address under `ainder.invalid`;
- `is_demo`: `1`.

No Google credential can map to a demo identity.

### `user_photos` additions

```sql
ALTER TABLE user_photos
    ADD COLUMN source_type ENUM('local', 'unsplash')
        NOT NULL DEFAULT 'local' AFTER sort_order,
    ADD COLUMN source_photo_id VARCHAR(64) NULL AFTER source_type,
    ADD COLUMN photographer_name VARCHAR(160) NULL AFTER source_photo_id,
    ADD COLUMN photographer_url VARCHAR(500) NULL AFTER photographer_name,
    ADD COLUMN source_page_url VARCHAR(500) NULL AFTER photographer_url;
```

For local uploads:

- `source_type = 'local'`;
- `file_path` is the existing `/ainder/uploads/...` path;
- all Unsplash metadata columns are null;
- existing validation, staging, finalization, and cleanup remain unchanged.

For Unsplash photos:

- `source_type = 'unsplash'`;
- `file_path` is the hotlinked URL returned under the API's `photo.urls` data;
- the hostname must be exactly `images.unsplash.com`;
- the photo ID, photographer name, photographer profile URL, and source photo page are required.

Future deletion logic deletes a filesystem object only when `source_type = 'local'`. Removing an Unsplash photo deletes only its database row.

### `agent_profiles`

```sql
CREATE TABLE agent_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    profile_text TEXT NOT NULL,
    agent_known_duration_days SMALLINT UNSIGNED NOT NULL,
    interaction_density ENUM('low', 'medium', 'high') NOT NULL,
    generated_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY agent_profiles_user_unique (user_id),
    CONSTRAINT agent_profiles_user_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
```

Each member has at most one current Agent Profile record. Demo known-duration values range roughly from 90 to 730 days, with `medium` and `high` density used most often.

## Unsplash Selection Flow

The Unsplash Access Key is stored only in ignored server-side `config.local.php`. It is never committed, embedded in HTML or JavaScript, printed by diagnostics, or included in seed output.

The seed preparation process is deliberately two-stage:

1. Query portrait candidates for Asian men, Asian women, Western men, and Western women with `content_filter=high` and portrait orientation.
2. Present candidate thumbnails in the accepted browser-based visual companion for manual curation.
3. Reject duplicates, group portraits, unclear subjects, branded imagery, unsuitable photos, and visibly poor crops.
4. Select five portraits for each cohort.
5. Retrieve and curate lifestyle images based on the fictional member's interests.
6. Build a frozen seed manifest containing the 20 members, 40 photo records, attribution data, and 20 Agent Profiles.
7. Trigger the API-provided download tracking endpoint once when a chosen image is assigned to the seed manifest.
8. Validate the complete manifest before any database mutation.

Search terms help produce balanced candidates but are not treated as authoritative identity metadata. Ainder does not display or store inferred ethnicity.

## Seed Execution

The production host has no assumed SSH or Composer workflow. The seed is therefore executed through a temporary token-protected PHP endpoint, following the existing migration pattern. The deployed endpoint is removed immediately after a successful or failed production attempt; the reviewed source remains in the repository.

Seed identities are deterministic, so rerunning the same manifest updates the same 20 members rather than adding duplicates.

Execution order:

1. Load and validate the frozen manifest.
2. Confirm exact cohort counts, age limits, names, public-introduction limits, photo counts, allowed URLs, attribution, and fresh Agent Profile fields.
3. Begin a database transaction.
4. Upsert the 20 demo users by deterministic `google_sub`.
5. Replace only those demo users' photo and Agent Profile rows.
6. Commit only after all 20 users, 40 photos, and 20 profiles are valid.
7. Roll back the entire database mutation on any error.
8. Return counts only; never return credentials or the full API response.
9. Remove the production seed endpoint.

A normal rerun preserves the selected Unsplash photo IDs. Replacing a person's portrait requires an explicit reviewed manifest change; image failure must not silently swap one human for another.

## Like and Match Invariant

Demo users may appear in discovery and may receive a Like. A Like to a demo user is retained like any other Like for product testing.

No current Match service exists in this phase. When it is implemented, its mandatory invariant is:

```text
if either participant is_demo, do not create a Match
```

This rule belongs in the server-side Match service, not only in the UI. Automated tests must cover both directions: a real user liking a demo and any attempted reciprocal path involving a demo.

## Error Handling

Preparation stops before database mutation when:

- Unsplash returns too few valid candidates;
- a required attribution field is missing;
- an image host is not `images.unsplash.com`;
- names, ages, cohort counts, photo counts, or public introductions fail validation;
- an Agent Profile is missing, stale, or malformed;
- the download tracking request fails for a newly assigned image.

Database execution is all-or-nothing. Existing demo records remain unchanged when a rerun fails validation or rolls back.

If a hotlinked image later stops loading, the affected demo is hidden from discovery until a reviewed replacement is applied. Ainder does not silently substitute a random image.

## Testing and Verification

Automated tests cover:

- `basic_intro` is required and accepts at most 50 Unicode characters;
- the onboarding page contains the exact placeholder;
- real uploads remain `source_type = 'local'` with null attribution;
- Unsplash rows require the exact image hostname and complete attribution;
- the manifest contains exactly 20 users, 40 photos, and 20 fresh Agent Profiles;
- each member has exactly two ordered photos;
- the four cohorts each contain five members;
- ages are within 25–55;
- seed execution is transactional and idempotent;
- the Access Key does not appear in tracked source or public responses;
- Agent Profiles are absent from normal candidate payloads;
- stale Agent Profiles cannot be used for new evaluations;
- the future Match service rejects any pair containing a demo member.

Production verification confirms:

- exactly 20 `users.is_demo = 1` rows;
- exactly 40 Unsplash photo rows belonging to those users;
- exactly 20 non-stale Agent Profile rows belonging to those users;
- every Demo row has a nonblank English `basic_intro` no longer than 50 characters;
- every Demo candidate displays its badge and attribution when the candidate UI exists;
- rerunning the identical manifest leaves all counts unchanged;
- the temporary seed endpoint returns 404 after removal.

## Acceptance Criteria

- Ainder contains 20 clearly labeled Demo members with the approved demographic balance and age range.
- Each Demo member has two curated Unsplash images, complete attribution, a public English introduction, and a fresh private English Agent Profile.
- Real-member onboarding requires the new 50-character public introduction and otherwise retains its local-photo workflow.
- Public candidate data never exposes Agent Profile text or hidden Agent-relationship metadata.
- Demo members can receive Likes but cannot participate in a Match.
- The seed is transactional, repeatable, secret-safe, and removable from the live host after execution.
