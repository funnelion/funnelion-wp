<?php

declare(strict_types=1);

namespace Funnelion\Exception;

/**
 * 422 Unprocessable Entity — Funnelion rejected the request payload as
 * malformed. The original `details` map from the API response is
 * preserved on $errors so callers can surface field-level messages.
 */
final class ValidationException extends FunnelionException
{
    /**
     * @param  array<string, mixed>  $errors
     */
    public function __construct(
        string $message,
        public readonly array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 422, $previous);
    }
}
