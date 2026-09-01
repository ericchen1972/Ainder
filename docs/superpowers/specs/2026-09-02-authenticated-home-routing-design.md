# Authenticated Home Routing Design

## Goal

When a visitor returns to `/ainder/`, route them according to the Ainder session state already established by Google authentication:

- An active member goes to `/ainder/app/`.
- A Google-authenticated visitor whose personal profile is not complete goes to `/ainder/profile/` while the pending identity is still valid.
- A visitor with neither state stays on the landing page and sees Google sign-in.
- An active member can explicitly sign out from the main page and returns to the landing page.

## Current Behavior and Root Cause

The Google callback stores an active member as `ainder_member_id`. For a new or incomplete registration, it stores `ainder_pending_identity` with a 30-minute expiry and redirects to `/ainder/profile/`.

The landing page currently checks only `ainder_member_id`. It does not check the valid pending identity, so returning from the profile page to `/ainder/` incorrectly renders the Google sign-in button.

## Design

Keep the existing callback and session model. At the start of the landing-page request:

1. Start the Ainder session.
2. If `ainder_member_id` exists, redirect to `/ainder/app/`.
3. Otherwise, if `ainder_pending_identity_is_valid($_SESSION, time())` is true, redirect to `/ainder/profile/`.
4. Otherwise, render the landing page normally.

The member check remains first so a complete authenticated member always wins if inconsistent session data contains both states. An expired pending identity does not count as authenticated registration state and therefore leaves the visitor on the landing page.

### Sign-out

Expose a visible `登出` button in the authenticated browse page:

- On desktop, place it in the sidebar member bar alongside the avatar and Ainder logo.
- On mobile, place it in the top member bar alongside the member avatar.

The button submits a `POST` form to `/ainder/logout.php` with the existing Ainder form CSRF token. The endpoint accepts sign-out only when the request is `POST` and the token is valid. A successful request clears the Ainder session and redirects to `/ainder/`. An invalid request leaves the session intact and redirects to `/ainder/app/`.

The control is rendered as a text button rather than an avatar menu so it stays discoverable without adding JavaScript or hidden interaction state.

## Scope

This change does not alter Google token verification, database records, profile fields, the 30-minute pending timeout, or registration submission behavior. It makes the landing page honor the session state already created by the callback and adds an explicit, CSRF-protected way to clear that state from the authenticated main page.

## Testing

Add regression coverage proving that:

- redirects an active member to `/ainder/app/`;
- redirects a valid pending identity to `/ainder/profile/`;
- uses the existing pending-identity validity helper, including its expiry behavior;
- the landing page continues to render Google sign-in when no authenticated Ainder session state exists;
- renders a sign-out form on both desktop and mobile main-page navigation;
- sends sign-out through `POST` with an Ainder CSRF token;
- clears the session and returns to `/ainder/` for a valid request;
- preserves the session and returns to `/ainder/app/` for an invalid request.

After implementation, run the full PHP test suite and verify the live flow in the signed-in in-app browser by returning to `/ainder/` from the profile page, then signing out from the main page and confirming that the landing page is shown.
