<?php

declare(strict_types=1);

function ainder_json_body(): array
{
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('INVALID_JSON');
    }

    return $decoded;
}

function ainder_json_success(array $data = [], int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true] + $data, JSON_UNESCAPED_SLASHES);
    exit;
}

function ainder_json_error(string $code, string $message, int $status): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => false,
        'error' => ['code' => $code, 'message' => $message],
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

function ainder_require_post_json(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        ainder_json_error('METHOD_NOT_ALLOWED', 'POST required.', 405);
    }
}

function ainder_request_header(string $name): string
{
    $serverName = 'HTTP_'.strtoupper(str_replace('-', '_', $name));

    return trim((string) ($_SERVER[$serverName] ?? ''));
}

function ainder_require_json_csrf(): void
{
    if (!ainder_form_csrf_is_valid(ainder_request_header('X-Ainder-CSRF'))) {
        ainder_json_error('CSRF_INVALID', 'Request confirmation expired.', 403);
    }
}
