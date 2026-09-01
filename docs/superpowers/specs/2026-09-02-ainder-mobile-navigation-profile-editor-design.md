# Ainder Mobile Navigation and Profile Editor Design

## Scope

This change refines the existing browse, Likes, and Messages experience and adds a signed-in member profile editor. It does not add or change any WebMCP tools.

## Reference findings

The supplied Tinder desktop and iPhone screenshots establish the interaction reference:

- desktop keeps activity navigation beside the main card or conversation;
- mobile gives the card most of the viewport and moves primary destinations to a fixed bottom navigation;
- mobile destinations are represented by icons rather than text;
- profile editing starts from the signed-in member avatar.

Dembrandt was run against Tinder for desktop and mobile. The authenticated application view redirected to the public Tinder page, so its extracted marketing-site tokens are not used as evidence for the application layout. The supplied screenshots remain the authoritative reference. Ainder keeps its existing dark surfaces, pink accent, type, spacing, and card styling rather than copying Tinder branding.

## Desktop UI

- Rename every visible `Agent Likes` navigation label to `Likes`.
- Keep the existing two-tab desktop sidebar structure.
- Reduce the Match Card close control so it does not compete with the member name.
- Increase the Agent opinion clamp from two lines to three lines while retaining ellipsis overflow.
- Preserve the existing full-opinion Modal and unmatch confirmation behavior.

## Mobile navigation

At widths up to the existing 720px breakpoint:

- remove the current text tabs from the top of the page;
- add a fixed bottom navigation with three equal destinations: Slide, Likes, and Messages;
- render icons only, with accessible labels and selected-state semantics;
- use Font Awesome Free shapes for `fire-flame-curved`, `heart`, and `comment-dots`;
- embed only the three required SVG shapes locally, using `currentColor`, a normalized 24×24 icon box, and no Font Awesome CDN or full CSS dependency;
- show the selected destination in Ainder pink with a small top indicator;
- Slide shows the candidate card area, Likes shows the incoming Likes list, and Messages shows the Match Card list;
- opening a conversation keeps Messages selected; its existing back arrow returns to Slide;
- retain the compact top bar with Ainder identity, the member avatar, and Logout.

Desktop remains sidebar-based and does not display the bottom navigation.

## Profile Modal

The signed-in member avatar becomes a button on desktop and mobile. Activating it opens an `Edit profile` Modal.

The Modal contains:

- the current display name;
- the member's existing ordered photos;
- a `Main` marker on the first photo;
- a single `+` control after the last photo when fewer than six photos exist;
- a save action and a close action.

Photo rules:

- existing photos cannot be deleted;
- selecting an existing photo replaces that exact sort position;
- selecting `+` appends the next photo in sequence;
- each successful addition reveals the next `+`, up to six total photos;
- the first position remains the main photo;
- replacement and addition use the existing centered 9:16 client crop, 720×1280 WebP output, and 0.84 quality;
- unchanged photos remain untouched.

On desktop the editor is a centered Modal. On mobile it uses a near-full-screen surface with internal scrolling so six portrait thumbnails and the save action remain usable.

## Server and data flow

No schema migration is required. The existing `users` and ordered `user_photos` records remain authoritative.

Add a CSRF-protected authenticated multipart endpoint for profile updates. It will:

1. require the signed-in active member;
2. validate a trimmed display name of 1–120 characters;
3. accept only replacement slots that belong to the member and additions that follow the existing contiguous order;
4. reject more than six total photos and reject requests that imply deletion or reordered gaps;
5. validate every uploaded file as the canonical processed WebP;
6. stage and finalize new files using the existing image pipeline;
7. update the name, replace selected `user_photos` rows, and insert appended rows in one database transaction;
8. keep old files until the transaction commits, then remove only superseded local files;
9. clean new staged/final files if validation or persistence fails;
10. return the updated name, ordered photo paths, and main avatar path as JSON.

The browser updates the header avatar and Modal previews from the successful response. A reload must produce the same state from the database.

## Error handling

- Client processing errors stay inside the Modal and do not close it.
- Save is disabled while photos are being processed or submitted.
- Invalid name, file type, slot, count, CSRF, or session state returns a structured error.
- A failed update leaves the original name and photos intact.
- The Modal remains open after a failed save and shows a concise English error.

## Accessibility

- Icon-only navigation buttons have English `aria-label` values and `aria-selected` state.
- The avatar button is labelled `Edit profile`.
- Photo controls identify whether they replace an existing position or add the next photo.
- Keyboard users can open and close the Modal, replace photos, add photos, and save.
- Focus returns to the avatar button after closing the Modal.

## Testing and verification

- Add unit coverage for profile update validation and ordered photo mutations.
- Add page-contract coverage for `Likes`, the three mobile icon destinations, smaller close control, three-line opinion clamp, Profile Modal, and absence of new WebMCP tools.
- Add browser-side tests for incremental photo addition, exact-slot replacement, six-photo maximum, no deletion control, and normalized client processing.
- Verify PHP and JavaScript syntax and run the complete existing test suite.
- Deploy to production while preserving `config.local.php`, `.user.ini`, and uploads.
- Verify desktop and a phone-sized viewport in the signed-in production session, including persistence after reload and no horizontal overflow.

