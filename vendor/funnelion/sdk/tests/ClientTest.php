<?php

declare(strict_types=1);

namespace Funnelion\Tests;

use Funnelion\Client;
use Funnelion\Config;
use Funnelion\Exception\AuthenticationException;
use Funnelion\Exception\FunnelionException;
use Funnelion\Exception\NetworkException;
use Funnelion\Exception\RateLimitException;
use Funnelion\Exception\ServerException;
use Funnelion\Exception\TimeoutException;
use Funnelion\Exception\ValidationException;
use Funnelion\Http\HttpResponse;
use Funnelion\Http\MockTransport;
use Funnelion\Resolve\Request;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    public function testResolveHappyPath(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(200, json_encode([
            'session_id' => 17,
            'visitor_id' => '90ccd5c1-test',
            'matched_rule_id' => 4,
            'swap_zones' => [
                [
                    'name' => 'Header phone',
                    'channel_kind' => 'phone',
                    'address' => '37060000123',
                    'source_label' => 'Facebook',
                    'pool_id' => 2,
                    'mask_pattern' => null,
                    'matched_rule_id' => 4,
                    'start_token' => null,
                ],
            ],
        ]) ?: ''));

        $client = new Client(new Config(siteToken: 'test-token'), $transport);
        $response = $client->resolve(new Request(
            url: 'https://example.com/?utm_source=facebook',
            ip: '203.0.113.42',
        ));

        $this->assertSame(17, $response->sessionId);
        $this->assertSame('90ccd5c1-test', $response->visitorId);
        $this->assertCount(1, $response->swapZones);

        $zone = $response->swapZone('Header phone');
        $this->assertNotNull($zone);
        $this->assertSame('37060000123', $zone->address);
        $this->assertSame('Facebook', $zone->sourceLabel);

        // Outgoing request shape sanity check.
        $sent = $transport->sentRequests[0];
        $this->assertSame('POST', $sent['method']);
        $this->assertSame('https://dash.funnelion.ai/api/v1/resolve', $sent['uri']);
        $this->assertSame('Bearer test-token', $sent['headers']['Authorization']);
        $this->assertSame('application/json', $sent['headers']['Content-Type']);

        $payload = json_decode($sent['body'], true);
        $this->assertSame('https://example.com/?utm_source=facebook', $payload['url']);
        $this->assertSame('203.0.113.42', $payload['ip']);
        $this->assertArrayNotHasKey('referrer', $payload);
        $this->assertArrayNotHasKey('visitor_id', $payload);
    }

    public function testResolvePassesOptionalFieldsWhenSet(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(200, json_encode(['swap_zones' => []]) ?: ''));

        $client = new Client(new Config(siteToken: 'tk'), $transport);
        $client->resolve(new Request(
            url: 'https://example.com/',
            ip: '203.0.113.42',
            referrer: 'https://google.com/',
            userAgent: 'Mozilla/5.0',
            visitorId: 'prev-uuid',
        ));

        $payload = json_decode($transport->sentRequests[0]['body'], true);
        $this->assertSame('https://google.com/', $payload['referrer']);
        $this->assertSame('Mozilla/5.0', $payload['user_agent']);
        $this->assertSame('prev-uuid', $payload['visitor_id']);
    }

    public function testResolveMaps401ToAuthenticationException(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(401, '{"error":"unknown_token"}'));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessageMatches('/unknown_token/');

        $client = new Client(new Config(siteToken: 'wrong'), $transport);
        $client->resolve(new Request(url: 'https://x/', ip: '1.2.3.4'));
    }

    public function testResolveMaps422ToValidationException(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(422, '{"error":"invalid_payload","details":{"ip":["required"]}}'));

        $client = new Client(new Config(siteToken: 'tk'), $transport);

        try {
            $client->resolve(new Request(url: 'https://x/', ip: '1.2.3.4'));
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->statusCode);
            $this->assertArrayHasKey('ip', $e->errors);
        }
    }

    public function testResolveMaps429ToRateLimitException(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(429, ''));

        $this->expectException(RateLimitException::class);
        $client = new Client(new Config(siteToken: 'tk'), $transport);
        $client->resolve(new Request(url: 'https://x/', ip: '1.2.3.4'));
    }

    public function testResolveMaps500ToServerException(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(503, ''));

        try {
            $client = new Client(new Config(siteToken: 'tk'), $transport);
            $client->resolve(new Request(url: 'https://x/', ip: '1.2.3.4'));
            $this->fail('expected ServerException');
        } catch (ServerException $e) {
            $this->assertSame(503, $e->statusCode);
        }
    }

    public function testResolveOrNullSwallowsTimeout(): void
    {
        $transport = new MockTransport();
        $transport->push(new TimeoutException('boom'));

        $client = new Client(new Config(siteToken: 'tk'), $transport);
        $response = $client->resolveOrNull(new Request(url: 'https://x/', ip: '1.2.3.4'));

        $this->assertNull($response);
    }

    public function testResolveOrNullSwallowsAuthError(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(401, '{"error":"unknown_token"}'));

        $client = new Client(new Config(siteToken: 'tk'), $transport);
        $this->assertNull($client->resolveOrNull(new Request(url: 'https://x/', ip: '1.2.3.4')));
    }

    public function testResolveOrNullStillReturnsResponseOnSuccess(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(200, json_encode(['swap_zones' => []]) ?: ''));

        $client = new Client(new Config(siteToken: 'tk'), $transport);
        $response = $client->resolveOrNull(new Request(url: 'https://x/', ip: '1.2.3.4'));

        $this->assertNotNull($response);
    }

    public function testNetworkErrorOnMalformedJson(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(200, 'not json'));

        $this->expectException(NetworkException::class);
        $client = new Client(new Config(siteToken: 'tk'), $transport);
        $client->resolve(new Request(url: 'https://x/', ip: '1.2.3.4'));
    }

    public function testEveryFailureIsCatchableAsFunnelionException(): void
    {
        // The "must always work" pattern relies on a single catch type.
        $transport = new MockTransport();
        $transport->push(new HttpResponse(401, '{"error":"unknown_token"}'));

        $client = new Client(new Config(siteToken: 'tk'), $transport);

        try {
            $client->resolve(new Request(url: 'https://x/', ip: '1.2.3.4'));
            $this->fail('expected exception');
        } catch (FunnelionException $e) {
            $this->assertInstanceOf(AuthenticationException::class, $e);
        }
    }
}
