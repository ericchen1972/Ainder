<?php

declare(strict_types=1);

function ainder_verify_google_token(
    string $credential,
    string $clientId
): ?array {
    if ($credential === '' || $clientId === '') {
        return null;
    }

    require_once dirname(__DIR__, 2).'/vendor/autoload.php';

    $client = new Google\Client(['client_id' => $clientId]);
    $payload = $client->verifyIdToken($credential);

    return is_array($payload) ? $payload : null;
}
