<?php

declare(strict_types=1);

namespace Funnelion\Http;

use Funnelion\Exception\NetworkException;
use Funnelion\Exception\TimeoutException;

/**
 * HTTP transport abstraction. The default implementation
 * (Funnelion\Http\CurlTransport) uses ext-curl; consumers who want
 * to share an HTTP client with the rest of their app can implement
 * this interface against their own client.
 *
 * Transport-level errors (timeout, DNS, connection refused, TLS) must
 * be thrown as TimeoutException / NetworkException. HTTP-level errors
 * (4xx, 5xx) are returned as-is on the response — the Client decides
 * how to map them.
 *
 * @phpstan-type Headers array<string, string>
 */
interface Transport
{
    /**
     * @param  array<string, string>  $headers
     *
     * @throws TimeoutException
     * @throws NetworkException
     */
    public function send(
        string $method,
        string $uri,
        array $headers,
        string $body,
        float $timeoutSeconds,
    ): HttpResponse;
}
