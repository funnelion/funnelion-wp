<?php

declare(strict_types=1);

namespace Funnelion\Exception;

/**
 * 5xx response from Funnelion. Transient by definition; render the
 * page's static default and let the next request try again.
 */
final class ServerException extends FunnelionException
{
}
