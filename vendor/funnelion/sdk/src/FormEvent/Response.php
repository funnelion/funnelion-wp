<?php

declare(strict_types=1);

namespace Funnelion\FormEvent;

/**
 * Result of Funnelion\Client::formEvent(). Mirrors the JSON returned by
 * POST /api/v1/form-event.
 *
 * - $leadEventId is the Funnelion lead-event row that was created; use
 *   it for log correlation back to the Funnelion dashboard.
 * - $attributionStatus is `attributed` when the visitor_id matched an
 *   active session and `no_match` when it didn't (lead-event was still
 *   created — Funnelion records every submission, attribution can be
 *   reviewed later).
 * - $reason is populated only on short-circuit responses (e.g.
 *   "ip_blocked"); leadEventId is null in that case.
 */
final class Response
{
    public function __construct(
        public readonly string $status,
        public readonly ?int $leadEventId,
        public readonly ?string $attributionStatus,
        public readonly ?string $reason = null,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            status: isset($raw['status']) && is_string($raw['status']) ? $raw['status'] : 'received',
            leadEventId: isset($raw['lead_event_id']) && is_int($raw['lead_event_id'])
                ? $raw['lead_event_id']
                : null,
            attributionStatus: isset($raw['attribution_status']) && is_string($raw['attribution_status'])
                ? $raw['attribution_status']
                : null,
            reason: isset($raw['reason']) && is_string($raw['reason']) ? $raw['reason'] : null,
        );
    }
}
