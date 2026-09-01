<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require_once $root.'/web/lib/unsplash.php';

$options = getopt('', ['scope:', 'limit::']);
$scope = (string) ($options['scope'] ?? 'all');
$limit = isset($options['limit']) ? (int) $options['limit'] : PHP_INT_MAX;

if (!in_array($scope, ['portrait', 'lifestyle', 'all'], true) || $limit < 1) {
    fwrite(STDERR, "Usage: php tools/track_unsplash_selections.php --scope=<portrait|lifestyle|all> [--limit=<count>]\n");
    exit(2);
}

$localPath = $root.'/web/config.local.php';
$local = is_file($localPath) ? require $localPath : [];
$accessKey = (string) ($local['unsplash_access_key'] ?? '');
if ($accessKey === '') {
    fwrite(STDERR, "Unsplash configuration unavailable.\n");
    exit(2);
}

$loadJson = static function (string $path): array {
    if (!is_file($path)) {
        throw new RuntimeException('Selection data unavailable.');
    }

    $data = json_decode(
        (string) file_get_contents($path),
        true,
        512,
        JSON_THROW_ON_ERROR
    );
    if (!is_array($data)) {
        throw new RuntimeException('Selection data invalid.');
    }

    return $data;
};

$candidateData = $loadJson($root.'/var/demo-candidates.json');
$portraitSelections = $loadJson($root.'/var/demo-selected-portraits.json');
$lifestyleSelections = $loadJson($root.'/var/demo-selected-lifestyles.json');
$portraitOverrides = $loadJson($root.'/var/demo-portrait-overrides.json');

$photoIndex = [];
foreach (['portraits', 'lifestyle'] as $group) {
    foreach (($candidateData[$group] ?? []) as $photos) {
        foreach ($photos as $photo) {
            $photoId = (string) ($photo['source_photo_id'] ?? '');
            if ($photoId !== '') {
                $photoIndex[$photoId] = $photo;
            }
        }
    }
}
foreach ($portraitOverrides as $photoId => $photo) {
    if (is_array($photo)) {
        $photoIndex[(string) $photoId] = $photo;
    }
}

$portraitIds = [];
foreach ($portraitSelections as $ids) {
    foreach ($ids as $photoId) {
        $portraitIds[] = (string) $photoId;
    }
}
$lifestyleIds = array_map(
    static fn (array $selection): string => (string) $selection['source_photo_id'],
    array_values($lifestyleSelections)
);

$selectedIds = match ($scope) {
    'portrait' => $portraitIds,
    'lifestyle' => $lifestyleIds,
    default => array_merge($portraitIds, $lifestyleIds),
};

$ledgerPath = $root.'/web/seeds/demo_photo_tracking.php';
$trackedIds = is_file($ledgerPath) ? require $ledgerPath : [];
if (!is_array($trackedIds)) {
    throw new RuntimeException('Tracking ledger invalid.');
}
$trackedIds = array_values(array_unique(array_map('strval', $trackedIds)));
$trackedLookup = array_fill_keys($trackedIds, true);

$writeLedger = static function (array $ids) use ($ledgerPath): void {
    $directory = dirname($ledgerPath);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Tracking ledger directory unavailable.');
    }

    $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn "
        .var_export(array_values($ids), true).";\n";
    $temporary = tempnam($directory, '.demo-photo-tracking-');

    if ($temporary === false
        || file_put_contents($temporary, $content, LOCK_EX) !== strlen($content)
        || !rename($temporary, $ledgerPath)) {
        if (is_string($temporary) && is_file($temporary)) {
            @unlink($temporary);
        }
        throw new RuntimeException('Tracking ledger could not be written.');
    }
};

$completed = 0;

try {
    foreach ($selectedIds as $photoId) {
        if (isset($trackedLookup[$photoId])) {
            continue;
        }
        if ($completed >= $limit) {
            break;
        }

        $photo = $photoIndex[$photoId] ?? null;
        $downloadLocation = is_array($photo)
            ? (string) ($photo['download_location'] ?? '')
            : '';
        if ($downloadLocation === '') {
            throw new RuntimeException("Selected photo metadata missing: {$photoId}");
        }

        ainder_unsplash_track_download($accessKey, $downloadLocation);
        $trackedIds[] = $photoId;
        $trackedLookup[$photoId] = true;
        $writeLedger($trackedIds);
        $completed++;
        echo "tracked {$photoId}\n";
    }

    echo "completed={$completed} total=".count($trackedIds)."\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage()."\n");
    fwrite(STDERR, "completed={$completed} total=".count($trackedIds)."\n");
    exit(1);
}
