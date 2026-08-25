<?php

declare(strict_types=1);

namespace Funnelion\Tests\Cookie;

use Funnelion\Cookie\GoogleAnalytics;
use PHPUnit\Framework\TestCase;

final class GoogleAnalyticsTest extends TestCase
{
    public function testClientIdFromGaCookie(): void
    {
        $this->assertSame(
            '1699887766.1750000000',
            GoogleAnalytics::clientIdFromGlobals(['_ga' => 'GA1.1.1699887766.1750000000']),
        );
    }

    public function testClientIdReadsFromTheEndSoDomainDepthPrefixDoesNotMatter(): void
    {
        // GA1.2 / GA1.3 appear on hosts with more labels.
        $this->assertSame(
            '1699887766.1750000000',
            GoogleAnalytics::clientIdFromGlobals(['_ga' => 'GA1.3.1699887766.1750000000']),
        );
    }

    public function testClientIdIsNullWhenCookieAbsentOrEmpty(): void
    {
        $this->assertNull(GoogleAnalytics::clientIdFromGlobals([]));
        $this->assertNull(GoogleAnalytics::clientIdFromGlobals(['_ga' => '']));
    }

    public function testClientIdIsNullForAValueThatIsNotAGaClientId(): void
    {
        // A Funnelion visitor UUID is exactly what must NOT pass through
        // as a GA client id — sending one is what puts events in GA4's
        // "Unassigned" bucket.
        $this->assertNull(GoogleAnalytics::clientIdFromGlobals(['_ga' => '90ccd5c1-4f0e-4b5e-9c1a-2f0f3b7c8d90']));
        $this->assertNull(GoogleAnalytics::clientIdFromGlobals(['_ga' => 'GA1.1.not.numbers']));
        $this->assertNull(GoogleAnalytics::clientIdFromGlobals(['_ga' => 'GA1.1.1699887766']));
    }

    public function testSessionIdFromCurrentCookieFormat(): void
    {
        $this->assertSame(
            '1750000000',
            GoogleAnalytics::sessionIdFromGlobals('G-ABC123', [
                '_ga_ABC123' => 'GS2.1.s1750000000$o3$g1$t1750000090$j60$l0$h0',
            ]),
        );
    }

    public function testSessionIdFromLegacyCookieFormat(): void
    {
        $this->assertSame(
            '1750000000',
            GoogleAnalytics::sessionIdFromGlobals('G-ABC123', [
                '_ga_ABC123' => 'GS1.1.1750000000.3.0.1750000090.0.0.0',
            ]),
        );
    }

    public function testSessionIdAcceptsMeasurementIdWithOrWithoutGPrefix(): void
    {
        $cookies = ['_ga_ABC123' => 'GS2.1.s1750000000$o3'];

        $this->assertSame('1750000000', GoogleAnalytics::sessionIdFromGlobals('G-ABC123', $cookies));
        $this->assertSame('1750000000', GoogleAnalytics::sessionIdFromGlobals('ABC123', $cookies));
        $this->assertSame('1750000000', GoogleAnalytics::sessionIdFromGlobals('  G-ABC123  ', $cookies));
    }

    public function testSessionIdIsNullWhenTheStreamCookieIsMissing(): void
    {
        // Cookie for a different data stream must not be picked up.
        $this->assertNull(GoogleAnalytics::sessionIdFromGlobals('G-ABC123', [
            '_ga_XYZ789' => 'GS2.1.s1750000000$o3',
        ]));
        $this->assertNull(GoogleAnalytics::sessionIdFromGlobals('G-ABC123', []));
        $this->assertNull(GoogleAnalytics::sessionIdFromGlobals('', ['_ga_ABC123' => 'GS2.1.s1750000000$o3']));
    }

    public function testSessionIdIsNullForAnUnparseableValue(): void
    {
        $this->assertNull(GoogleAnalytics::sessionIdFromGlobals('G-ABC123', ['_ga_ABC123' => 'garbage']));
        $this->assertNull(GoogleAnalytics::parseSessionCookie('GS2.1.oops'));
    }
}
