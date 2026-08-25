<?php

declare(strict_types=1);

namespace Funnelion\Exception;

/**
 * Base type for every exception thrown by the SDK. Consumers can catch
 * this single class to fall back gracefully without distinguishing
 * individual failure modes — see Funnelion\Client::resolveOrNull()
 * for the catch-all variant that returns null instead of throwing.
 */
class FunnelionException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }
}
