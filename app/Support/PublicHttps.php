<?php

namespace App\Support;

class PublicHttps
{
    /**
     * Whether generated app and asset URLs should use https.
     */
    public static function shouldForce(
        ?string $appUrl,
        ?string $assetUrl,
        ?string $renderExternalUrl,
        bool $requestHttps,
        bool $onRender = false,
    ): bool {
        if ($requestHttps || $onRender) {
            return true;
        }

        foreach ([$appUrl, $assetUrl, $renderExternalUrl] as $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }

            if (str_starts_with($url, 'https://')) {
                return true;
            }

            $host = parse_url($url, PHP_URL_HOST);

            if (is_string($host) && str_ends_with(strtolower($host), '.onrender.com')) {
                return true;
            }
        }

        return false;
    }

    public static function requestIsHttps(?string $forwardedProto, bool $isSecure): bool
    {
        return $isSecure || strtolower((string) $forwardedProto) === 'https';
    }

    public static function isRenderRuntime(?string $renderFlag, ?string $renderExternalUrl): bool
    {
        if (is_string($renderExternalUrl) && $renderExternalUrl !== '') {
            return true;
        }

        $flag = strtolower(trim((string) $renderFlag));

        return $flag !== '' && $flag !== '0' && $flag !== 'false';
    }

    /**
     * Rewrite an origin to https without changing its host.
     */
    public static function toHttps(?string $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return $url;
        }

        if (str_starts_with($url, 'http://')) {
            return 'https://'.substr($url, strlen('http://'));
        }

        return $url;
    }

    /**
     * Public origin for generated URLs. Uses RENDER_EXTERNAL_URL only when APP_URL has no real host.
     */
    public static function publicOrigin(?string $appUrl, ?string $renderExternalUrl): ?string
    {
        $candidate = $appUrl;

        if (self::isPlaceholderHost($appUrl) && is_string($renderExternalUrl) && $renderExternalUrl !== '') {
            $candidate = $renderExternalUrl;
        }

        return self::toHttps($candidate);
    }

    private static function isPlaceholderHost(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return true;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host === null || $host === '' || in_array(strtolower($host), ['localhost', '127.0.0.1'], true);
    }
}
