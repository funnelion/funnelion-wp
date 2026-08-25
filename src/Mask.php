<?php

declare(strict_types=1);

namespace FunnelionWP;

/**
 * Display-mask formatter — a byte-for-byte port of the JS snippet's
 * applyMask (public/track.js) so server-side and client-side render the
 * same string. Each '#' consumes one digit; a literal digit in the
 * pattern matches (and consumes) an identical leading digit; any other
 * character passes through verbatim.
 *
 *   apply('+370 ### ## ###', '37066188604') === '+370 661 88 604'
 */
final class Mask
{
    public static function apply(?string $pattern, string $digits): string
    {
        if ($pattern === null || $pattern === '') {
            return $digits;
        }

        $cleaned = preg_replace('/\D/', '', $digits) ?? '';
        $len = strlen($cleaned);
        $i = 0;
        $out = '';

        for ($p = 0, $plen = strlen($pattern); $p < $plen; $p++) {
            $ch = $pattern[$p];
            if ($ch === '#') {
                $out .= $i < $len ? $cleaned[$i++] : '#';
            } elseif ($ch >= '0' && $ch <= '9') {
                if ($i < $len && $cleaned[$i] === $ch) {
                    $i++;
                }
                $out .= $ch;
            } else {
                $out .= $ch;
            }
        }

        return $out;
    }
}
