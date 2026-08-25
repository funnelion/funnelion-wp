<?php

declare(strict_types=1);

namespace Funnelion\Tests\Html;

use Funnelion\Html\ZoneSwapper;
use Funnelion\Resolve\Response;
use Funnelion\Resolve\SwapZone;
use PHPUnit\Framework\TestCase;

final class ZoneSwapperTest extends TestCase
{
    public function testReplacesInnerTextOfMarkedSpan(): void
    {
        $html = '<p>Call us: <span data-funnelion="Header phone">+370 626 33611</span></p>';

        $result = (new ZoneSwapper())->swap($html, $this->response([
            $this->zone('Header phone', '37060000123'),
        ]));

        $this->assertSame(
            '<p>Call us: <span data-funnelion="Header phone">37060000123</span></p>',
            $result,
        );
    }

    public function testRewritesTelHrefOnAnchor(): void
    {
        $html = '<a href="tel:+37062633611" data-funnelion="Header phone">+370 626 33611</a>';

        $result = (new ZoneSwapper())->swap($html, $this->response([
            $this->zone('Header phone', '37060000123'),
        ]));

        $this->assertSame(
            '<a href="tel:37060000123" data-funnelion="Header phone">37060000123</a>',
            $result,
        );
    }

    public function testRewritesMailtoHrefOnAnchor(): void
    {
        $html = '<a href="mailto:old@example.com" data-funnelion="Sales email">old@example.com</a>';

        $result = (new ZoneSwapper())->swap($html, $this->response([
            $this->zone('Sales email', 'new@example.com', 'email'),
        ]));

        $this->assertSame(
            '<a href="mailto:new@example.com" data-funnelion="Sales email">new@example.com</a>',
            $result,
        );
    }

    public function testReplacesMultipleZonesIndependently(): void
    {
        $html = '<span data-funnelion="Header phone">A</span> · <span data-funnelion="Footer phone">B</span>';

        $result = (new ZoneSwapper())->swap($html, $this->response([
            $this->zone('Header phone', '111'),
            $this->zone('Footer phone', '222'),
        ]));

        $this->assertSame(
            '<span data-funnelion="Header phone">111</span> · <span data-funnelion="Footer phone">222</span>',
            $result,
        );
    }

    public function testReplacesAllOccurrencesOfSameZone(): void
    {
        $html = '<span data-funnelion="X">old</span> middle <span data-funnelion="X">old</span>';

        $result = (new ZoneSwapper())->swap($html, $this->response([$this->zone('X', 'new')]));

        $this->assertSame(
            '<span data-funnelion="X">new</span> middle <span data-funnelion="X">new</span>',
            $result,
        );
    }

    public function testLeavesHtmlUnchangedWhenMarkerMissing(): void
    {
        $html = '<p>No marker here.</p>';

        $result = (new ZoneSwapper())->swap($html, $this->response([
            $this->zone('Header phone', '111'),
        ]));

        $this->assertSame($html, $result);
    }

    public function testSkipsZonesWithNullAddress(): void
    {
        $html = '<span data-funnelion="Header phone">+370 626 33611</span>';

        $result = (new ZoneSwapper())->swap($html, $this->response([
            $this->zone('Header phone', null),
        ]));

        // Page's static default stays intact.
        $this->assertSame($html, $result);
    }

    public function testSkipsZonesWithNullName(): void
    {
        $html = '<span data-funnelion="X">old</span>';

        $result = (new ZoneSwapper())->swap($html, $this->response([
            new SwapZone(
                name: null,
                channelKind: 'phone',
                address: '111',
                sourceLabel: null,
                poolId: null,
                maskPattern: null,
                matchedRuleId: null,
                startToken: null,
            ),
        ]));

        $this->assertSame($html, $result);
    }

    public function testEncodesAddressForHtmlSafety(): void
    {
        $html = '<span data-funnelion="X">old</span>';

        // Pathological: an attacker-controlled DID. We escape on output.
        $result = (new ZoneSwapper())->swap($html, $this->response([
            $this->zone('X', '<script>alert(1)</script>'),
        ]));

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testLeavesNonTelHrefAlone(): void
    {
        $html = '<a href="https://example.com" data-funnelion="X">old</a>';

        $result = (new ZoneSwapper())->swap($html, $this->response([$this->zone('X', '111')]));

        // href untouched; text replaced.
        $this->assertSame(
            '<a href="https://example.com" data-funnelion="X">111</a>',
            $result,
        );
    }

    /**
     * @param  list<SwapZone>  $zones
     */
    private function response(array $zones): Response
    {
        return new Response(sessionId: 1, visitorId: 'v', matchedRuleId: null, swapZones: $zones);
    }

    private function zone(?string $name, ?string $address, string $channelKind = 'phone'): SwapZone
    {
        return new SwapZone(
            name: $name,
            channelKind: $channelKind,
            address: $address,
            sourceLabel: null,
            poolId: null,
            maskPattern: null,
            matchedRuleId: null,
            startToken: null,
        );
    }
}
