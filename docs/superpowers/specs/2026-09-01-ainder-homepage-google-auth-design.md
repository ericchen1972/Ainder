# Ainder Homepage and Google Authentication Design

**Date:** 2026-09-01  
**Status:** Approved for implementation planning

## Objective

Deliver the first public Ainder web slice at `https://sweety.tw/ainder/`:

- a dark, full-screen landing page using the prepared desktop and mobile hero images;
- the white Ainder logo at the upper left;
- a Google sign-in control at the upper right;
- an empty onboarding page for authenticated Google users who are not yet Ainder members;
- an empty main page for users who already exist in the Ainder member table.

Ainder is an independent product. It shares the Sweety host and database credentials, but it must not share a database, member table, application session, or member identity with SweetyGame or any other product.

## Scope

### Included

- Responsive landing page and asset selection.
- Google identity verification.
- Ainder-only PHP session handling.
- Creation of an independent `ainder` MySQL database and its initial `users` table.
- Member/non-member routing after Google sign-in.
- Guarded placeholder pages for onboarding and the main application.
- Deployment to the existing `/ainder` directory and live verification.

### Excluded

- Personal-information form fields and submission.
- Creating a member from the placeholder onboarding page.
- Profile generation, profile expiry, matching, Like, Match, messaging, and unmatch behavior.
- Reusing or synchronizing SweetyGame members.
- Requesting access to Google services such as Drive, Calendar, or Gmail.

## Architecture Decision

Use Google Identity Services for the sign-in button and verify the returned ID token on the PHP server with the Google PHP SDK already installed on the host.

This is preferred over a traditional authorization-code flow because this phase needs identity only. It avoids storing a Google client secret in Ainder and does not request access to additional Google APIs. It is also preferred over an authentication broker because an external broker would introduce an unnecessary dependency on another product's login system.

The Google OAuth client must authorize the web origin `https://sweety.tw` and the Ainder login endpoint. An existing client ID may be reused only if its configured origin is compatible; otherwise a dedicated Ainder web client must be configured.

## Page and Route Structure

### `/ainder/`

Public landing page.

- Uses `100dvh` with a dark fallback background.
- Loads the desktop hero image by default and the mobile hero image at the mobile breakpoint through `<picture>`.
- Uses full-bleed `object-fit: cover` presentation with a restrained dark overlay so the corner controls remain legible.
- Places the white Ainder logo at the upper left.
- Places the official Google sign-in control at the upper right.
- Has no navigation bar or additional content.
- An authenticated Ainder member who returns to this route may be sent directly to `/ainder/app/`.

### `/ainder/auth/google.php`

Accepts the Google Identity Services response.

- Accepts POST only.
- Verifies the Google double-submit CSRF token when Google provides `g_csrf_token`.
- Verifies the Google ID token signature, issuer, audience, expiry, and email verification state.
- Extracts only the identity fields needed for onboarding: `sub`, email, display name, and avatar URL.
- Never trusts identity values submitted independently by the browser.

### `/ainder/profile/`

Placeholder onboarding page.

- Requires a valid pending Google identity in the Ainder session.
- Does not insert or update a member record.
- Shows only a minimal dark placeholder shell for this phase.
- If the pending identity is absent or expired, redirects to `/ainder/`.

### `/ainder/app/`

Placeholder member main page.

- Requires an authenticated Ainder member session.
- Shows only a minimal dark placeholder shell for this phase.
- If the member session is absent, redirects to `/ainder/`.

### `/ainder/logout.php`

Clears only the Ainder session and returns to `/ainder/`.

## Authentication and Routing Flow

1. The visitor signs in with Google from `/ainder/`.
2. The server verifies the Google response before starting any Ainder identity state.
3. The server queries `ainder.users` by the immutable Google subject identifier, `google_sub`.
4. If an active Ainder member exists:
   - regenerate the PHP session identifier;
   - store the Ainder member ID in the session;
   - update `last_login_at`;
   - redirect to `/ainder/app/`.
5. If no Ainder member exists:
   - do not write anything to the database;
   - regenerate the PHP session identifier;
   - store the verified Google identity as pending onboarding state;
   - set a 30-minute pending-state expiry;
   - redirect to `/ainder/profile/`.
6. A future personal-information submission will be the only operation allowed to create the member row.

Email is descriptive account information and is not the login key because a Google account's email can change. `google_sub` is the stable unique identity key.

## Session Boundary

Ainder uses its own session name and cookie path, scoped to `/ainder/`, so it does not collide with sessions from other applications on `sweety.tw`.

Production cookies use:

- `Secure`;
- `HttpOnly`;
- `SameSite=Lax`;
- cookie path `/ainder/`.

Session identifiers are regenerated after a verified Google login. Pending onboarding identity expires after 30 minutes and contains only the verified Google fields needed by the later registration form.

## Database Design

Create a separate database:

```sql
CREATE DATABASE ainder
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
```

The application reuses the Sweety MySQL host, username, and password through the existing server-side configuration, but explicitly connects to the `ainder` database. Credentials are not copied into browser assets or committed to the Ainder repository.

Initial table:

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    google_sub VARCHAR(255) NOT NULL,
    email VARCHAR(320) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    avatar_url TEXT NULL,
    status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY users_google_sub_unique (google_sub),
    KEY users_email_index (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

There is deliberately no draft-member table. Until personal information is submitted in a later phase, a new Google user exists only in the short-lived session and is not an Ainder member.

## Configuration Boundary

- Reuse the host's installed Composer dependencies and Google PHP SDK.
- Read database connection values only from the existing server-side Sweety configuration in config-only mode, then select the independent `ainder` database.
- Store the Google Client ID in server-side Ainder configuration or an environment variable.
- Do not commit production credentials.
- Fail with a non-sensitive error page if required configuration is missing.

## Error Handling

- Invalid, expired, or incorrectly targeted Google credentials return to the landing page with a generic login error.
- Database failures show a generic temporary-unavailable response and do not reveal connection details.
- Disabled members are not granted an authenticated member session.
- Authentication endpoints do not create users as a side effect of retries.
- Logs must not contain Google ID tokens, cookies, database credentials, or complete session payloads.

## Deployment

The working implementation remains in the Ainder repository. Deployment copies only the required runtime files and prepared assets into the mounted `sweety.tw/ainder` directory or transfers the same files through the existing FTP route.

Database migration is executed once against the production MySQL server. If the reused database account lacks `CREATE DATABASE` permission, deployment stops and reports that exact prerequisite instead of falling back to the Sweety database.

## Verification

Before declaring completion:

- verify the landing page at desktop and mobile viewport sizes;
- verify the correct hero asset, image crop, logo, and Google button placement;
- verify the page has no horizontal overflow;
- verify unauthenticated access guards on `/profile/` and `/app/`;
- verify an invalid Google credential cannot establish a session;
- verify a valid Google identity with no `users` row reaches `/profile/` without creating a row;
- verify the member-routing branch with an isolated test fixture or automated repository stub;
- verify Ainder connects only to the `ainder` database;
- verify the public `/ainder/` route no longer returns the current empty-directory 403 response;
- verify no secrets are present in the deployed HTML, JavaScript, repository diff, or diagnostic output.

## Acceptance Criteria

- `https://sweety.tw/ainder/` displays the approved responsive Ainder landing page.
- Google identities are verified server-side before any session is trusted.
- A non-member is redirected to the onboarding placeholder and no member record is created.
- An existing active Ainder member is redirected to the main placeholder.
- Ainder data is stored only in the independent `ainder` database.
- Direct access to protected placeholder pages is rejected appropriately.
- No production credential is added to the Ainder repository or exposed to the browser.
