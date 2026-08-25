<?php

declare(strict_types=1);

namespace Funnelion\Exception;

/**
 * Transport-level failure — DNS lookup failed, connection refused, TLS
 * error, response not parseable as JSON, etc. Funnelion never produced
 * a meaningful HTTP response.
 */
final class NetworkException extends FunnelionException
{
}
