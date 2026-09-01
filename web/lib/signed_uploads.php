<?php

declare(strict_types=1);

function ainder_agent_identifier(): string
{
    return bin2hex(random_bytes(16));
}

function ainder_sign_upload(string $uploadId, int $expires, string $key): string
{
    if ($key === '') {
        throw new RuntimeException('Upload signing is not configured.');
    }

    return hash_hmac('sha256', $uploadId.'.'.$expires, $key);
}

function ainder_verify_upload_signature(
    string $uploadId,
    int $expires,
    string $signature,
    string $key,
    int $now
): bool {
    return $uploadId !== ''
        && $signature !== ''
        && $expires >= $now
        && $key !== ''
        && hash_equals(ainder_sign_upload($uploadId, $expires, $key), $signature);
}

function ainder_signed_upload_url(
    array $config,
    string $uploadId,
    int $expires
): string {
    $signature = ainder_sign_upload(
        $uploadId,
        $expires,
        (string) ($config['upload_signing_key'] ?? '')
    );

    return (string) $config['public_base_url']
        .'/api/agent-registration/upload.php?id='.rawurlencode($uploadId)
        .'&expires='.$expires
        .'&signature='.rawurlencode($signature);
}
