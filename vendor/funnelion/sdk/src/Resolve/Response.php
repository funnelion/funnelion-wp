<?php

declare(strict_types=1);

namespace Funnelion\Resolve;

/**
 * Result of Funnelion\Client::resolve(). Mirrors the API response
 * documented in docs/api-v1-resolve.md.
 *
 * `$reason` is populated only on short-circuit responses (e.g.
 * "ip_blocked") where the resolver bypassed the routing pipeline.
 */
final class Response
{
    /**
     * @param  list<SwapZone>  $swapZones
     */
    public function __construct(
        public readonly ?int $sessionId,
        public readonly ?string $visitorId,
        public readonly ?int $matchedRuleId,
        public readonly array $swapZones,
        public readonly ?string $reason = null,
    ) {}

    /**
     * Look up a swap zone by its display name. Returns null when no
     * matching zone is in the response.
     */
    public function swapZone(string $name): ?SwapZone
    {
        foreach ($this->swapZones as $zone) {
            if ($zone->name === $name) {
                return $zone;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        $zones = [];
        if (isset($raw['swap_zones']) && is_array($raw['swap_zones'])) {
            foreach ($raw['swap_zones'] as $z) {
                if (is_array($z)) {
                    $zones[] = SwapZone::fromArray($z);
                }
            }
        }

        return new self(
            sessionId: isset($raw['session_id']) && is_int($raw['session_id'])
                ? $raw['session_id']
                : null,
            visitorId: isset($raw['visitor_id']) && is_string($raw['visitor_id'])
                ? $raw['visitor_id']
                : null,
            matchedRuleId: isset($raw['matched_rule_id']) && is_int($raw['matched_rule_id'])
                ? $raw['matched_rule_id']
                : null,
            swapZones: $zones,
            reason: isset($raw['reason']) && is_string($raw['reason']) ? $raw['reason'] : null,
        );
    }
}
