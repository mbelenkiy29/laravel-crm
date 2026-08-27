<?php

use App\Support\PublicHttps;

test('https is forced when the request is already https', function () {
    expect(PublicHttps::shouldForce('http://localhost', null, null, true))->toBeTrue();
});

test('https is forced when RENDER_EXTERNAL_URL is https even if the request is http', function () {
    expect(PublicHttps::shouldForce(
        'http://localhost',
        null,
        'https://krayin-crm.example.test',
        false,
    ))->toBeTrue();
});

test('https is forced when APP_URL or ASSET_URL is https', function () {
    expect(PublicHttps::shouldForce('https://krayin-crm.example.test', null, null, false))->toBeTrue()
        ->and(PublicHttps::shouldForce(null, 'https://krayin-crm.example.test/assets', null, false))->toBeTrue();
});

test('https is not forced for local http', function () {
    expect(PublicHttps::shouldForce('http://localhost', null, 'http://localhost', false))->toBeFalse();
});

test('forwarded proto https counts as a https request', function () {
    expect(PublicHttps::requestIsHttps('https', false))->toBeTrue()
        ->and(PublicHttps::requestIsHttps('http', false))->toBeFalse()
        ->and(PublicHttps::requestIsHttps(null, true))->toBeTrue();
});
