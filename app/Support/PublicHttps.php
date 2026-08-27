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
    ): bool {
        if ($requestHttps) {
            return true;
        }

        foreach ([$appUrl, $assetUrl, $renderExternalUrl] as $url) {
            if (is_string($url) && str_starts_with($url, 'https://')) {
                return true;
            }
        }

        return false;
    }

    public static function requestIsHttps(?string $forwardedProto, bool $isSecure): bool
    {
        return $isSecure || strtolower((string) $forwardedProto) === 'https';
    }
}
