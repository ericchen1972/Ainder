# Ainder Agent Likes Inbox Implementation Plan

1. Add contract and pure validation tests for migration 005, required opinions, candidate exclusion, inbox queries, remove API, seed protection, WebMCP schema, and list/controller markup.
2. Add migration 005 and Agent opinion validation/storage.
3. Update the candidate query to exclude outgoing Likes and add a pending-inbound-Likes query.
4. Add the pending Like removal API and protected demo inbound Like seed endpoint.
5. Render the desktop Agent Likes list and add controller methods for selecting/removing candidates and inbox rows.
6. Make successful WebMCP Like remove the candidate card and require/reuse the Agent opinion.
7. Run all PHP/JavaScript tests and syntax checks.
8. Commit and push `main`, deploy, run migration 005, seed Chloe Park to Eric Chen, and verify production DB effects through the authenticated page and WebMCP.
