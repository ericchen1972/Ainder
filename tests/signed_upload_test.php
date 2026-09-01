<?php

declare(strict_types=1);

require_once dirname(__DIR__).'/web/lib/signed_uploads.php';

test('signed upload token binds upload id and expiry', function (): void {
    $signature = ainder_sign_upload('upload-1', 1788300000, 'secret');
    expect_same(true, ainder_verify_upload_signature(
        'upload-1', 1788300000, $signature, 'secret', 1788299900
    ));
    expect_same(false, ainder_verify_upload_signature(
        'upload-2', 1788300000, $signature, 'secret', 1788299900
    ));
});

test('expired signed upload is rejected', function (): void {
    $signature = ainder_sign_upload('upload-1', 100, 'secret');
    expect_same(false, ainder_verify_upload_signature(
        'upload-1', 100, $signature, 'secret', 101
    ));
});

test('Agent registration identifiers are opaque lowercase hex', function (): void {
    expect_same(1, preg_match('/^[a-f0-9]{32}$/', ainder_agent_identifier()));
});
