<?php

declare(strict_types=1);

namespace Funnelion\Exception;

/**
 * 429 Too Many Requests — token exceeded its per-minute quota. The
 * caller should back off; rendering the page's static default while
 * the limit clears is the recommended pattern.
 */
final class RateLimitException extends FunnelionException
{
}
