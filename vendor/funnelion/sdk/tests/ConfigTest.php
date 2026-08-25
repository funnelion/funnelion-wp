<?php

declare(strict_types=1);

namespace Funnelion\Tests;

use Funnelion\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = new Config(siteToken: 'tk');

        $this->assertSame('https://dash.funnelion.ai', $config->baseUri);
        $this->assertSame(0.5, $config->timeoutSeconds);
        $this->assertStringStartsWith('funnelion-php/', $config->userAgent);
    }

    public function testEmptyTokenRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Config(siteToken: '');
    }

    public function testNonPositiveTimeoutRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Config(siteToken: 'tk', timeoutSeconds: 0);
    }
}
