<?php

declare(strict_types=1);

function ainder_verify_google_token(
    string $credential,
    string $clientId
): ?array {
    if ($credential === '' || $clientId === '') {
        return null;
    }

    return ainder_verify_google_token_with(
        $credential,
        $clientId,
        static function (string $token, string $expectedClientId): mixed {
            require_once dirname(__DIR__, 2).'/vendor/autoload.php';

            $client = new Google\Client(['client_id' => $expectedClientId]);

            return $client->verifyIdToken($token);
        }
    );
}

function ainder_verify_google_token_with(
    string $credential,
    string $clientId,
    callable $verifier
): ?array {
    try {
        $payload = $verifier($credential, $clientId);
    } catch (Throwable) {
        return null;
    }

    return is_array($payload) ? $payload : null;
}
