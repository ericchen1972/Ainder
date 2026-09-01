import { postJson } from './webmcp-common.js';

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
}
