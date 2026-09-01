<?php

declare(strict_types=1);

function ainder_google_csrf_is_valid(string $cookieToken, string $postToken): bool
{
    return $cookieToken !== ''
        && $postToken !== ''
        && hash_equals($cookieToken, $postToken);
}

/**
 * @return array{google_sub: string, email: string, display_name: string, avatar_url: string}|null
 */
function ainder_normalize_google_identity(array $payload): ?array
{
    $googleSub = trim((string) ($payload['sub'] ?? ''));
    $email = trim((string) ($payload['email'] ?? ''));
    $emailVerified = filter_var(
        $payload['email_verified'] ?? false,
        FILTER_VALIDATE_BOOL
    );

    if ($googleSub === ''
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !$emailVerified) {
        return null;
    }

    $displayName = trim((string) ($payload['name'] ?? ''));
    if ($displayName === '') {
        $emailName = strstr($email, '@', true);
        $displayName = is_string($emailName) && $emailName !== ''
            ? $emailName
            : 'Ainder user';
    }

    $avatarUrl = (string) ($payload['picture'] ?? '');

    return [
        'google_sub' => $googleSub,
        'email' => $email,
        'display_name' => mb_substr($displayName, 0, 120),
        'avatar_url' => filter_var($avatarUrl, FILTER_VALIDATE_URL)
            ? $avatarUrl
            : '',
    ];
}

function ainder_login_destination(?array $member): string
{
    return ($member['status'] ?? null) === 'active'
        ? '/ainder/app/'
        : '/ainder/profile/';
}

function ainder_home_destination(array $session, int $now): ?string
{
    if (isset($session['ainder_member_id'])) {
        return '/ainder/app/';
    }

    return ainder_pending_identity_is_valid($session, $now)
        ? '/ainder/profile/'
        : null;
}

function ainder_pending_identity_is_valid(array $session, int $now): bool
{
    return isset(
        $session['ainder_pending_identity']['google_sub'],
        $session['ainder_pending_expires_at']
    ) && (int) $session['ainder_pending_expires_at'] > $now;
}
