<?php

namespace App\Support\Playback;

use Illuminate\Http\Request;
use Modules\World\Models\Country;
use Symfony\Component\HttpFoundation\IpUtils;

final class TrustedCountryResolver
{
    public function resolve(Request $request): ?string
    {
        $remoteAddress = $request->server->get('REMOTE_ADDR');
        $countryHeader = config('playback.trusted_geo.country_header');
        $trustedCidrs = config('playback.trusted_geo.trusted_proxy_cidrs', []);

        if (! is_string($remoteAddress) || filter_var($remoteAddress, FILTER_VALIDATE_IP) === false
            || ! is_string($countryHeader) || ! preg_match('/^[A-Za-z][A-Za-z0-9-]{0,63}$/', $countryHeader)
            || ! is_array($trustedCidrs) || $trustedCidrs === []) {
            return null;
        }

        $edgeIsTrusted = false;
        foreach ($trustedCidrs as $cidr) {
            if (is_string($cidr) && $cidr !== '' && IpUtils::checkIp($remoteAddress, $cidr)) {
                $edgeIsTrusted = true;
                break;
            }
        }

        if (! $edgeIsTrusted) {
            return null;
        }

        $countryCode = strtoupper(trim((string) $request->headers->get($countryHeader, '')));
        if (! preg_match('/^[A-Z]{2}$/', $countryCode) || in_array($countryCode, ['XX', 'T1', 'A1'], true)) {
            return null;
        }

        return Country::query()
            ->where('status', 1)
            ->whereNull('deleted_at')
            ->whereRaw('UPPER(code) = ?', [$countryCode])
            ->exists()
            ? $countryCode
            : null;
    }
}
