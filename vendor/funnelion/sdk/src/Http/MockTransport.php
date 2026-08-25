<?php

declare(strict_types=1);

namespace Funnelion\Http;

use Funnelion\Exception\NetworkException;

/**
 * Test transport. Queue HttpResponse instances (or Throwables to be
 * raised) with push(); each send() consumes the next entry. Sent
 * requests are recorded on $sentRequests so tests can assert
 * payloads and headers.
 *
 * Not auto-loaded for production — kept in src/ so consumers can use
 * it for their own integration tests too. Cost is negligible (a few
 * KB of class metadata).
 */
final class MockTransport implements Transport
{
    /** @var list<HttpResponse|\Throwable> */
    private array $queue = [];

    /**
     * @var list<array{method: string, uri: string, headers: array<string, string>, body: string, timeout: float}>
     */
    public array $sentRequests = [];

    public function push(HttpResponse|\Throwable $next): self
    {
        $this->queue[] = $next;

        return $this;
    }

    public function send(
        string $method,
        string $uri,
        array $headers,
        string $body,
        float $timeoutSeconds,
    ): HttpResponse {
        $this->sentRequests[] = [
            'method' => $method,
            'uri' => $uri,
            'headers' => $headers,
            'body' => $body,
            'timeout' => $timeoutSeconds,
        ];

        if (count($this->queue) === 0) {
            throw new NetworkException('MockTransport: no queued response for this request.');
        }

        $next = array_shift($this->queue);

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }
}
