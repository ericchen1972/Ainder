<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/profile_editor.php';

function expect_invalid_profile_change(callable $callback): void
{
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException('Expected InvalidArgumentException.');
}

test('profile editor accepts a trimmed name and exact replacement slots', function (): void {
    expect_same([], ainder_validate_profile_name(' Eric Chen '));
    expect_same(
        [1 => '/new-main.webp', 2 => '/new-second.webp'],
        ainder_validate_profile_photo_changes(
            2,
            [1, 2],
            ['/new-main.webp', '/new-second.webp']
        )
    );
});

test('profile editor permits only contiguous additions through slot six', function (): void {
    expect_same(
        [3 => '/third.webp', 4 => '/fourth.webp'],
        ainder_validate_profile_photo_changes(
            2,
            [3, 4],
            ['/third.webp', '/fourth.webp']
        )
    );
    expect_invalid_profile_change(
        fn () => ainder_validate_profile_photo_changes(2, [4], ['/gap.webp'])
    );
    expect_invalid_profile_change(
        fn () => ainder_validate_profile_photo_changes(6, [7], ['/seven.webp'])
    );
});

test('profile editor rejects deletion-shaped or duplicate slot requests', function (): void {
    expect_invalid_profile_change(
        fn () => ainder_validate_profile_photo_changes(
            2,
            [1, 1],
            ['/a.webp', '/b.webp']
        )
    );
    expect_invalid_profile_change(
        fn () => ainder_validate_profile_photo_changes(2, [0], ['/a.webp'])
    );
});

test('profile editor rejects an empty or overlong name', function (): void {
    expect_same(
        ['display_name' => 'Name must contain 1–120 characters.'],
        ainder_validate_profile_name('   ')
    );
    expect_same(
        ['display_name' => 'Name must contain 1–120 characters.'],
        ainder_validate_profile_name(str_repeat('a', 121))
    );
});
