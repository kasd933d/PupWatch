<?php

namespace App\Services\Dns;

/**
 * Small seam around DNS so the SSRF guard can be tested without the network
 * (PRD §14 — "a fake DNS resolver").
 */
interface DnsResolver
{
    /**
     * Resolve a hostname to every IP it currently answers with.
     *
     * @return list<string> IPv4 and/or IPv6 literals; an empty list when the host does not resolve
     */
    public function resolve(string $host): array;
}
