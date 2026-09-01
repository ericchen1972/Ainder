<?php

declare(strict_types=1);

$failures = 0;

function test(string $name, callable $callback): void
{
    global $failures;

    try {
        $callback();
        echo "PASS {$name}\n";
    } catch (Throwable $error) {
        $failures++;
        echo "FAIL {$name}: {$error->getMessage()}\n";
    }
}

function expect_same(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            'Expected '.var_export($expected, true).', got '.var_export($actual, true)
        );
    }
}

require __DIR__.'/auth_test.php';
require __DIR__.'/registration_test.php';

if (is_file(__DIR__.'/page_contract_test.php')) {
    require __DIR__.'/page_contract_test.php';
}

exit($failures === 0 ? 0 : 1);
