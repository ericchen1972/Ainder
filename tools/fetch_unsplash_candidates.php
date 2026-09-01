<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require_once $root.'/web/lib/demo.php';
require_once $root.'/web/lib/unsplash.php';

$options = getopt('', ['output:']);
$output = trim((string) ($options['output'] ?? ''));
if ($output === '') {
    fwrite(STDERR, "Usage: php tools/fetch_unsplash_candidates.php --output=<path>\n");
    exit(2);
}

$outputDirectory = dirname($output);
if (!is_dir($outputDirectory) || !is_writable($outputDirectory)) {
    fwrite(STDERR, "Output directory is unavailable.\n");
    exit(2);
}

$localPath = $root.'/web/config.local.php';
$local = is_file($localPath) ? require $localPath : [];
$accessKey = (string) ($local['unsplash_access_key'] ?? '');
if ($accessKey === '') {
    fwrite(STDERR, "Unsplash configuration unavailable.\n");
    exit(2);
}

$portraitQueries = [
    'asian_male' => 'Asian man portrait lifestyle',
    'asian_female' => 'Asian woman portrait lifestyle',
    'western_male' => 'European man portrait lifestyle',
    'western_female' => 'European woman portrait lifestyle',
];

$lifestyleQueries = [
    'travel' => 'travel destination quiet landscape',
    'coffee' => 'coffee cafe table lifestyle',
    'pet' => 'pet dog cat home lifestyle',
    'workspace' => 'creative workspace desk lifestyle',
    'design' => 'interior architecture design detail',
    'fitness' => 'fitness running hiking lifestyle',
];

$seenPhotoIds = [];
$deduplicate = static function (array $photos) use (&$seenPhotoIds): array {
    $unique = [];

    foreach ($photos as $photo) {
        $photoId = (string) ($photo['source_photo_id'] ?? '');
        if ($photoId === ''
            || isset($seenPhotoIds[$photoId])
            || ainder_validate_demo_photo($photo) !== []) {
            continue;
        }

        $seenPhotoIds[$photoId] = true;
        $unique[] = $photo;
    }

    return $unique;
};

try {
    $portraits = [];
    foreach ($portraitQueries as $cohort => $query) {
        $portraits[$cohort] = $deduplicate(
            ainder_unsplash_search($accessKey, $query, 'portrait', 30)
        );
    }

    $lifestyle = [];
    foreach ($lifestyleQueries as $category => $query) {
        $lifestyle[$category] = $deduplicate(
            ainder_unsplash_search($accessKey, $query, 'landscape', 30)
        );
    }

    $payload = [
        'meta' => [
            'content_filter' => 'high',
            'portrait_orientation' => 'portrait',
            'lifestyle_orientation' => 'landscape',
            'fetched_at' => gmdate(DATE_ATOM),
        ],
        'portraits' => $portraits,
        'lifestyle' => $lifestyle,
    ];
    $json = json_encode(
        $payload,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    )."\n";

    $temporary = tempnam($outputDirectory, '.ainder-unsplash-');
    if ($temporary === false
        || file_put_contents($temporary, $json, LOCK_EX) !== strlen($json)
        || !rename($temporary, $output)) {
        if (is_string($temporary) && is_file($temporary)) {
            @unlink($temporary);
        }
        throw new RuntimeException('Candidate output could not be written.');
    }

    foreach ($portraits as $cohort => $photos) {
        echo "portrait {$cohort}: ".count($photos)."\n";
    }
    foreach ($lifestyle as $category => $photos) {
        echo "lifestyle {$category}: ".count($photos)."\n";
    }
    echo "output: {$output}\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    exit(1);
}
