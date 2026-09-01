# Ainder Profile Onboarding Design

**Date:** 2026-09-01  
**Status:** Approved for implementation planning

## Objective

Enable Google sign-in with the Ainder OAuth Client ID and provide a dark, Tinder-inspired onboarding screen for new Google users. The screen leads with Agent-assisted onboarding, reveals a manual form only on request, and creates an Ainder member only after all required personal information and photos pass validation.

## Product Boundary

- Ainder remains independent from SweetyGame and all other products.
- The independent `ainder` database is the only database used for Ainder members and photos.
- Existing Ainder members go directly to the application placeholder after Google sign-in.
- New Google identities remain only in the Ainder session until registration succeeds.
- The website does not implement Agent-driven profile submission in this phase. It presents the Agent-first message and leaves the future Agent route to reuse the same registration service.

## Google Sign-In

Use this user-provided public Google Web Client ID through untracked production configuration:

```text
315346868518-hu4t4do82agusauffh5tdva68a0tbjge.apps.googleusercontent.com
```

The Client ID must not be hard-coded into tracked application source. The production `web/config.local.php` remains excluded from Git.

The Google Cloud client must authorize:

- JavaScript origin: `https://sweety.tw`
- Login URI: `https://sweety.tw/ainder/auth/google.php`

Google Identity Services posts the credential to Ainder. The PHP backend verifies the Google double-submit CSRF token and ID token before trusting `sub`, email, display name, or avatar data.

## Authentication Routing

1. The user signs in with Google from `/ainder/`.
2. The server verifies the Google credential.
3. The server looks up `ainder.users.google_sub`.
4. An existing active member receives an Ainder member session and is redirected to `/ainder/app/`.
5. A non-member receives a 30-minute pending identity session and is redirected to `/ainder/profile/`.
6. No member row or photo row is created during sign-in for a non-member.

The pending identity session holds only verified Google fields needed for registration. Expired pending identity redirects to the landing page for a new sign-in.

## Initial Profile Screen

The profile page continues the Ainder dark visual language.

- Place the white Ainder logo at the upper left.
- Center the message `你可以讓 Agent 為你填寫個人資訊`.
- Place a pink Ainder `手動填寫` button below the message.
- Do not place a separate website button for Agent submission in this phase.
- Keep the manual form absent from the visible layout until the user selects `手動填寫`.

The button exposes its state with `aria-expanded`. When activated, the form expands in place beneath the message and the page moves smoothly to the start of the revealed form.

## Manual Registration Fields

The form contains only:

- **Name:** required; prefilled from the verified Google name; editable; maximum 120 characters.
- **Email:** required; sourced from verified Google identity; displayed read-only; never trusted from a browser-submitted value.
- **Birth date:** required; the user must be at least 18 years old on the registration date.
- **Gender:** required; exactly `male` or `female`, presented as `男性` and `女性`.
- **Profile photos:** required; minimum 2 and maximum 6.

The following Tinder fields are intentionally absent from the rendered form and submission payload:

- `有興趣的對象`
- `我想尋找`
- `是否在個人資料顯示性別`

They are not added to the initial database schema. A future approved feature can introduce them explicitly.

## Responsive Layout

### Desktop

- Keep the Agent message and manual action centered before expansion.
- After expansion, use a centered two-column form surface.
- The left column contains name, email, birth date, and gender.
- The right column contains a six-slot, three-by-two photo grid.

### Mobile

- Use a single-column flow.
- Keep the logo and introductory hierarchy compact at the top.
- Place personal fields first and the six-slot photo grid below.
- Preserve touch-sized controls and prevent horizontal overflow.

The form uses the selected **A: in-place expansion** approach. It does not use a permanent split landing layout or a multi-step wizard.

## Photo Interaction and Validation

- Accept JPG, PNG, and WebP.
- Limit each source file to 10 MB.
- Require 2–6 photos to register.
- Show a local preview immediately after selection.
- Allow each selected photo to be removed and replaced.
- Do not implement drag-and-drop reordering in this phase.
- Save photos in selection order using sort positions 1–6.
- Generate server-side random file names; never use a submitted original name as the stored file name.
- Reject a file when its detected content type does not match an allowed image type.

The registration action remains visibly unavailable until required client-side conditions are met. Server validation is always authoritative.

## Data Model

### `users`

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    google_sub VARCHAR(255) NOT NULL,
    email VARCHAR(320) NOT NULL,
    display_name VARCHAR(120) NOT NULL,
    birth_date DATE NOT NULL,
    gender ENUM('male', 'female') NOT NULL,
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

The initial migration has not yet been run in production, so implementation updates the initial `users` migration rather than adding a production alteration.

### `user_photos`

```sql
CREATE TABLE user_photos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    sort_order TINYINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY user_photos_user_sort_unique (user_id, sort_order),
    CONSTRAINT user_photos_user_foreign
        FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Registration Transaction

The registration endpoint accepts POST only and requires:

- a valid pending Google identity session;
- an Ainder form CSRF token;
- a valid name;
- a birth date proving the user is at least 18 years old;
- a gender of exactly `male` or `female`;
- 2–6 valid image uploads.

The email and Google subject are read from the verified pending session, not from editable form data.

Registration follows this sequence:

1. Validate session, CSRF, form values, photo count, file sizes, and detected image types.
2. Stage image uploads under random temporary names.
3. Begin a database transaction.
4. Insert the `users` row.
5. Move staged images into the member photo directory using random final names.
6. Insert ordered `user_photos` rows.
7. Commit the transaction.
8. Replace pending identity state with the authenticated member ID.
9. Redirect to `/ainder/app/`.

If any database or file step fails, roll back the database transaction and remove every staged or moved file from this attempt. No partial member or partial photo set may remain.

A duplicate `google_sub` race is resolved by re-querying the existing member and creating its member session rather than creating a second row.

## Error Handling

- Field errors appear inside the expanded form beside the relevant input.
- Underage users cannot submit and receive a clear birth-date error.
- Fewer than 2 or more than 6 photos cannot submit.
- Unsupported, oversized, or invalid image data receives a photo-specific error.
- Expired pending sessions redirect to `/ainder/`.
- Invalid Google credentials return to `/ainder/?login=failed`.
- Database and storage failures return a generic message without credentials, filesystem paths, tokens, or SQL details.
- Submitted values remain visible after a recoverable validation error, except file inputs that the browser cannot safely restore.

## Testing and Verification

Automated tests cover:

- Google CSRF and ID-token routing boundaries;
- no member persistence before registration;
- existing active member routing;
- pending session expiry;
- exact 18th-birthday acceptance and one-day-under rejection;
- name and gender validation;
- photo counts of 1, 2, 6, and 7;
- allowed and rejected image content types;
- photo size enforcement;
- registration rollback and file cleanup on failure;
- absence of the three excluded Tinder fields from rendered HTML and request handling;
- secret-free tracked source.

Live verification covers:

- the user-provided Google Client ID renders an operational sign-in button;
- a new Google identity reaches `/ainder/profile/` without a database row;
- manual form expansion and error display;
- successful registration creates one member and 2–6 ordered photo rows;
- a new member reaches `/ainder/app/`;
- the same Google identity subsequently goes directly to `/ainder/app/`;
- desktop and mobile layout, focus behavior, touch targets, image previews, and overflow;
- production uses only the independent `ainder` database.

## Acceptance Criteria

- Google sign-in uses the supplied Client ID from untracked deployment configuration.
- Non-members are not persisted before a valid registration submission.
- The initial profile screen shows only the white logo, Agent-first message, and manual-fill action.
- Manual fields expand in place and include only name, read-only Google email, birth date, binary gender, and 2–6 photos.
- Only users aged 18 or older with 2–6 valid photos can register.
- Successful submission atomically creates the Ainder member and ordered photo rows, authenticates the member, and redirects to the app placeholder.
- Failure leaves no partial member or photo data.
- Desktop and mobile layouts follow the approved A design and remain usable without overflow.
