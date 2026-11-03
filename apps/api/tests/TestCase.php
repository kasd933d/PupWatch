<?php

namespace Tests;

use App\Services\Dns\DnsResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakeDnsResolver;

abstract class TestCase extends BaseTestCase
{
    /**
     * Every test runs against deterministic DNS so the suite never depends on
     * the network and private-range resolution is reproducible.
     */
    protected FakeDnsResolver $dns;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dns = new FakeDnsResolver;
        $this->app->instance(DnsResolver::class, $this->dns);
    }
}
