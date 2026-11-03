<?php

namespace App\Services\Ssrf;

/**
 * A URL that passed every SSRF rule, together with the single IP the
 * connection must be pinned to (PRD §5.4).
 */
final readonly class ValidatedTarget
{
    public function __construct(
        public string $url,
        public string $scheme,
        public string $host,
        public int $port,
        public string $ip,
    ) {}

    /**
     * The CURLOPT_RESOLVE entry that pins this host:port to the validated IP.
     */
    public function resolveEntry(): string
    {
        // IPv6 literals must be bracketed inside CURLOPT_RESOLVE.
        $host = str_contains($this->ip, ':') ? '['.$this->ip.']' : $this->ip;

        return sprintf('%s:%d:%s', $this->host, $this->port, $host);
    }
}
