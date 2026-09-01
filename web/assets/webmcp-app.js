import { currentCandidateId, postJson } from './webmcp-common.js';

const context = document.modelContext;

if (typeof context?.registerTool === 'function') {
  await context.registerTool({
    name: 'upsert_my_agent_profile',
    description: 'Create or refresh the signed-in Ainder member Agent Profile. Show the proposed Profile text to the user and obtain confirmation before calling this write tool.',
    inputSchema: {
      type: 'object',
      properties: {
        profile_text: {
          type: 'string',
          minLength: 1,
          maxLength: 4000,
        },
        agent_known_duration_days: {
          type: 'integer',
          minimum: 0,
          maximum: 65535,
        },
        interaction_density: { enum: ['low', 'medium', 'high'] },
      },
      required: [
        'profile_text',
        'agent_known_duration_days',
        'interaction_density',
      ],
      additionalProperties: false,
    },
    execute: (input) => postJson('/ainder/api/profile/upsert.php', input),
  });

  await context.registerTool({
    name: 'evaluate_current_candidate',
    description: 'Get the current Ainder candidate Agent Profile so you can answer the user question about this person. If Profile state is missing or expired, return the structured error and do not evaluate.',
    inputSchema: {
      type: 'object',
      properties: {},
      additionalProperties: false,
    },
    annotations: { readOnlyHint: true },
    execute: () => {
      const candidateId = currentCandidateId();
      if (!candidateId) {
        return {
          ok: false,
          error: {
            code: 'CANDIDATE_REQUIRED',
            message: 'No current candidate.',
          },
        };
      }
      return postJson('/ainder/api/candidates/evaluate.php', {
        candidate_id: candidateId,
      });
    },
  });

  await context.registerTool({
    name: 'send_like_to_current_candidate',
    description: 'Send an Ainder Like to the current candidate only after the user asks for it. Requires a fresh evaluation token and repeats all Profile checks.',
    inputSchema: {
      type: 'object',
      properties: {
        evaluation_token: {
          type: 'string',
          pattern: '^[a-f0-9]{64}$',
        },
      },
      required: ['evaluation_token'],
      additionalProperties: false,
    },
    execute: (input) => {
      const candidateId = currentCandidateId();
      if (!candidateId) {
        return {
          ok: false,
          error: {
            code: 'CANDIDATE_REQUIRED',
            message: 'No current candidate.',
          },
        };
      }
      return postJson('/ainder/api/candidates/like.php', {
        candidate_id: candidateId,
        evaluation_token: input.evaluation_token,
      });
    },
  });
}
