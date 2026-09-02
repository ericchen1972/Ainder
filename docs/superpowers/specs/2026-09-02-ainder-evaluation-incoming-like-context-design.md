# Ainder Candidate Evaluation Incoming Like Context

## Goal

When the signed-in member asks the AI about the current candidate, the existing
`evaluate_current_candidate` WebMCP tool must tell the AI when that candidate
has already sent a Like to the signed-in member. If the incoming Like contains
the sender Agent's opinion, the evaluation response must include it with clear
source semantics.

## Approaches considered

1. **Recommended: optional structured context on the existing evaluation.**
   Add `incoming_like_context` only when a candidate-to-requester Like exists.
   This preserves the current tool flow and keeps the Profile separate from the
   Like-specific context.
2. Add the opinion directly to `candidate.profile_text`. This was rejected
   because it would mix two sources and could make the AI treat Chloe's Agent
   opinion about Eric as part of Chloe's own Profile.
3. Add a separate WebMCP tool for incoming Like context. This was rejected
   because the AI would need another tool call for information required by the
   same candidate-evaluation question.

## Response contract

The existing evaluation response remains unchanged for candidates who have not
sent a Like to the requester.

When the current candidate has sent a Like to the requester, the response adds:

```json
{
  "incoming_like_context": {
    "has_incoming_like": true,
    "agent_opinion": "The candidate Agent's existing opinion about the signed-in member."
  }
}
```

`incoming_like_context` is top-level evaluation context, not part of
`candidate`. The `agent_opinion` is the opinion stored on the candidate-to-user
Like. Its meaning is explicitly: the candidate's Agent opinion about the
signed-in member. It is not an opinion about the candidate and must not be
merged into `profile_text`.

The context is returned only for a valid Like whose sender is the evaluated
candidate and whose recipient is the signed-in member. The query must not expose
Likes involving any other users.

## Data flow

1. WebMCP resolves the current candidate ID as it does today.
2. The evaluation endpoint validates both members and both Agent Profiles.
3. The server creates the existing short-lived evaluation token.
4. The server looks up the candidate-to-requester Like and its non-empty
   `agent_opinion`.
5. If found, the response includes `incoming_like_context`; otherwise the field
   is omitted.
6. The WebMCP tool description tells the AI to use this context to recognize
   that the candidate already Likes its user while preserving the opinion's
   source meaning.

No data is written beyond the existing evaluation-token record. No Like or Match
state changes as a result of evaluation.

## Error handling

Existing authentication, CSRF, candidate validation, missing Profile, and
expired Profile behavior remain unchanged. Failure to find an incoming Like is
normal and does not produce an error. An empty or missing Like opinion does not
produce partial context; the field is omitted.

## Testing and verification

- Add a failing contract/unit test proving the evaluation implementation queries
  the exact candidate-to-requester Like direction and exposes
  `incoming_like_context`.
- Add a failing WebMCP contract test for the updated tool description.
- Verify candidates without an incoming Like keep the existing response shape.
- Run the complete PHP test suite and PHP/JavaScript syntax checks.
- Deploy to production and use the signed-in In Browser session to evaluate
  Chloe. Confirm the response says Chloe has already sent Eric a Like and
  includes the stored Chloe-Agent opinion about Eric.
- Do not create a reciprocal Like or Match during this verification.

## Out of scope

- Changing the Likes or Messages UI.
- Creating, deleting, or accepting a Like.
- Showing another member's private Profile outside the existing evaluation flow.
- Adding a new WebMCP tool.
