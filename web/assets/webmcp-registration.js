import { postJson } from './webmcp-common.js';

const context = document.modelContext;

if (typeof context?.registerTool === 'function') {
  await context.registerTool({
    name: 'start_agent_registration',
    description: 'Start Ainder registration only after the user confirms all personal data, Agent Profile text, the designated main photo, and supporting-photo order.',
    inputSchema: {
      type: 'object',
      properties: {
        idempotency_key: {
          type: 'string',
          pattern: '^[a-f0-9]{64}$',
        },
      },
      required: ['idempotency_key'],
      additionalProperties: false,
    },
    execute: (input) => postJson(
      '/ainder/api/agent-registration/start.php',
      input,
    ),
  });

  await context.registerTool({
    name: 'prepare_photo_upload',
    description: 'Create one signed upload URL for a confirmed Ainder registration photo. Before calling, the user must designate sort order 1 as the main photo; confirm it is one real human with a visible face, and reject clearly sexual, violent, illegal, or otherwise unsuitable supplied images.',
    inputSchema: {
      type: 'object',
      properties: {
        registration_id: {
          type: 'string',
          pattern: '^[a-f0-9]{32}$',
        },
        filename: { type: 'string', minLength: 1, maxLength: 255 },
        mime_type: {
          enum: ['image/jpeg', 'image/png', 'image/webp'],
        },
        byte_size: {
          type: 'integer',
          minimum: 1,
          maximum: 10485760,
        },
        sort_order: { type: 'integer', minimum: 1, maximum: 6 },
      },
      required: [
        'registration_id',
        'filename',
        'mime_type',
        'byte_size',
        'sort_order',
      ],
      additionalProperties: false,
    },
    execute: (input) => postJson(
      '/ainder/api/agent-registration/prepare-photo.php',
      input,
    ),
  });

  await context.registerTool({
    name: 'submit_agent_registration',
    description: 'Create the Ainder member, ordered photos, and Agent Profile after every signed upload is ready and the user has confirmed the complete draft.',
    inputSchema: {
      type: 'object',
      properties: {
        registration_id: {
          type: 'string',
          pattern: '^[a-f0-9]{32}$',
        },
        idempotency_key: {
          type: 'string',
          pattern: '^[a-f0-9]{64}$',
        },
        display_name: { type: 'string', minLength: 1, maxLength: 120 },
        birth_date: { type: 'string', format: 'date' },
        gender: { enum: ['male', 'female'] },
        upload_ids: {
          type: 'array',
          minItems: 2,
          maxItems: 6,
          uniqueItems: true,
          items: { type: 'string', pattern: '^[a-f0-9]{32}$' },
        },
        profile_text: { type: 'string', minLength: 1, maxLength: 4000 },
        agent_known_duration_days: {
          type: 'integer',
          minimum: 0,
          maximum: 65535,
        },
        interaction_density: { enum: ['low', 'medium', 'high'] },
      },
      required: [
        'registration_id',
        'idempotency_key',
        'display_name',
        'birth_date',
        'gender',
        'upload_ids',
        'profile_text',
        'agent_known_duration_days',
        'interaction_density',
      ],
      additionalProperties: false,
    },
    execute: async (input) => {
      const result = await postJson(
        '/ainder/api/agent-registration/submit.php',
        input,
      );
      if (result.ok && result.redirect_url) {
        window.setTimeout(() => window.location.assign(result.redirect_url), 50);
      }
      return result;
    },
  });
}
