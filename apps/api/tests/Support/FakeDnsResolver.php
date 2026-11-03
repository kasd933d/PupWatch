<?php

namespace Tests\Support;

use App\Services\Dns\DnsResolver;

/**
 * Deterministic DNS for tests: every host resolves to a public address unless
 * the test registered it as a blocked one (PRD §14 — fake DNS resolver).
 */
class FakeDnsResolver implements DnsResolver
{
    public const PUBLIC_IP = '93.184.216.34';

    /** @var array<string, list<string>> */
    private array $overrides = [];

    /** @var array<string, list<string>> */
    private array $resolved = [];

    /**
     * @param  array<string, list<string>>  $overrides
     */
    public function __construct(array $overrides = [])
    {
        $this->overrides = $overrides;
    }

    /**
     * @param  list<string>  $ips
     */
    public function set(string $host, array $ips): void
    {
        $this->overrides[strtolower($host)] = $ips;
    }

    public function resolve(string $host): array
    {
        $host = strtolower($host);

        $this->resolved[$host] = $this->overrides[$host] ?? (
            filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : [self::PUBLIC_IP]
        );

        return $this->resolved[$host];
    }

    /**
     * @return array<string, list<string>>
     */
    public function resolved(): array
    {
        return $this->resolved;
    }
}
