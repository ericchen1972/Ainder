# Ainder Swipe Browser Design

**Date:** 2026-09-01  
**Status:** Approved

## Objective

Replace the authenticated Ainder placeholder with a Tinder-inspired candidate browser while preserving Ainder's product boundary: the website is a simple visual surface, swiping only browses candidates, and Like remains an Agent action.

## Scope

This increment delivers the authenticated browse screen, candidate loading, responsive layout, photo navigation, pointer/touch swiping, keyboard navigation, and empty/error states.

It does not deliver Like storage or APIs, Pass storage, Matches, messages, Agent Profile creation, Agent evaluation, WebMCP tools, or a persistent record of browsing history. The `Agent Likes` and `Messages` sidebar tabs are presentational empty states in this increment.

## Reference Analysis

The chosen direction is layout A from the visual comparison: a complete desktop sidebar and a focused central portrait card. On mobile, the sidebar is removed and the card becomes the primary surface.

Tinder was used only as a structural reference. The supplied authenticated desktop screenshot established the app-specific proportions and hierarchy. Dembrandt was run for desktop and mobile, but the extraction was redirected to Tinder's public landing page because no authenticated cookie was available. Its reliable findings were therefore limited to the broader design language: dark neutral surfaces, high-contrast text, an 8px-based spacing rhythm, rounded controls, strong accent color, and responsive changes around tablet/mobile widths. The Ainder implementation retains Ainder's existing burgundy, pink, and dark visual identity instead of copying Tinder's branding or components.

## User Experience

### Desktop

The screen has two regions:

1. A fixed-width left sidebar containing the member avatar, Ainder wordmark, `Agent Likes` tab, `Messages` tab, and an explanatory empty state that says the Agent handles Likes.
2. A flexible main stage containing one centered portrait card with previous and next controls.

The sidebar does not contain a Like button, compatibility score, Agent Profile text, or an action that can create a Like.

### Mobile

The sidebar is removed. A compact top bar contains the Ainder wordmark and member entry point. The portrait card fills the remaining viewport while respecting safe areas and preventing horizontal overflow.

### Candidate Card

The public card contains only:

- all 2–6 registered photos;
- display name;
- calculated age;
- the public `basic_intro` field of at most 50 characters;
- visible Unsplash photographer/source attribution where applicable.

The card must not expose Agent Profile text, Agent-known duration, interaction density, Profile timestamps, a compatibility percentage, or any Like control.

### Photo Navigation

Clicking or tapping the left and right photo regions changes the current member's photo. Photo progress segments at the top of the card indicate the active photo. Photo navigation never changes the candidate.

### Candidate Navigation

Candidate navigation has no Like or Dislike meaning:

- drag/swipe left: next candidate;
- drag/swipe right: previous candidate;
- keyboard left arrow: next candidate;
- keyboard right arrow: previous candidate;
- on-screen left control: next candidate;
- on-screen right control: previous candidate.

The candidate list is circular. Moving next from the final candidate returns to the first; moving previous from the first returns to the final candidate.

A drag must cross a deliberate threshold before navigation occurs. A shorter drag returns the card to its resting position so that taps and minor pointer movement do not change the candidate.

## Candidate Eligibility and Ordering

The authenticated member's gender determines the only visible candidate gender:

- a male member sees female members;
- a female member sees male members.

Candidates must be active and have the opposite binary gender. No separate self-exclusion condition is needed because the member cannot satisfy the opposite-gender filter. The first implementation includes both real and Demo members when they satisfy these rules. Demo members can be browsed and can later be Liked by an Agent, but the existing rule that a Demo member cannot form a Match remains unchanged.

The server loads the eligible list and shuffles it once per page load. The order remains stable for the lifetime of that loaded page so circular navigation is understandable.

Browsing does not require a fresh Agent Profile and does not inspect a candidate's Profile date. The existing requester-only Profile freshness rule applies later when the Agent evaluates or Likes a candidate.

## Public Data Boundary

The candidate repository returns an explicit public allowlist:

- `id`;
- `display_name`;
- `age` derived from `birth_date`;
- `basic_intro`;
- `photos`, each containing the display URL, sort order, source type, photographer name, photographer URL, and source page URL;
- `is_demo` only when required internally for future Match enforcement.

Private Profile fields are never selected for this page and never embedded in HTML or JavaScript.

The currently visible card exposes a machine-readable candidate ID in the DOM and updates it whenever navigation completes. This preserves the future contract that `get_current_candidate` and `like_candidate` refer to the same person the user sees, without adding either Agent tool in this increment.

## Components

### Candidate Repository

An isolated PHP module owns the opposite-gender query, active-status filter, public allowlist construction, age calculation, photo grouping, and one-time shuffle. The page does not contain inline SQL.

### Authenticated Page

`web/app/index.php` retains the existing member-session guard, loads the authenticated member and public candidates, and renders semantic HTML for the sidebar, stage, card, navigation controls, attribution, and empty state.

### Browse Styles

A dedicated browse stylesheet owns layout and responsive rules. It does not modify landing or onboarding selectors. The principal responsive transition occurs at the existing mobile boundary near 720px, with intermediate safeguards for short desktop viewports.

### Browse Controller

A dedicated JavaScript controller owns candidate index, photo index, circular movement, pointer/touch gestures, keyboard input, animation cleanup, image fallback, accessible status announcements, and synchronization of the current candidate ID.

## Error and Empty States

- No eligible candidates: show a centered message that no members are currently available; keep the member/sidebar navigation usable.
- One photo fails: move to the next available photo and keep attribution synchronized.
- All photos fail: show a dark branded image placeholder while retaining the member's public text.
- Candidate payload is malformed: omit the malformed candidate rather than leaking an exception into the page.
- Database failure: return the existing generic service-unavailable response without database details.
- JavaScript unavailable: render the first candidate and functional photo/candidate links or buttons through a conservative server-rendered fallback where practical; no Like action appears.

## Accessibility

- Navigation controls have explicit Traditional Chinese accessible labels.
- Keyboard direction matches gesture direction.
- Candidate changes and empty states are announced through an `aria-live` region.
- Focus indicators remain visible on all controls.
- Motion respects `prefers-reduced-motion`.
- Text gradients maintain readable contrast over bright images.

## Testing

Automated checks cover:

- male members receive only female candidates;
- female members receive only male candidates;
- disabled members are excluded;
- only public allowlisted fields reach the page;
- private Agent Profile fields never appear in browse source;
- the authenticated route guard remains active;
- no Like control or Like request exists in the browse page;
- left/next and right/previous movement wrap at both ends;
- photo navigation does not change candidate index;
- the empty state renders without an exception;
- dedicated, versioned browse assets are referenced;
- responsive rules prevent horizontal overflow.

Live verification uses a real registered member and confirms:

- a male member can browse all active female members, including the ten seeded female Demo members;
- a female member can browse all active male members, including the ten seeded male Demo members;
- the final candidate wraps to the first;
- swiping never creates a Like or Match;
- desktop and mobile proportions match the approved layout A direction;
- temporary diagnostics, if used, are removed after verification.

## Deployment

No schema migration is required. Deploy the candidate repository, authenticated page, dedicated stylesheet, and dedicated controller. Preserve the production `config.local.php` and uploads directory. Verify live asset hashes or file versions, authenticated redirects, desktop/mobile rendering, candidate counts, loop behavior, and the absence of public diagnostic endpoints.
