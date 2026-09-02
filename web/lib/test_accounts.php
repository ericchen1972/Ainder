<?php

declare(strict_types=1);

function ainder_test_account_scenarios(): array
{
    return [
        'grace' => [
            'label' => 'Grace Liu',
            'member_google_sub' => 'demo:010',
            'sender_google_sub' => 'demo:001',
            'agent_opinion' => "Grace's warmth, creativity, and respect for emotional boundaries look promising. Ethan's steady listening may suit her need for gentleness, while both should be careful not to postpone difficult conversations.",
        ],
        'john' => [
            'label' => 'John Carter',
            'member_google_sub' => 'demo:011',
            'sender_google_sub' => 'demo:020',
            'agent_opinion' => "John's reliability, humor, and active lifestyle look compatible with Evelyn's practical and health-conscious approach. They may connect through shared routines, as long as solutions do not replace emotional listening.",
        ],
    ];
}

function ainder_test_account_scenario(string $slug): ?array
{
    $scenarios = ainder_test_account_scenarios();

    return $scenarios[$slug] ?? null;
}
