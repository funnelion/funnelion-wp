<?php

declare(strict_types=1);

namespace FunnelionWP;

use Funnelion\Resolve\Response;
use Funnelion\Resolve\SwapZone;

/**
 * Server-side, selector-based swap for the resolve response.
 *
 * Unlike the SDK's marker-based ZoneSwapper (which keys off
 * data-funnelion="<name>"), this locates the target elements by the
 * zone's own CSS selectors — so a site needs no per-element markup. It
 * deliberately uses targeted regex on anchor elements rather than a full
 * DOM round-trip: a DOMDocument reparse/serialise of a whole live page
 * (WooCommerce, page builders) risks reordering attributes, dropping
 * whitespace, or rewriting entities. This only ever rewrites the anchors
 * it matches and leaves every other byte untouched.
 *
 * Supported selectors (the shape Funnelion emits for phone/email zones):
 *   a[href="tel:…"]   a[href^="tel:"]   a[href$="…"]   a[href*="…"]
 *   a[href="mailto:…"] and the ^= / $= / *= variants
 * Selectors it can't parse are skipped (fail-open — the page keeps its
 * hardcoded fallback). Elements with non-text children don't match the
 * leaf pattern and are likewise left as-is.
 */
final class SelectorSwapper
{
    public function swap(string $html, Response $response): string
    {
        foreach ($response->swapZones as $zone) {
            if (! $zone instanceof SwapZone) {
                continue;
            }
            if ($zone->address === null || $zone->address === '' || $zone->selectors === []) {
                continue;
            }

            $display = Mask::apply($zone->maskPattern, $zone->address);
            $newHref = $this->hrefFor($zone->channelKind, $zone->address);

            foreach ($zone->selectors as $selector) {
                $html = $this->applyAnchorSelector($html, (string) $selector, $newHref, $display);
            }
        }

        return $html;
    }

    /** Build the dialable/mailable href for the resolved address. */
    private function hrefFor(string $channelKind, string $address): string
    {
        if ($channelKind === 'email') {
            return 'mailto:'.$address;
        }
        // phone (default): normalise to E.164-ish tel: with a single leading +.
        $digits = preg_replace('/\D/', '', $address) ?? $address;

        return 'tel:+'.$digits;
    }

    /**
     * Rewrite anchors matched by an `a[href<op>"value"]` selector: swap the
     * matched href to $newHref and the leaf text to $display. No-op for any
     * selector this doesn't understand.
     */
    private function applyAnchorSelector(string $html, string $selector, string $newHref, string $display): string
    {
        $valueRegex = $this->hrefValueRegex($selector);
        if ($valueRegex === null) {
            Support::log("selector not supported, skipped: {$selector}");

            return $html;
        }

        $hrefNew = htmlspecialchars($newHref, ENT_QUOTES, 'UTF-8');
        $textNew = htmlspecialchars($display, ENT_QUOTES, 'UTF-8');

        // Match an <a> whose open tag carries the matching href, with
        // text-only inner content (leaf), then its close tag.
        $pattern = '#<a\b(?=[^>]*\bhref\s*=\s*"'.$valueRegex.'")([^>]*)>([^<]*)(</a\s*>)#is';

        $result = preg_replace_callback(
            $pattern,
            function (array $m) use ($valueRegex, $hrefNew, $textNew): string {
                // Rewrite only the matching href attribute inside the open tag.
                $attrs = preg_replace_callback(
                    '#(\bhref\s*=\s*")'.$valueRegex.'(")#i',
                    static fn (array $h): string => $h[1].$hrefNew.$h[2],
                    $m[1],
                    1,
                ) ?? $m[1];

                return '<a'.$attrs.'>'.$textNew.$m[3];
            },
            $html,
        );

        return $result ?? $html;
    }

    /**
     * Parse `a[href<op>"value"]` and return a regex matching the href
     * attribute VALUE (no capturing groups), or null if unsupported.
     */
    private function hrefValueRegex(string $selector): ?string
    {
        if (! preg_match('#^\s*a\s*\[\s*href\s*([\^\$\*]?=)\s*"([^"]*)"\s*\]\s*$#i', $selector, $m)) {
            return null;
        }

        $op = $m[1];
        $val = preg_quote($m[2], '#');

        return match ($op) {
            '=' => $val,
            '^=' => $val.'[^"]*',
            '$=' => '[^"]*'.$val,
            '*=' => '[^"]*'.$val.'[^"]*',
            default => null,
        };
    }
}
