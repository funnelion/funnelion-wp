<?php

declare(strict_types=1);

namespace Funnelion\Html;

use Funnelion\Resolve\Response;

/**
 * Replaces swap-zone markers in HTML with resolved channel addresses.
 *
 * The contract for HTML markup:
 *
 *     <span data-funnelion="Header phone">+370 626 33611</span>
 *     <a href="tel:+37062633611" data-funnelion="Header phone">+370 626 33611</a>
 *     <a href="mailto:hi@example.com" data-funnelion="Sales email">hi@example.com</a>
 *
 * The element must be a **leaf** (only text content; no nested
 * elements). Its inner text is replaced with the resolved address.
 * When the element is an `<a>` whose `href` starts with `tel:` or
 * `mailto:`, the href is rewritten to point at the resolved address
 * too — so `tel:` links dial the assigned tracking number.
 *
 * Zones with no `name` (defensive — shouldn't happen on a properly
 * configured Site) or no `address` (pool exhausted with "refuse"
 * policy) are skipped, leaving the page's static default in place.
 * This is the "must always work" pattern: a bad resolver state never
 * deletes the customer's hardcoded fallback.
 */
final class ZoneSwapper
{
    public function swap(string $html, Response $response): string
    {
        foreach ($response->swapZones as $zone) {
            if ($zone->name === null || $zone->address === null) {
                continue;
            }
            $html = $this->replaceZone($html, $zone->name, $zone->address);
        }

        return $html;
    }

    private function replaceZone(string $html, string $name, string $address): string
    {
        $escapedName = preg_quote($name, '#');
        $encodedAddress = htmlspecialchars($address, ENT_QUOTES, 'UTF-8');

        // Match the open tag (any element) carrying data-funnelion="<name>",
        // then text-only inner content (no nested tags), then the matching
        // close tag. The (?2) backreference forces tag-name equality.
        $pattern = '#(<([a-zA-Z][a-zA-Z0-9]*)\b[^>]*?\sdata-funnelion="'.$escapedName.'"[^>]*>)([^<]*)(</\2\s*>)#s';

        $replaced = preg_replace_callback(
            $pattern,
            function (array $matches) use ($encodedAddress, $address): string {
                $openTag = $matches[1];

                if (strtolower($matches[2]) === 'a') {
                    $openTag = $this->rewriteAnchorHref($openTag, $address);
                }

                return $openTag.$encodedAddress.$matches[4];
            },
            $html,
        );

        return $replaced ?? $html;
    }

    /**
     * Rewrite href="tel:..." and href="mailto:..." to point at the
     * resolved address. Other schemes (http, https, fragment, etc.)
     * are left alone — they're navigation, not contact.
     */
    private function rewriteAnchorHref(string $openTag, string $address): string
    {
        $encodedAddress = htmlspecialchars($address, ENT_QUOTES, 'UTF-8');

        return (string) preg_replace_callback(
            '/\shref\s*=\s*"(tel|mailto):[^"]*"/i',
            fn (array $m): string => ' href="'.strtolower($m[1]).':'.$encodedAddress.'"',
            $openTag,
        );
    }
}
