<?php

declare(strict_types=1);

namespace Funnelion\Tests\PageView;

use Funnelion\Client;
use Funnelion\Config;
use Funnelion\Exception\AuthenticationException;
use Funnelion\Http\HttpResponse;
use Funnelion\Http\MockTransport;
use Funnelion\PageView\Request;
use PHPUnit\Framework\TestCase;

final class ClientPageViewTest extends TestCase
{
    public function testSendsThePageAndAcceptsAnEmpty204(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(204, ''));

        $client = new Client(new Config(siteToken: 'test-token'), $transport);
        $client->pageView(new Request(
            visitorId: '90ccd5c1-test',
            url: 'https://example.com/pricing',
            language: 'lt',
            gaClientId: '123.456',
        ));

        $sent = $transport->sentRequests[0];
        $this->assertSame('POST', $sent['method']);
        $this->assertSame('https://dash.funnelion.ai/api/v1/page-view', $sent['uri']);
        $this->assertSame('Bearer test-token', $sent['headers']['Authorization']);
        $this->assertSame([
            'visitor_id' => '90ccd5c1-test',
            'url' => 'https://example.com/pricing',
            'language' => 'lt',
            'ga_client_id' => '123.456',
        ], json_decode($sent['body'], true));
    }

    public function testPageViewThrowsOnRejection(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(401, '{"error":"unknown_token"}'));

        $this->expectException(AuthenticationException::class);
        (new Client(new Config(siteToken: 'bad'), $transport))
            ->pageView(new Request(visitorId: 'v', url: 'https://example.com/'));
    }

    public function testPageViewQuietlyReportsInsteadOfThrowing(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(204, ''));
        $transport->push(new HttpResponse(429, ''));
        $transport->push(new HttpResponse(503, ''));

        $client = new Client(new Config(siteToken: 'test-token'), $transport);
        $request = new Request(visitorId: 'v', url: 'https://example.com/');

        $this->assertTrue($client->pageViewQuietly($request));
        $this->assertFalse($client->pageViewQuietly($request));
        $this->assertFalse($client->pageViewQuietly($request));
    }

    public function testRequiresAVisitorAndAUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Request(visitorId: '', url: 'https://example.com/');
    }
}
