# Ainder Card Drag and Photo Controls Design

## Goal

Make candidate browsing behave like a card interaction instead of native image dragging. Candidate navigation and photo navigation must remain separate:

- dragging the card changes candidates;
- compact controls inside the image change photos for the current candidate;
- the age is visually secondary to the name.

This change does not add Like, Dislike, Super Like, match, or other Tinder actions.

## Reference and Evidence

The approved visual reference is the signed-in Tinder desktop screenshot supplied by the user. It shows a portrait card with photo navigation contained within the image rather than large candidate-navigation buttons outside the card.

Dembrandt attempted desktop and mobile extraction from `https://tinder.com/app/recs`, but the unauthenticated extraction redirected to Tinder's public homepage. Public-site tokens are therefore not treated as evidence for the signed-in card dimensions. The supplied screenshot and the current rendered Ainder page are authoritative for this focused change.

The current Ainder implementation binds pointer tracking to `.candidate-stack`, but candidate images do not disable native browser image dragging. The image also retains default selection and drag behavior. Native image dragging can therefore take ownership of the gesture before the stack receives the pointer sequence needed to change candidates. The existing photo buttons stop `click` propagation but do not stop `pointerdown`, so their pointer sequence can also begin a candidate drag.

## Approved Interaction Model

### Candidate navigation

The full current card is the candidate-drag surface except for interactive photo controls.

- Drag left past the existing 64px threshold: show the next candidate.
- Drag right past the threshold: show the previous candidate.
- Release below the threshold or cancel the pointer: return the current card to its resting position.
- Preserve circular candidate wrapping.
- Preserve the existing reduced-motion behavior.
- Preserve keyboard candidate navigation: Left Arrow shows the next candidate and Right Arrow shows the previous candidate.

Candidate dragging remains browse-only. It never sends a Like or records a dating decision.

### Native drag prevention

Every candidate `<img>` is rendered with `draggable="false"`. CSS also disables browser image dragging and selection with `user-select: none`, `-webkit-user-select: none`, and `-webkit-user-drag: none` on candidate media.

The JavaScript prevents any residual `dragstart` event on candidate images. Pointer capture remains on the card stack so a drag continues when the pointer moves beyond the image or card boundary.

### Photo navigation

Remove the two large `.candidate-control` buttons outside the card. They no longer change candidates.

Each candidate with two or more photos renders two visible photo controls inside the card:

- left control: previous photo;
- right control: next photo.

The selected visual is option C from the approved companion: a narrow translucent pill centered vertically near each inner card edge.

- Visible pill: 26px wide by 54px high.
- Interactive button target: at least 44px wide and 54px high.
- Horizontal inset: 10px from the card edge.
- Surface: translucent dark background with a restrained blur and white chevron.
- Focus state: retain the existing pink high-contrast outline.

The controls continue to wrap through the current candidate's photos and update the top photo segments. They never change `data-current-candidate-id`.

Photo controls stop `pointerdown` and `click` propagation. Pressing or clicking a photo control must never begin candidate dragging. Candidates with fewer than two usable photos do not show these controls.

## Typography

The candidate name remains the primary line at its current responsive size. The age moves to its own explicit scale instead of inheriting the heading size:

- desktop size: 18px;
- mobile size: 17px;
- normal-to-medium weight;
- baseline aligned with the name;
- small horizontal gap after the name.

The age remains readable over the existing bottom gradient but must no longer compete with the name.

## Guidance Text

Replace the desktop hint with wording that explains the separated controls:

`拖曳卡片換人 · 卡片內箭頭換照片`

The hint remains hidden in the current mobile layout. Accessible labels continue to identify the inside controls as previous and next photo. The live candidate status remains unchanged.

## Responsive Behavior

The same interaction contract applies to desktop and mobile.

- Desktop keeps the centered portrait card and sidebar.
- Mobile keeps the edge-to-edge card below the mobile bar.
- The narrow pill visuals remain visible on both layouts.
- Touch scrolling remains vertically available through `touch-action: pan-y`.
- Horizontal dragging is owned by the candidate card, not the image.
- The 44px minimum interactive width is preserved even when the visible pill is narrower.

## Implementation Boundaries

Expected changes are limited to:

- `web/app/index.php` for photo-control markup, `draggable="false"`, external-control removal, and updated hint text;
- `web/assets/browse.css` for the selected pill controls, native-drag prevention, and age scale;
- `web/assets/browse.js` for pointer event separation and residual `dragstart` prevention;
- the existing browse model and contract tests where behavior needs a pure assertion.

Candidate queries, WebMCP tools, Agent Profile behavior, Like behavior, registration, member sessions, sidebar content, and stored photo data remain unchanged.

## Error and Edge Handling

- A photo load failure retains the existing fallback to another usable photo.
- When only one usable photo remains after failures, both photo controls become unavailable or hidden.
- A pointer cancellation restores the card without candidate movement.
- Clicking a photo control during or after a candidate drag does not cause a second navigation.
- A drag completed over a nested image still resolves through the card pointer state.
- Reduced-motion users receive the state change without entrance animation.

## Verification

Automated tests must cover:

- candidate drag direction and threshold remain left-next/right-previous;
- candidate image markup includes `draggable="false"`;
- CSS disables native media dragging and selection;
- external candidate controls are absent;
- inside photo controls are visible only for multi-photo candidates;
- photo-control pointer and click events do not reach candidate drag handling;
- photo navigation wraps without changing candidates;
- the age has an independent smaller responsive size;
- no visible or scripted Like control is introduced.

Live verification uses the authenticated in-app browser on desktop and a mobile viewport. It must demonstrate:

1. dragging from the visible image changes candidates rather than dragging a ghost image;
2. left drag selects the next candidate and right drag selects the previous candidate;
3. each pill changes only the current candidate's photo and segments;
4. single-photo candidates have no pill controls;
5. the age is visibly smaller than the name;
6. no overflow or loss of vertical touch scrolling occurs.

## Acceptance Criteria

- Native image dragging cannot replace the Ainder candidate gesture.
- Drag left means next candidate; drag right means previous candidate.
- Compact option-C pill controls live inside the image and change photos only.
- The large outside candidate buttons are removed.
- Age is visually secondary and 18px on desktop.
- Keyboard and reduced-motion accessibility remain functional.
- Desktop and mobile behavior are verified in the authenticated production page after deployment.
