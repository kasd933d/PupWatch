<?php

namespace App\Services;

/**
 * SSRF guard (PRD §5).
 *
 * Structural rules enforced at save time AND at every check:
 *  1. only http/https
 *  2. only ports 80, 443, 8080, 8443
 *  3. no embedded credentials (user:pass@)
 *  4. hostnames such as localhost, *.internal, *.local, *.home.arpa are refused
 *  5. IP literals in loopback / private / link-local / metadata / multicast /
 *     unspecified ranges are refused, including IPv4-mapped IPv6 forms
 *
 * The full guard — resolve the hostname, validate every returned IP, then pin
 * the connection to the validated IP (CURLOPT_RESOLVE) — lands with the check
 * engine in Milestone 2 together with the PRD's blocked/allowed test table.
 *
 * This class never performs a network request itself, which is why it is safe
 * to call from form validation.
 */
class SsrfGuard
{
    /** PRD §5.2 — the only ports PupWatch may connect to. */
    public const ALLOWED_PORTS = [80, 443, 8080, 8443];

    /** @var list<string> */
    public const BLOCKED_HOST_SUFFIXES = ['.localhost', '.local', '.internal', '.home.arpa', '.lan'];

    /** @var list<string> */
    public const BLOCKED_HOSTS = ['localhost', 'metadata', 'metadata.google.internal'];

    /**
     * Validate the structural rules of a URL.
     *
     * @return list<string> human-readable problems; an empty list means acceptable
     */
    public static function validateUrl(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return ['The URL could not be parsed.'];
        }

        $errors = [];
        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            $errors[] = 'Only http and https URLs are allowed.';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            $errors[] = 'URLs with embedded credentials (user:pass@) are not allowed.';
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if (in_array($host, self::BLOCKED_HOSTS, true)) {
            $errors[] = sprintf('The host "%s" is not allowed.', $host);
        }

        foreach (self::BLOCKED_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                $errors[] = sprintf('Hosts ending in "%s" are not allowed.', $suffix);
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false && self::isBlockedIp($host)) {
            $errors[] = 'This IP address is not allowed.';
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);

        if (! in_array($port, self::ALLOWED_PORTS, true)) {
            $errors[] = sprintf(
                'Port %d is not allowed. Allowed ports: %s.',
                $port,
                implode(', ', self::ALLOWED_PORTS)
            );
        }

        return $errors;
    }

    /**
     * True when an IP literal must never be contacted (PRD §5.3).
     */
    public static function isBlockedIp(string $ip): bool
    {
        // Normalise IPv4-mapped IPv6 (::ffff:127.0.0.1) to its IPv4 form first.
        if (str_contains($ip, ':')) {
            if (preg_match('/^::ffff:(\d{1,3}(?:\.\d{1,3}){3})$/i', $ip, $matches)) {
                $ip = $matches[1];
            } else {
                return self::isBlockedIpv6($ip);
            }
        }

        $long = ip2long($ip);

        if ($long === false) {
            return true;
        }

        $long = (int) $long;

        $ranges = [
            ['0.0.0.0', '255.255.255.255'], // unspecified + broadcast + reserved
            ['127.0.0.0', '127.255.255.255'], // loopback
            ['10.0.0.0', '10.255.255.255'], // private
            ['172.16.0.0', '172.31.255.255'], // private
            ['192.168.0.0', '192.168.255.255'], // private
            ['169.254.0.0', '169.254.255.255'], // link-local incl. 169.254.169.254 metadata
            ['100.64.0.0', '100.127.255.255'], // CGNAT
            ['192.0.0.0', '192.0.0.255'], // IETF protocol assignments
            ['192.0.2.0', '192.0.2.255'], // TEST-NET-1
            ['198.18.0.0', '198.19.255.255'], // benchmarking
            ['198.51.100.0', '198.51.100.255'], // TEST-NET-2
            ['203.0.113.0', '203.0.113.255'], // TEST-NET-3
            ['224.0.0.0', '239.255.255.255'], // multicast
        ];

        foreach ($ranges as [$start, $end]) {
            if ($long >= (int) ip2long($start) && $long <= (int) ip2long($end)) {
                return true;
            }
        }

        return false;
    }

    private static function isBlockedIpv6(string $ip): bool
    {
        $binary = @inet_pton($ip);

        if ($binary === false || strlen($binary) !== 16) {
            return true;
        }

        // :: (unspecified) and ::1 (loopback)
        if ($binary === str_repeat("\0", 16) || $binary === str_repeat("\0", 15)."\x01") {
            return true;
        }

        $first = ord($binary[0]);
        $second = ord($binary[1]);

        // fe80::/10 link-local
        if ($first === 0xFE && ($second & 0xC0) === 0x80) {
            return true;
        }

        // fc00::/7 unique-local
        if (($first & 0xFE) === 0xFC) {
            return true;
        }

        // ff00::/8 multicast
        if ($first === 0xFF) {
            return true;
        }

        return false;
    }
}
