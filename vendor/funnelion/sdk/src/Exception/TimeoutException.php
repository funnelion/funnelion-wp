<?php

declare(strict_types=1);

namespace Funnelion\Exception;

/**
 * Request exceeded the configured timeout. The most common failure mode
 * on a healthy network; treat it as a signal to render your page's
 * static default.
 */
final class TimeoutException extends FunnelionException
{
}
