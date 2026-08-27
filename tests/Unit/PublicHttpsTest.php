<?php

use App\Support\PublicHttps;

test('https is forced when the request is already https', function () {
    expect(PublicHttps::shouldForce('http://localhost', null, null, true))->toBeTrue();
});

test('https is forced on Render even when APP_URL is http', function () {
    expect(PublicHttps::shouldForce(
        'http://krayin-crm-c1gg.onrender.com',
        'http://krayin-crm-c1gg.onrender.com',
        null,
        false,
        true,
    ))->toBeTrue();
});

test('https is forced when the app host is onrender.com even if APP_URL is http', function () {
    expect(PublicHttps::shouldForce(
        'http://krayin-crm-c1gg.onrender.com',
        null,
        null,
        false,
    ))->toBeTrue();
});

test('https is forced when RENDER_EXTERNAL_URL is https even if the request is http', function () {
    expect(PublicHttps::shouldForce(
        'http://localhost',
        null,
        'https://krayin-crm-c1gg.onrender.com',
        false,
    ))->toBeTrue();
});

test('https is not forced for local http', function () {
    expect(PublicHttps::shouldForce('http://localhost', null, null, false))->toBeFalse();
});

test('forwarded proto https counts as a https request', function () {
    expect(PublicHttps::requestIsHttps('https', false))->toBeTrue()
        ->and(PublicHttps::requestIsHttps('http', false))->toBeFalse()
        ->and(PublicHttps::requestIsHttps(null, true))->toBeTrue();
});

test('toHttps rewrites the scheme without changing the host', function () {
    expect(PublicHttps::toHttps('http://krayin-crm-c1gg.onrender.com'))
        ->toBe('https://krayin-crm-c1gg.onrender.com')
        ->and(PublicHttps::toHttps('https://krayin-crm-c1gg.onrender.com'))
        ->toBe('https://krayin-crm-c1gg.onrender.com');
});

test('publicOrigin keeps a real APP_URL host and only upgrades the scheme', function () {
    expect(PublicHttps::publicOrigin(
        'http://krayin-crm-c1gg.onrender.com',
        'https://other.onrender.com',
    ))->toBe('https://krayin-crm-c1gg.onrender.com');
});

test('RENDER env counts as Render runtime', function () {
    expect(PublicHttps::isRenderRuntime('true', null))->toBeTrue()
        ->and(PublicHttps::isRenderRuntime(null, 'https://krayin-crm-c1gg.onrender.com'))->toBeTrue()
        ->and(PublicHttps::isRenderRuntime(null, null))->toBeFalse();
});
