<?php

declare(strict_types=1);

namespace Funnelion\Exception;

/**
 * 401 Unauthorized — server_side_token missing, malformed, or unknown
 * to Funnelion. Almost always a configuration error; not retryable.
 */
final class AuthenticationException extends FunnelionException
{
}
