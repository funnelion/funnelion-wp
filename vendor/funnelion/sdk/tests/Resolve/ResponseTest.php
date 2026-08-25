<?php

declare(strict_types=1);

namespace Funnelion\Tests\Resolve;

use Funnelion\Resolve\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testFromArrayHappyPath(): void
    {
        $response = Response::fromArray([
            'session_id' => 17,
            'visitor_id' => 'uuid-1',
            'matched_rule_id' => 4,
            'swap_zones' => [
                [
                    'name' => 'Header phone',
                    'channel_kind' => 'phone',
                    'address' => '37060000123',
                    'pool_id' => 2,
                ],
                [
                    'name' => 'Footer phone',
                    'channel_kind' => 'phone',
                    'address' => '37060000456',
                ],
            ],
        ]);

        $this->assertSame(17, $response->sessionId);
        $this->assertSame('uuid-1', $response->visitorId);
        $this->assertSame(4, $response->matchedRuleId);
        $this->assertCount(2, $response->swapZones);
        $this->assertNull($response->reason);
    }

    public function testSwapZoneLookupByName(): void
    {
        $response = Response::fromArray([
            'swap_zones' => [
                ['name' => 'Header phone', 'channel_kind' => 'phone', 'address' => '11'],
                ['name' => 'Footer phone', 'channel_kind' => 'phone', 'address' => '22'],
            ],
        ]);

        $this->assertSame('11', $response->swapZone('Header phone')?->address);
        $this->assertSame('22', $response->swapZone('Footer phone')?->address);
        $this->assertNull($response->swapZone('Nonexistent'));
    }

    public function testFromArrayShortCircuitResponse(): void
    {
        $response = Response::fromArray([
            'session_id' => null,
            'visitor_id' => null,
            'matched_rule_id' => null,
            'swap_zones' => [],
            'reason' => 'ip_blocked',
        ]);

        $this->assertNull($response->sessionId);
        $this->assertSame('ip_blocked', $response->reason);
        $this->assertSame([], $response->swapZones);
    }

    public function testFromArrayTolerantOfMissingFields(): void
    {
        $response = Response::fromArray([]);

        $this->assertNull($response->sessionId);
        $this->assertNull($response->visitorId);
        $this->assertSame([], $response->swapZones);
    }
}
