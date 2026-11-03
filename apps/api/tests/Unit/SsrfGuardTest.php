<?php

namespace Tests\Unit;

use App\Services\Dns\DnsResolver;
use App\Services\Ssrf\SsrfViolationException;
use App\Services\SsrfGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SsrfGuardTest extends TestCase
{
    private function guard(): SsrfGuard
    {
        return new SsrfGuard($this->app->make(DnsResolver::class));
    }

    /**
     * PRD §14 — a long table of blocked and allowed addresses.
     */
    public static function blockedIpProvider(): array
    {
        return [
            'loopback' => ['127.0.0.1'],
            'loopback range' => ['127.255.255.254'],
            'private 10/8' => ['10.1.2.3'],
            'private 172.16/12 low' => ['172.16.0.1'],
            'private 172.16/12 high' => ['172.31.255.254'],
            'private 192.168/16' => ['192.168.0.10'],
            'link-local' => ['169.254.1.1'],
            'cloud metadata' => ['169.254.169.254'],
            'unspecified' => ['0.0.0.0'],
            'broadcast' => ['255.255.255.255'],
            'multicast' => ['224.0.0.1'],
            'CGNAT' => ['100.64.0.1'],
            'reserved 240/4' => ['240.0.0.1'],
            'ipv6 loopback' => ['::1'],
            'ipv6 unspecified' => ['::'],
            'ipv6 link-local' => ['fe80::1'],
            'ipv6 unique-local' => ['fc00::abcd'],
            'ipv6 multicast' => ['ff02::1'],
            'ipv4-mapped loopback' => ['::ffff:127.0.0.1'],
            'ipv4-mapped private' => ['::ffff:10.0.0.1'],
            'not an ip' => ['not-an-ip'],
        ];
    }

    #[DataProvider('blockedIpProvider')]
    public function test_blocked_ip_literals(string $ip): void
    {
        $this->assertTrue(SsrfGuard::isBlockedIp($ip), "$ip should be blocked");
    }

    public static function allowedIpProvider(): array
    {
        return [
            'example.com' => ['93.184.216.34'],
            'google dns' => ['8.8.8.8'],
            'cloudflare dns' => ['1.1.1.1'],
            'public ipv6' => ['2606:4700:4700::1111'],
        ];
    }

    #[DataProvider('allowedIpProvider')]
    public function test_public_ip_literals_are_allowed(string $ip): void
    {
        $this->assertFalse(SsrfGuard::isBlockedIp($ip), "$ip should be allowed");
    }

    public static function blockedUrlProvider(): array
    {
        return [
            'localhost' => ['http://localhost/admin'],
            'localhost suffix' => ['http://app.localhost/'],
            'dot local' => ['http://printer.local/'],
            'dot internal' => ['http://db.internal/'],
            'dot lan' => ['http://nas.lan/'],
            'home.arpa' => ['http://router.home.arpa/'],
            'embedded credentials' => ['http://user:pass@example.com/'],
            'ssh port' => ['https://example.com:22/'],
            'ftp scheme' => ['ftp://example.com/'],
            'file scheme' => ['file:///etc/passwd'],
            'javascript scheme' => ['javascript:alert(1)'],
            'ip literal loopback' => ['http://127.0.0.1/'],
            'ip literal metadata' => ['http://169.254.169.254/latest/meta-data'],
            'ip literal private' => ['http://192.168.1.1/'],
            'ip literal ipv6 loopback' => ['http://[::1]/'],
            'not a url' => ['not a url'],
        ];
    }

    #[DataProvider('blockedUrlProvider')]
    public function test_unsafe_urls_are_rejected(string $url): void
    {
        $this->assertNotEmpty(SsrfGuard::validateUrl($url), "$url should be rejected");
    }

    public static function allowedUrlProvider(): array
    {
        return [
            'https default port' => ['https://example.com/health'],
            'http default port' => ['http://example.com/'],
            'port 8080' => ['http://example.com:8080/'],
            'port 8443' => ['https://example.com:8443/'],
            'ip literal public' => ['https://93.184.216.34/'],
            'path and query' => ['https://example.com/a/b?c=d'],
        ];
    }

    #[DataProvider('allowedUrlProvider')]
    public function test_safe_urls_pass_structural_validation(string $url): void
    {
        $this->assertSame([], SsrfGuard::validateUrl($url), "$url should be allowed");
    }

    public function test_validate_throws_when_a_host_resolves_to_a_private_address(): void
    {
        $this->dns->set('rebind.example', ['10.0.0.7']);

        $this->expectException(SsrfViolationException::class);
        $this->guard()->validate('https://rebind.example/');
    }

    public function test_validate_throws_when_any_resolved_address_is_blocked(): void
    {
        // A mixed answer is still an attack surface: every IP must be public.
        $this->dns->set('mixed.example', ['93.184.216.34', '192.168.0.1']);

        $this->expectException(SsrfViolationException::class);
        $this->guard()->validate('https://mixed.example/');
    }

    public function test_validate_throws_when_the_host_does_not_resolve(): void
    {
        $this->dns->set('nothing.example', []);

        $this->expectException(SsrfViolationException::class);
        $this->guard()->validate('https://nothing.example/');
    }

    public function test_validate_returns_the_pinned_ip_for_a_safe_host(): void
    {
        $target = $this->guard()->validate('https://example.com/health');

        $this->assertSame('93.184.216.34', $target->ip);
        $this->assertSame('example.com', $target->host);
        $this->assertSame(443, $target->port);
        $this->assertSame('example.com:443:93.184.216.34', $target->resolveEntry());
    }

    public function test_save_time_check_rejects_a_host_that_resolves_privately(): void
    {
        $this->dns->set('internal.example', ['10.0.0.1']);

        $this->assertNotEmpty($this->guard()->saveTimeErrors('https://internal.example/'));
    }

    public function test_save_time_check_allows_an_unresolvable_host(): void
    {
        // DNS being down must not block a save; the check records the failure.
        $this->dns->set('offline.example', []);

        $this->assertSame([], $this->guard()->saveTimeErrors('https://offline.example/'));
    }
}
