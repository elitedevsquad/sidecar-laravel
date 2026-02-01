<?php

namespace EliteDevSquad\SidecarLaravel\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** @codeCoverageIgnore */
class SidecarMiddleware
{
    public function handle(Request $request, Closure $next): mixed
    {
        $this->validateAuth();
        $this->validatePermissions($request);
        $this->validateEnabled();

        return $next($request);
    }

    private function validateAuth(): void
    {
        if (! Auth::check()) {
            abort(403, 'Unauthorized. Please log in.');
        }
    }

    private function validatePermissions(Request $request): void
    {
        $path = '/'.ltrim($request->path(), '/');

        if (! (str_starts_with($path, '/__devsquad-sidecar/execute-') || $path === '/__devsquad-sidecar/execute')) {
            return;
        }

        /** @var array<string> $allowedIps */
        $allowedIps = config('devsquad-sidecar.allowed_ips', []);

        if (blank($allowedIps)) {
            return;
        }

        $clientIp = $request->ip();

        if ($clientIp === null) {
            abort(403, 'Unauthorized. Unable to determine client IP address.');
        }

        foreach ($allowedIps as $allowedIp) {
            if ($this->ipMatches($clientIp, trim($allowedIp))) {
                return;
            }
        }

        abort(403, "Unauthorized IP: {$clientIp}. You are not authorized to execute this action.");
    }

    /**
     * Check if client IP matches the allowed IP pattern.
     * Supports exact matches and CIDR notation.
     */
    private function ipMatches(string $clientIp, string $allowedIp): bool
    {
        // Exact match
        if ($clientIp === $allowedIp) {
            return true;
        }

        // CIDR notation support (e.g., 192.168.1.0/24)
        if (str_contains($allowedIp, '/')) {
            return $this->ipMatchesCidr($clientIp, $allowedIp);
        }

        // Prefix match for convenience (e.g., "192.168.1" matches "192.168.1.*")
        // This is safer than str_contains as it only matches from the start
        if (str_starts_with($clientIp, $allowedIp)) {
            // Ensure we're matching complete octets, not partial ones
            // e.g., "192.168.1" should match "192.168.1.100" but not "192.168.10.100"
            $nextChar = substr($clientIp, strlen($allowedIp), 1);

            return $nextChar === '.' || $nextChar === ':' || $nextChar === '';
        }

        return false;
    }

    /**
     * Check if client IP matches CIDR notation.
     */
    private function ipMatchesCidr(string $clientIp, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr, 2);

        // Support both IPv4 and IPv6
        $clientBinary = @inet_pton($clientIp);
        $subnetBinary = @inet_pton($subnet);

        if ($clientBinary === false || $subnetBinary === false) {
            return false;
        }

        // IPv4
        if (strlen($clientBinary) === 4) {
            $mask = (int) $mask;
            if ($mask < 0 || $mask > 32) {
                return false;
            }

            $clientLong = ip2long($clientIp);
            $subnetLong = ip2long($subnet);

            if ($clientLong === false || $subnetLong === false) {
                return false;
            }

            $maskLong = $mask === 0 ? 0 : (~0 << (32 - $mask));

            return ($clientLong & $maskLong) === ($subnetLong & $maskLong);
        }

        // IPv6
        if (strlen($clientBinary) === 16) {
            $mask = (int) $mask;
            if ($mask < 0 || $mask > 128) {
                return false;
            }

            $bitsToCheck = $mask;
            for ($i = 0; $i < 16; $i++) {
                if ($bitsToCheck <= 0) {
                    break;
                }

                $clientByte = ord($clientBinary[$i]);
                $subnetByte = ord($subnetBinary[$i]);

                if ($bitsToCheck < 8) {
                    $byteMask = (~0 << (8 - $bitsToCheck)) & 0xFF;

                    return ($clientByte & $byteMask) === ($subnetByte & $byteMask);
                }

                if ($clientByte !== $subnetByte) {
                    return false;
                }

                $bitsToCheck -= 8;
            }

            return true;
        }

        return false;
    }

    private function validateEnabled(): void
    {
        if (! config('devsquad-sidecar.enabled')) {
            abort(403, 'Sidecar is disabled.');
        }
    }
}
