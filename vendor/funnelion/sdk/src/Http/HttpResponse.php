<?php

declare(strict_types=1);

namespace Funnelion\Http;

/**
 * Low-level HTTP response returned by a Transport. Distinct from
 * Funnelion\Resolve\Response (the high-level resolve result) — this
 * carries the raw status + body before the Client interprets them.
 */
final class HttpResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
    ) {}
}
