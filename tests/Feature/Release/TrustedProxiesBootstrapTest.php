<?php

namespace Tests\Feature\Release;

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Process;
use ReflectionClass;
use Tests\TestCase;

class TrustedProxiesBootstrapTest extends TestCase
{
    public function test_artisan_boots_when_trusted_proxies_are_configured(): void
    {
        $result = Process::path(base_path())
            ->timeout(30)
            ->env([
                'TRUSTED_PROXIES' => '127.0.0.1',
                'APP_ENV' => 'testing',
            ])
            ->run([PHP_BINARY, 'artisan', 'about', '--only=environment']);

        $this->assertTrue(
            $result->successful(),
            $result->errorOutput().$result->output(),
        );
        $this->assertStringNotContainsString('Target class [config] does not exist', $result->errorOutput());
        $this->assertStringNotContainsString('Class "config" does not exist', $result->errorOutput());
        $this->assertStringNotContainsString('Class "config" does not exist', $result->output());
    }

    public function test_empty_trusted_proxies_does_not_register_a_trust_all_proxy(): void
    {
        $configured = config('app.trusted_proxies');

        $this->assertTrue(
            $configured === null || $configured === '',
            'phpunit must not default TRUSTED_PROXIES to a trust-all value.',
        );

        $property = (new ReflectionClass(TrustProxies::class))->getProperty('alwaysTrustProxies');
        $this->assertNull($property->getValue());
    }
}
