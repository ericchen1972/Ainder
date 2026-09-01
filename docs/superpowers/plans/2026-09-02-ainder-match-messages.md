# Ainder Match and Messages Implementation Plan

1. Add failing contracts for migration 006, Match list queries, message validation and APIs, unmatch deletion, Messages cards, opinion modal, conversation composer, emoji insertion, tab switching, and WebMCP Match navigation.
2. Create migration 006 and `web/lib/matches.php` for authorized Match summaries and message history.
3. Add send/list Message APIs and a confirmed unmatch API that deletes both Likes.
4. Return `match_id` for reciprocal Likes and permit the Chloe demo fixture to form a Match only when Eric actually Likes her.
5. Render Agent Likes and Messages as separate sidebar panels plus the right-side conversation view.
6. Implement tabs, cards, modal, back navigation, persistent sending, emoji insertion, and unmatch confirmation in JavaScript.
7. Update WebMCP Like success to enter the server-rendered Messages route when `matched` is true.
8. Run full PHP/JS/syntax tests, commit and push `main`.
9. Deploy migration 006 before the updated app, then verify the non-Match production state without creating Eric-to-Chloe reciprocal data.
