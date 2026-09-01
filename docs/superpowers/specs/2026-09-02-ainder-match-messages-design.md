# Ainder Match and Messages Design

## Scope

Implement the complete program path from reciprocal Like to a persistent Messages view, without creating an Eric-to-Chloe Like or a production Match during deployment.

## Match transition

Any reciprocal pair of Likes creates a Match, including a real member matching a seeded demo member. A successful Like response returns `matched` and `match_id`. When matched, the browser removes the pending Agent Like row and candidate card, then navigates to the Messages view for that Match. On reload, pending Likes are absent because the reciprocal pair is now represented under Messages.

## Messages list

The desktop `Messages` panel renders one card per Match. Each card contains:

- the other member's primary photo on the left;
- display name and smaller age at the top;
- a right-aligned close control;
- the signed-in member Agent's stored opinion about the other member below.

The opinion is line-clamped with an ellipsis. Clicking it opens a modal containing the complete opinion. Clicking any other non-control area of the card opens that Match conversation.

The close control asks for confirmation before calling the unmatch API. Unmatching deletes the Match and all Messages plus both directional Like records. It does not block either member, so they can appear and Like again later.

## Conversation view

The right stage switches from the candidate slider to a Message view containing a top back-arrow control, conversation heading, persistent message history, and a bottom composer. The composer has a text input, Send button, and emoji-list button. Emoji selection inserts into the text input and does not send automatically. The back arrow returns to the slider state.

Messages are limited to 2000 characters. Only members of a Match can read, send, or unmatch. The sender identity always comes from the authenticated session.

## Data model

Migration 006 creates `messages` with Match and sender foreign keys. Deleting a Match cascades its Messages. Application authorization verifies the sender belongs to the Match.

Match cards use the current member's outgoing Like `agent_opinion`, which is Eric Agent's opinion about Chloe from Eric's perspective.

## UI state

Sidebar and mobile tabs receive `data-tab` attributes. `Agent Likes` controls the pending inbox panel; `Messages` controls the Match card panel. A Match produced by WebMCP opens `/ainder/app/?view=messages&match=<id>` after the tool result is returned. No Like or Match is seeded by this implementation.

## Out of scope

Read receipts, typing indicators, attachments, message editing/deletion, blocking, push notifications, and an actual Eric-to-Chloe Match fixture are excluded.
