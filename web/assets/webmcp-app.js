import { currentCandidateId, postJson } from './webmcp-common.js';

const context = document.modelContext;

function browseController() {
  return globalThis.ainderBrowseController ?? null;
}

function candidateRequired() {
  return {
    ok: false,
    error: {
      code: 'CANDIDATE_REQUIRED',
      message: 'No current candidate.',
    },
  };
}

if (typeof context?.registerTool === 'function') {
  await context.registerTool({
    name: 'upsert_my_agent_profile',
    description: 'Create or refresh the signed-in Ainder member private Agent Profile. Do not show profile_text by default. Before calling this write tool, ask the user only to confirm that their personal information is correct.',
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
    name: 'get_current_candidate',
    description: 'Read the candidate currently visible on the Ainder browse card, including public name, age, and photo position. This is read-only.',
    inputSchema: {
      type: 'object',
      properties: {},
      additionalProperties: false,
    },
    execute: () => {
      const candidate = browseController()?.getCurrentCandidate();
      return candidate ? { ok: true, candidate } : candidateRequired();
    },
  });

  await context.registerTool({
    name: 'browse_candidates',
    description: 'Move the visible Ainder card to the next or previous candidate. This only changes the current page and never sends a Like.',
    inputSchema: {
      type: 'object',
      properties: {
        direction: { enum: ['next', 'previous'] },
      },
      required: ['direction'],
      additionalProperties: false,
    },
    execute: (input) => {
      const candidate = browseController()?.browseCandidates(input.direction);
      return candidate ? { ok: true, candidate } : candidateRequired();
    },
  });

  await context.registerTool({
    name: 'change_candidate_photo',
    description: 'Change the visible photo of the current Ainder candidate without changing candidates.',
    inputSchema: {
      type: 'object',
      properties: {
        direction: { enum: ['next', 'previous'] },
      },
      required: ['direction'],
      additionalProperties: false,
    },
    execute: (input) => {
      const controller = browseController();
      const current = controller?.getCurrentCandidate();
      if (!current) return candidateRequired();
      if (current.photo_count < 2) {
        return {
          ok: false,
          error: {
            code: 'PHOTO_NAVIGATION_UNAVAILABLE',
            message: 'The current candidate has fewer than two available photos.',
          },
        };
      }
      return {
        ok: true,
        candidate: controller.changeCandidatePhoto(input.direction),
      };
    },
  });

  await context.registerTool({
    name: 'evaluate_current_candidate',
    description: 'Get the current Ainder candidate Agent Profile so you can answer the user question about this person. If Profile state is missing or expired, return the structured error and do not evaluate.',
    inputSchema: {
      type: 'object',
      properties: {},
      additionalProperties: false,
    },
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
    description: 'Send an Ainder Like to the current candidate only after the user asks for it. Requires a fresh evaluation token and a non-empty Agent opinion. Reuse the opinion already given to the user about this candidate. If no opinion has been given, evaluate the candidate and generate a concise opinion before calling this tool. Never submit an empty opinion.',
    inputSchema: {
      type: 'object',
      properties: {
        evaluation_token: {
          type: 'string',
          pattern: '^[a-f0-9]{64}$',
        },
        opinion: {
          type: 'string',
          minLength: 1,
          maxLength: 1000,
        },
      },
      required: ['evaluation_token', 'opinion'],
      additionalProperties: false,
    },
    execute: async (input) => {
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
      const result = await postJson('/ainder/api/candidates/like.php', {
        candidate_id: candidateId,
        evaluation_token: input.evaluation_token,
        opinion: input.opinion,
      });
      if (result.ok) {
        browseController()?.removeCandidate(candidateId);
      }
      return result;
    },
  });
}
