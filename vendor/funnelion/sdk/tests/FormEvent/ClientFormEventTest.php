<?php

declare(strict_types=1);

namespace Funnelion\Tests\FormEvent;

use Funnelion\Client;
use Funnelion\Config;
use Funnelion\Exception\AuthenticationException;
use Funnelion\Exception\TimeoutException;
use Funnelion\Exception\ValidationException;
use Funnelion\FormEvent\Request;
use Funnelion\Http\HttpResponse;
use Funnelion\Http\MockTransport;
use PHPUnit\Framework\TestCase;

final class ClientFormEventTest extends TestCase
{
    public function testHappyPathReturnsAttributedLeadEvent(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(200, json_encode([
            'status' => 'received',
            'lead_event_id' => 4711,
            'attribution_status' => 'attributed',
        ]) ?: ''));

        $client = new Client(new Config(siteToken: 'tk'), $transport);
        $response = $client->formEvent(new Request(
            ip: '203.0.113.42',
            fields: ['email' => 'jane@example.com', 'name' => 'Jane'],
            visitorId: 'abc-uuid',
        ));

        $this->assertSame('received', $response->status);
        $this->assertSame(4711, $response->leadEventId);
        $this->assertSame('attributed', $response->attributionStatus);
        $this->assertNull($response->reason);

        $sent = $transport->sentRequests[0];
        $this->assertSame('POST', $sent['method']);
        $this->assertSame('https://dash.funnelion.ai/api/v1/form-event', $sent['uri']);
        $this->assertSame('Bearer tk', $sent['headers']['Authorization']);

        $payload = json_decode($sent['body'], true);
        $this->assertSame('203.0.113.42', $payload['ip']);
        $this->assertSame('abc-uuid', $payload['visitor_id']);
        $this->assertSame(['email' => 'jane@example.com', 'name' => 'Jane'], $payload['fields']);
    }

    public function testNoMatchAttributionStatusIsSurfaced(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(200, json_encode([
            'status' => 'received',
            'lead_event_id' => 4712,
            'attribution_status' => 'no_match',
        ]) ?: ''));

        $client = new Client(new Config(siteToken: 'tk'), $transport);
        $response = $client->formEvent(new Request(
            ip: '1.2.3.4',
            fields: ['email' => 'x@example.com'],
        ));

        $this->assertSame('no_match', $response->attributionStatus);
    }

    public function testIpBlockedShortCircuitSurfacesReason(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(200, json_encode([
            'lead_event_id' => null,
            'attribution_status' => null,
            'reason' => 'ip_blocked',
        ]) ?: ''));

        $client = new Client(new Config(siteToken: 'tk'), $transport);
        $response = $client->formEvent(new Request(
            ip: '198.51.100.5',
            fields: ['email' => 'x@example.com'],
        ));

        $this->assertSame('ip_blocked', $response->reason);
        $this->assertNull($response->leadEventId);
    }

    public function testEmptyFieldsRejectedClientSide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Request(ip: '1.2.3.4', fields: []);
    }

    public function testEmptyIpRejectedClientSide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Request(ip: '', fields: ['email' => 'x@example.com']);
    }

    public function testRequestToArrayOmitsNullOptionalFields(): void
    {
        $r = new Request(ip: '1.2.3.4', fields: ['email' => 'x@example.com']);

        $this->assertSame(['ip' => '1.2.3.4', 'fields' => ['email' => 'x@example.com']], $r->toArray());
    }

    public function testRequestToArrayIncludesEveryOptionalFieldWhenProvided(): void
    {
        $r = new Request(
            ip: '1.2.3.4',
            fields: ['email' => 'x@example.com'],
            url: 'https://example.com/',
            referrer: 'https://google.com/',
            userAgent: 'UA',
            visitorId: 'uuid',
            formId: 7,
            formActionUrl: 'https://example.com/api/contact',
            language: 'lt',
        );

        $this->assertSame([
            'ip' => '1.2.3.4',
            'fields' => ['email' => 'x@example.com'],
            'url' => 'https://example.com/',
            'referrer' => 'https://google.com/',
            'user_agent' => 'UA',
            'visitor_id' => 'uuid',
            'form_id' => 7,
            'form_action_url' => 'https://example.com/api/contact',
            'language' => 'lt',
        ], $r->toArray());
    }

    public function testFormEventMaps401ToAuthenticationException(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(401, '{"error":"unknown_token"}'));

        $client = new Client(new Config(siteToken: 'wrong'), $transport);

        $this->expectException(AuthenticationException::class);
        $client->formEvent(new Request(ip: '1.2.3.4', fields: ['email' => 'x@x']));
    }

    public function testFormEventMaps422ToValidationException(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(422, '{"error":"invalid_payload","details":{"fields":["required"]}}'));

        $client = new Client(new Config(siteToken: 'tk'), $transport);

        try {
            $client->formEvent(new Request(ip: '1.2.3.4', fields: ['email' => 'x@x']));
            $this->fail('expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('fields', $e->errors);
        }
    }

    public function testFormEventOrNullSwallowsTimeout(): void
    {
        $transport = new MockTransport();
        $transport->push(new TimeoutException('boom'));

        $client = new Client(new Config(siteToken: 'tk'), $transport);
        $this->assertNull($client->formEventOrNull(new Request(
            ip: '1.2.3.4',
            fields: ['email' => 'x@x'],
        )));
    }

    public function testFormEventOrNullSwallowsAuthError(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(401, '{"error":"unknown_token"}'));

        $client = new Client(new Config(siteToken: 'tk'), $transport);
        $this->assertNull($client->formEventOrNull(new Request(
            ip: '1.2.3.4',
            fields: ['email' => 'x@x'],
        )));
    }

    public function testFormEventOrNullReturnsResponseOnSuccess(): void
    {
        $transport = new MockTransport();
        $transport->push(new HttpResponse(200, json_encode([
            'status' => 'received',
            'lead_event_id' => 1,
            'attribution_status' => 'attributed',
        ]) ?: ''));

        $client = new Client(new Config(siteToken: 'tk'), $transport);
        $response = $client->formEventOrNull(new Request(
            ip: '1.2.3.4',
            fields: ['email' => 'x@x'],
        ));

        $this->assertNotNull($response);
        $this->assertSame(1, $response->leadEventId);
    }
}
