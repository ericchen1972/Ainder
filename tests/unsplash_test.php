<?php

declare(strict_types=1);

$unsplashLibrary = dirname(__DIR__).'/web/lib/unsplash.php';
if (is_file($unsplashLibrary)) {
    require_once $unsplashLibrary;
}

test('Unsplash API photos normalize to hotlinks and attributed source data', function (): void {
    $photo = ainder_unsplash_normalize_photo([
        'id' => 'abc123',
        'urls' => ['regular' => 'https://images.unsplash.com/photo-abc?fit=crop&w=1080'],
        'links' => [
            'html' => 'https://unsplash.com/photos/abc123',
            'download_location' => 'https://api.unsplash.com/photos/abc123/download',
        ],
        'user' => [
            'name' => 'Jamie Example',
            'links' => ['html' => 'https://unsplash.com/@jamie'],
        ],
    ]);

    expect_same('abc123', $photo['source_photo_id']);
    expect_same('unsplash', $photo['source_type']);
    expect_same('Jamie Example', $photo['photographer_name']);
    expect_same(true, str_contains($photo['photographer_url'], 'utm_source=ainder'));
    expect_same(true, str_contains($photo['source_page_url'], 'utm_source=ainder'));
    expect_same(
        'https://api.unsplash.com/photos/abc123/download',
        $photo['download_location']
    );
});

test('Unsplash client rejects non-Unsplash download endpoints', function (): void {
    expect_same(false, ainder_unsplash_download_location_is_allowed(
        'https://example.com/photos/abc/download'
    ));
    expect_same(false, ainder_unsplash_download_location_is_allowed(
        'http://api.unsplash.com/photos/abc/download'
    ));
    expect_same(true, ainder_unsplash_download_location_is_allowed(
        'https://api.unsplash.com/photos/abc/download'
    ));
});
