<?php

namespace App\Services;

use App\Services\Dns\DnsResolver;
use App\Services\Ssrf\SsrfViolationException;
use App\Services\Ssrf\ValidatedTarget;

/**
 * SSRF guard (PRD §5).
 *
 * Rules, enforced at save time AND at every check:
 *  1. only http/https
 *  2. only ports 80, 443, 8080, 8443
 *  3. no embedded credentials (user:pass@)
 *  4. hostnames such as localhost, *.internal, *.local, *.home.arpa are refused
 *  5. every resolved IP must be public — loopback, private, link-local,
 *     metadata (169.254.169.254), CGNAT, multicast, reserved and test ranges
 *     are refused, including IPv4-mapped IPv6 forms
 *  6. the connection is pinned to the validated IP (see ValidatedTarget)
 *
 * This class performs DNS resolution (through the injected resolver) but never
 * an HTTP request, so it is safe to use from form validation.
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
     * IPv4 ranges that must never be contacted (PRD §5.3).
     *
     * @var list<array{0: string, 1: string}>
     */
    private const BLOCKED_IPV4_RANGES = [
        ['0.0.0.0', '0.255.255.255'], // unspecified / "this network"
        ['10.0.0.0', '10.255.255.255'], // private
        ['100.64.0.0', '100.127.255.255'], // CGNAT
        ['127.0.0.0', '127.255.255.255'], // loopback
        ['169.254.0.0', '169.254.255.255'], // link-local, includes 169.254.169.254 metadata
        ['172.16.0.0', '172.31.255.255'], // private
        ['192.0.0.0', '192.0.0.255'], // IETF protocol assignments
        ['192.0.2.0', '192.0.2.255'], // TEST-NET-1
        ['192.168.0.0', '192.168.255.255'], // private
        ['198.18.0.0', '198.19.255.255'], // benchmarking
        ['198.51.100.0', '198.51.100.255'], // TEST-NET-2
        ['203.0.113.0', '203.0.113.255'], // TEST-NET-3
        ['224.0.0.0', '239.255.255.255'], // multicast
        ['240.0.0.0', '255.255.255.255'], // reserved + broadcast
    ];

    public function __construct(private readonly DnsResolver $resolver) {}

    /**
     * Full validation: structural rules plus DNS, returning the pinned target.
     *
     * @throws SsrfViolationException
     */
    public function validate(string $url): ValidatedTarget
    {
        $parts = $this->parse($url);
        $this->assertStructure($parts);

        $scheme = strtolower($parts['scheme']);
        $host = strtolower(trim($parts['host'], '[]'));
        $port = $this->port($parts, $scheme);

        $ips = $this->resolver->resolve($host);

        if ($ips === []) {
            throw new SsrfViolationException(sprintf('Could not resolve the host "%s".', $host));
        }

        foreach ($ips as $ip) {
            if (self::isBlockedIp($ip)) {
                throw new SsrfViolationException(
                    sprintf('The host "%s" resolves to a private or reserved address and cannot be checked.', $host)
                );
            }
        }

        return new ValidatedTarget($url, $scheme, $host, $port, $ips[0]);
    }

    /**
     * Save-time validation (PRD §4.2): structural rules always, DNS when it is
     * available. An unresolvable host is accepted here — the check engine
     * records the DNS failure as data instead of blocking the save.
     *
     * @return list<string> human-readable problems; empty means acceptable
     */
    public function saveTimeErrors(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return ['The URL could not be parsed.'];
        }

        $errors = self::structuralErrors($parts);

        if ($errors !== []) {
            return $errors;
        }

        try {
            $host = strtolower(trim($parts['host'], '[]'));
            $ips = $this->resolver->resolve($host);

            foreach ($ips as $ip) {
                if (self::isBlockedIp($ip)) {
                    return [sprintf(
                        'The host "%s" resolves to a private or reserved address and cannot be checked.',
                        $host
                    )];
                }
            }
        } catch (\Throwable) {
            // DNS being unavailable is not a reason to reject the save.
        }

        return [];
    }

    /**
     * Structural validation only (no DNS), used by callers that must not block.
     *
     * @return list<string>
     */
    public static function validateUrl(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return ['The URL could not be parsed.'];
        }

        return self::structuralErrors($parts);
    }

    /**
     * True when an IP literal must never be contacted (PRD §5.3).
     */
    public static function isBlockedIp(string $ip): bool
    {
        $ip = trim($ip);

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

        // ip2long() returns a signed int on 32-bit builds; compare as unsigned.
        $long = $long < 0 ? $long + 4294967296 : $long;

        foreach (self::BLOCKED_IPV4_RANGES as [$start, $end]) {
            $startLong = (int) ip2long($start);
            $endLong = (int) ip2long($end);
            $startLong = $startLong < 0 ? $startLong + 4294967296 : $startLong;
            $endLong = $endLong < 0 ? $endLong + 4294967296 : $endLong;

            if ($long >= $startLong && $long <= $endLong) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{scheme?: string, host?: string, port?: int, user?: string, pass?: string}  $parts
     * @return list<string>
     */
    private static function structuralErrors(array $parts): array
    {
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
     * @return array{scheme: string, host: string, port?: int, user?: string, pass?: string}
     */
    private function parse(string $url): array
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new SsrfViolationException('The URL could not be parsed.');
        }

        /** @var array{scheme: string, host: string, port?: int, user?: string, pass?: string} $parts */
        return $parts;
    }

    /**
     * @param  array{scheme: string}  $parts
     */
    private function assertStructure(array $parts): void
    {
        $errors = self::structuralErrors($parts);

        if ($errors !== []) {
            throw new SsrfViolationException($errors[0]);
        }
    }

    /**
     * @param  array{port?: int}  $parts
     */
    private function port(array $parts, string $scheme): int
    {
        return isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
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

        // ::ffff:0:0/96 and other IPv4-mapped forms that were not dotted-quad
        if (substr($binary, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            return true;
        }

        return false;
    }
}
