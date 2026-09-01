<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/registration.php';

test('exact eighteenth birthday is accepted', function (): void {
    expect_same([], ainder_validate_registration_fields([
        'display_name' => 'Eva',
        'birth_date' => '2008-09-01',
        'gender' => 'female',
    ], new DateTimeImmutable('2026-09-01')));
});

test('one day under eighteen is rejected', function (): void {
    $errors = ainder_validate_registration_fields([
        'display_name' => 'Eva',
        'birth_date' => '2008-09-02',
        'gender' => 'female',
    ], new DateTimeImmutable('2026-09-01'));

    expect_same('你必須年滿 18 歲。', $errors['birth_date']);
});

test('name and binary gender are required', function (): void {
    $errors = ainder_validate_registration_fields([
        'display_name' => '',
        'birth_date' => '1990-01-01',
        'gender' => 'other',
    ], new DateTimeImmutable('2026-09-01'));

    expect_same(true, isset($errors['display_name']));
    expect_same(true, isset($errors['gender']));
});

test('invalid calendar date is rejected', function (): void {
    $errors = ainder_validate_registration_fields([
        'display_name' => 'Eric',
        'birth_date' => '2000-02-30',
        'gender' => 'male',
    ], new DateTimeImmutable('2026-09-01'));

    expect_same(true, isset($errors['birth_date']));
});
