<?php

declare(strict_types=1);

namespace Funnelion;

/**
 * SDK configuration. All fields except $siteToken have sensible defaults
 * tuned for the "must always work" pattern documented in the README —
 * notably a short 500ms timeout so a slow Funnelion API never blocks
 * the customer's page render.
 */
final class Config
{
    public function __construct(
        public readonly string $siteToken,
        public readonly string $baseUri = 'https://dash.funnelion.ai',
        public readonly float $timeoutSeconds = 0.5,
        public readonly string $userAgent = 'funnelion-php/'.Client::VERSION,
    ) {
        if ($siteToken === '') {
            throw new \InvalidArgumentException('site_token must not be empty.');
        }
        if ($timeoutSeconds <= 0) {
            throw new \InvalidArgumentException('timeout_seconds must be positive.');
        }
    }
}
