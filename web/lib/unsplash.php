<?php

declare(strict_types=1);

function ainder_unsplash_request(string $accessKey, string $url): array
{
    if ($accessKey === '') {
        throw new RuntimeException('Unsplash configuration unavailable.');
    }

    $parsed = filter_var($url, FILTER_VALIDATE_URL);
    $scheme = is_string($parsed) ? parse_url($parsed, PHP_URL_SCHEME) : null;
    $host = is_string($parsed) ? parse_url($parsed, PHP_URL_HOST) : null;
    if ($scheme !== 'https' || $host !== 'api.unsplash.com') {
        throw new RuntimeException('Unsplash endpoint is not allowed.');
    }

    $handle = curl_init($url);
    if ($handle === false) {
        throw new RuntimeException('Unsplash request unavailable.');
    }

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Accept-Version: v1',
            'Authorization: Client-ID '.$accessKey,
        ],
        CURLOPT_USERAGENT => 'Ainder/1.0',
    ]);

    try {
        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new RuntimeException('Unsplash request failed.');
        }

        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Unsplash response was invalid.');
        }

        return $decoded;
    } catch (JsonException) {
        throw new RuntimeException('Unsplash response was invalid.');
    } finally {
        curl_close($handle);
    }
}

function ainder_unsplash_search(
    string $accessKey,
    string $query,
    string $orientation,
    int $perPage = 30
): array {
    if (!in_array($orientation, ['landscape', 'portrait', 'squarish'], true)) {
        throw new InvalidArgumentException('Invalid Unsplash orientation.');
    }
    if ($perPage < 1 || $perPage > 30) {
        throw new InvalidArgumentException('Invalid Unsplash result count.');
    }

    $url = 'https://api.unsplash.com/search/photos?'.http_build_query([
        'query' => $query,
        'orientation' => $orientation,
        'per_page' => $perPage,
        'content_filter' => 'high',
    ], '', '&', PHP_QUERY_RFC3986);
    $response = ainder_unsplash_request($accessKey, $url);
    $results = is_array($response['results'] ?? null)
        ? $response['results']
        : [];

    return array_values(array_map(
        static fn (array $photo): array => ainder_unsplash_normalize_photo($photo),
        array_filter($results, 'is_array')
    ));
}

function ainder_unsplash_normalize_photo(array $photo): array
{
    return [
        'source_type' => 'unsplash',
        'file_path' => (string) ($photo['urls']['regular'] ?? ''),
        'source_photo_id' => (string) ($photo['id'] ?? ''),
        'photographer_name' => (string) ($photo['user']['name'] ?? ''),
        'photographer_url' => ainder_unsplash_add_referral(
            (string) ($photo['user']['links']['html'] ?? '')
        ),
        'source_page_url' => ainder_unsplash_add_referral(
            (string) ($photo['links']['html'] ?? '')
        ),
        'download_location' => (string) ($photo['links']['download_location'] ?? ''),
    ];
}

function ainder_unsplash_add_referral(string $url): string
{
    $separator = str_contains($url, '?') ? '&' : '?';

    return $url.$separator.'utm_source=ainder&utm_medium=referral';
}

function ainder_unsplash_download_location_is_allowed(string $url): bool
{
    $parsed = filter_var($url, FILTER_VALIDATE_URL);
    if (!is_string($parsed)) {
        return false;
    }

    $path = (string) parse_url($parsed, PHP_URL_PATH);

    return parse_url($parsed, PHP_URL_SCHEME) === 'https'
        && parse_url($parsed, PHP_URL_HOST) === 'api.unsplash.com'
        && preg_match('#/download$#', $path) === 1;
}

function ainder_unsplash_track_download(
    string $accessKey,
    string $downloadLocation
): void {
    if (!ainder_unsplash_download_location_is_allowed($downloadLocation)) {
        throw new InvalidArgumentException('Invalid Unsplash download endpoint.');
    }

    ainder_unsplash_request($accessKey, $downloadLocation);
}
