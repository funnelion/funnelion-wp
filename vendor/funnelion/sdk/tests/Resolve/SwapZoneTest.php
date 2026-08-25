<?php

declare(strict_types=1);

namespace Funnelion\Tests\Resolve;

use Funnelion\Resolve\SwapZone;
use PHPUnit\Framework\TestCase;

final class SwapZoneTest extends TestCase
{
    public function testFromArrayAllFields(): void
    {
        $zone = SwapZone::fromArray([
            'name' => 'Header phone',
            'channel_kind' => 'phone',
            'address' => '37060000123',
            'source_label' => 'Facebook',
            'pool_id' => 2,
            'mask_pattern' => '+370 ### ## ###',
            'matched_rule_id' => 4,
            'start_token' => 'abc123',
        ]);

        $this->assertSame('Header phone', $zone->name);
        $this->assertSame('phone', $zone->channelKind);
        $this->assertSame('37060000123', $zone->address);
        $this->assertSame('Facebook', $zone->sourceLabel);
        $this->assertSame(2, $zone->poolId);
        $this->assertSame('+370 ### ## ###', $zone->maskPattern);
        $this->assertSame(4, $zone->matchedRuleId);
        $this->assertSame('abc123', $zone->startToken);
    }

    public function testFromArrayDefaultsChannelKindToPhone(): void
    {
        $zone = SwapZone::fromArray(['name' => 'X']);

        $this->assertSame('phone', $zone->channelKind);
    }

    public function testFromArrayTolerantOfMissingFields(): void
    {
        $zone = SwapZone::fromArray([]);

        $this->assertNull($zone->name);
        $this->assertNull($zone->address);
        $this->assertNull($zone->poolId);
    }
}
