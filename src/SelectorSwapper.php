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
                $html = $this->applyAnchorSelector($html, (string) $selector, $zone->channelKind, $newHref, $display);
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
     * matched href to $newHref and the visible address to $display. No-op
     * for any selector this doesn't understand.
     *
     * A text-only anchor has its whole text replaced, as before. An anchor
     * with markup inside (an icon <span>/<img>, the number in a <p>) keeps
     * that markup: only the text runs and title/alt/aria-label values that
     * spell the anchor's *original* address are rewritten, so the icon and
     * styling survive and unrelated words are never touched.
     */
    private function applyAnchorSelector(string $html, string $selector, string $channelKind, string $newHref, string $display): string
    {
        $valueRegex = $this->hrefValueRegex($selector);
        if ($valueRegex === null) {
            Support::log("selector not supported, skipped: {$selector}");

            return $html;
        }

        $hrefNew = htmlspecialchars($newHref, ENT_QUOTES, 'UTF-8');
        $textNew = htmlspecialchars($display, ENT_QUOTES, 'UTF-8');

        // Match an <a> whose open tag carries the matching href, its inner
        // content (anchors can't nest, so the first </a> closes it), then
        // its close tag.
        $pattern = '#<a\b(?=[^>]*\bhref\s*=\s*"'.$valueRegex.'")([^>]*)>(.*?)(</a\s*>)#is';

        $result = preg_replace_callback(
            $pattern,
            function (array $m) use ($valueRegex, $channelKind, $hrefNew, $textNew): string {
                $oldHref = preg_match('#\bhref\s*=\s*"([^"]*)"#i', $m[1], $h) ? html_entity_decode($h[1], ENT_QUOTES, 'UTF-8') : '';
                $matcher = $this->addressMatcher($channelKind, $oldHref);

                // Rewrite only the matching href attribute inside the open tag.
                $attrs = preg_replace_callback(
                    '#(\bhref\s*=\s*")'.$valueRegex.'(")#i',
                    static fn (array $h): string => $h[1].$hrefNew.$h[2],
                    $m[1],
                    1,
                ) ?? $m[1];
                if ($matcher !== null) {
                    $attrs = $this->replaceInLabelAttributes($attrs, $matcher, $textNew);
                }

                $inner = $m[2];
                if ($matcher !== null) {
                    $replaced = 0;
                    $swapped = $this->replaceInContent($inner, $matcher, $textNew, $replaced);
                    if ($replaced > 0) {
                        return '<a'.$attrs.'>'.$swapped.$m[3];
                    }
                }

                // Text-only anchor that doesn't spell its address (or an
                // address we couldn't read): replace the whole text, the
                // original leaf behaviour. Markup we can't place a number
                // in is left alone — the href swap alone still routes calls.
                if (strpos($inner, '<') === false) {
                    return '<a'.$attrs.'>'.$textNew.$m[3];
                }

                return '<a'.$attrs.'>'.$inner.$m[3];
            },
            $html,
        );

        return $result ?? $html;
    }

    /**
     * Build a callable that rewrites every spelling of the anchor's original
     * address in a plain-text string, or null when the href carries none.
     *
     * Phones compare by the last 8 digits, so "+370 678 39400", "+37067839400"
     * and the national "8 678 39400" all match a tel:+37067839400 href.
     * Emails compare case-insensitively on the mailto address (query dropped).
     *
     * @return (callable(string, string, int&): string)|null
     */
    private function addressMatcher(string $channelKind, string $oldHref): ?callable
    {
        if ($channelKind === 'email') {
            $address = rawurldecode((string) preg_replace('#^mailto:#i', '', explode('?', $oldHref, 2)[0]));
            if (strpos($address, '@') === false) {
                return null;
            }
            $regex = '#'.preg_quote($address, '#').'#iu';

            return static function (string $text, string $new, int &$count) use ($regex): string {
                $out = preg_replace($regex, $new, $text, -1, $n);
                $count += $n;

                return $out ?? $text;
            };
        }

        $oldDigits = preg_replace('/\D/', '', $oldHref) ?? '';
        if (strlen($oldDigits) < 8) {
            return null;
        }
        $tail = substr($oldDigits, -8);

        return static function (string $text, string $new, int &$count) use ($tail): string {
            return preg_replace_callback(
                '~\+*\d(?:[\d\s().\-\x{00A0}]|&nbsp;|&#160;){6,}\d~u',
                static function (array $r) use ($tail, $new, &$count): string {
                    $digits = preg_replace('/\D/', '', $r[0]) ?? '';
                    if (strlen($digits) >= 8 && substr($digits, -8) === $tail) {
                        $count++;

                        return $new;
                    }

                    return $r[0];
                },
                $text,
            ) ?? $text;
        };
    }

    /** Rewrite the address in the text runs between tags, leaving every tag as-is. */
    private function replaceInContent(string $inner, callable $matcher, string $new, int &$count): string
    {
        $parts = preg_split('#(<[^>]*>)#', $inner, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $inner;
        }
        foreach ($parts as $i => $part) {
            if ($part === '' || $part[0] === '<') {
                $parts[$i] = $part === '' ? '' : $this->replaceInLabelAttributes($part, $matcher, $new);
                continue;
            }
            $parts[$i] = $matcher($part, $new, $count);
        }

        return implode('', $parts);
    }

    /** Rewrite the address inside title/alt/aria-label values of one tag. */
    private function replaceInLabelAttributes(string $tag, callable $matcher, string $new): string
    {
        return preg_replace_callback(
            '#(\b(?:title|alt|aria-label)\s*=\s*")([^"]*)(")#i',
            static function (array $a) use ($matcher, $new): string {
                $ignored = 0;

                return $a[1].$matcher($a[2], $new, $ignored).$a[3];
            },
            $tag,
        ) ?? $tag;
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
