<?php

declare(strict_types=1);

namespace Funnelion\Resolve;

/**
 * One swap zone in a resolve response. Field names mirror the API
 * response documented in docs/api-v1-resolve.md.
 *
 * `$address` can be null when the matched pool's exhaustion policy is
 * "refuse" — the caller should render the page's static default
 * in that case (or omit the zone entirely).
 *
 * `$selectors` is the zone's list of CSS selectors, present on the
 * server-side resolve response so a server-side adapter can locate and
 * rewrite the target elements itself (as an alternative to the
 * `data-funnelion` marker contract). Empty when the response carries
 * none.
 *
 * @property list<string> $selectors
 */
final class SwapZone
{
    /**
     * @param  list<string>  $selectors
     */
    public function __construct(
        public readonly ?string $name,
        public readonly string $channelKind,
        public readonly ?string $address,
        public readonly ?string $sourceLabel,
        public readonly ?int $poolId,
        public readonly ?string $maskPattern,
        public readonly ?int $matchedRuleId,
        public readonly ?string $startToken,
        public readonly array $selectors = [],
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        return new self(
            name: isset($raw['name']) && is_string($raw['name']) ? $raw['name'] : null,
            channelKind: isset($raw['channel_kind']) && is_string($raw['channel_kind'])
                ? $raw['channel_kind']
                : 'phone',
            address: isset($raw['address']) && is_string($raw['address']) ? $raw['address'] : null,
            sourceLabel: isset($raw['source_label']) && is_string($raw['source_label'])
                ? $raw['source_label']
                : null,
            poolId: isset($raw['pool_id']) && is_int($raw['pool_id']) ? $raw['pool_id'] : null,
            maskPattern: isset($raw['mask_pattern']) && is_string($raw['mask_pattern'])
                ? $raw['mask_pattern']
                : null,
            matchedRuleId: isset($raw['matched_rule_id']) && is_int($raw['matched_rule_id'])
                ? $raw['matched_rule_id']
                : null,
            startToken: isset($raw['start_token']) && is_string($raw['start_token'])
                ? $raw['start_token']
                : null,
            selectors: isset($raw['selectors']) && is_array($raw['selectors'])
                ? array_values(array_filter($raw['selectors'], 'is_string'))
                : [],
        );
    }
}
