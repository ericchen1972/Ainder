<?php

declare(strict_types=1);

function ainder_validate_registration_fields(
    array $input,
    DateTimeImmutable $today
): array {
    $errors = [];
    $displayName = trim((string) ($input['display_name'] ?? ''));
    $basicIntro = trim((string) ($input['basic_intro'] ?? ''));

    if ($displayName === '' || mb_strlen($displayName) > 120) {
        $errors['display_name'] = '請輸入 1–120 個字的名字。';
    }

    $birthValue = (string) ($input['birth_date'] ?? '');
    $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $birthValue);
    $dateErrors = DateTimeImmutable::getLastErrors();

    if (!$birthDate
        || ($dateErrors !== false
            && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
        $errors['birth_date'] = '請輸入有效的生日。';
    } elseif ($birthDate->modify('+18 years') > $today->setTime(0, 0)) {
        $errors['birth_date'] = '你必須年滿 18 歲。';
    }

    if (!in_array($input['gender'] ?? '', ['male', 'female'], true)) {
        $errors['gender'] = '請選擇男性或女性。';
    }

    if ($basicIntro === '') {
        $errors['basic_intro'] = '請填寫基本資料。';
    } elseif (mb_strlen($basicIntro, 'UTF-8') > 50) {
        $errors['basic_intro'] = '基本資料不可超過 50 字。';
    }

    return $errors;
}
