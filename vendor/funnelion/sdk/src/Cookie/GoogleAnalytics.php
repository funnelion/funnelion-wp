<?php

declare(strict_types=1);

namespace Funnelion\Cookie;

/**
 * Reads the visitor's Google Analytics identifiers out of the cookies
 * gtag.js writes on your own domain.
 *
 * Why the SDK cares: Funnelion fires GA4 Measurement Protocol events for
 * the inbound calls and emails it attributes (`phone_call_received_*`,
 * `email_received_*`). GA4 only folds such an event into the session the
 * visitor is actually in — inheriting its source / medium / campaign —
 * when the event carries the client id and session id gtag itself
 * issued. Sent under any other id, the event lands on a phantom session
 * with no acquisition data and shows up under GA4's **Unassigned**
 * channel group. Passing these two values on `Resolve\Request` is what
 * keeps that from happening.
 *
 *  - `GoogleAnalytics::clientIdFromGlobals()` — from the `_ga` cookie
 *  - `GoogleAnalytics::sessionIdFromGlobals('G-ABC123')` — from the
 *    per-stream `_ga_ABC123` cookie
 *
 * Both return null when the cookie is absent, which is the normal state
 * when the visitor declined analytics consent or the page runs no GA
 * tag — and also on the very first request of a visit, because gtag
 * writes its cookies only once the page has loaded. Send them anyway on
 * every call: Funnelion stores the newest non-null value it is given, so
 * the ids attach from the visitor's second request onwards.
 *
 * Both cookies are first-party and readable from PHP; the SDK never
 * needs the gtag JS API.
 */
final class GoogleAnalytics
{
    public const CLIENT_ID_COOKIE = '_ga';

    public const SESSION_COOKIE_PREFIX = '_ga_';

    /**
     * The GA4 client id, e.g. `1699887766.1750000000`.
     *
     * The `_ga` cookie holds `GA1.1.1699887766.1750000000`; the client id
     * is the last two dot-separated pieces. The prefix's second digit is
     * the cookie's domain depth (`GA1.1`, `GA1.2`, … depending on how
     * many labels the host has), so we read from the end rather than
     * assuming a fixed offset.
     *
     * @param  array<string, mixed>|null  $cookies  defaults to $_COOKIE
     */
    public static function clientIdFromGlobals(?array $cookies = null): ?string
    {
        $cookies ??= $_COOKIE;
        $raw = $cookies[self::CLIENT_ID_COOKIE] ?? null;
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $parts = explode('.', $raw);
        if (count($parts) < 4) {
            return null;
        }
        $clientId = implode('.', array_slice($parts, -2));

        return preg_match('/^\d+\.\d+$/', $clientId) === 1 ? $clientId : null;
    }

    /**
     * The GA4 session id — the unix timestamp at which GA started the
     * visitor's current session, e.g. `1750000000`.
     *
     * Lives in a per-data-stream cookie named after the measurement id
     * without its `G-` prefix: measurement id `G-ABC123` → cookie
     * `_ga_ABC123`. Pass the measurement id in either form.
     *
     * Two value formats are in the wild and both are handled:
     *
     *   GS2.1.s1750000000$o3$g1$t1750000090$j60$l0$h0   (current)
     *   GS1.1.1750000000.3.0.1750000090.0.0.0           (legacy)
     *
     * @param  string  $measurementId  e.g. `G-ABC123` or `ABC123`
     * @param  array<string, mixed>|null  $cookies  defaults to $_COOKIE
     */
    public static function sessionIdFromGlobals(string $measurementId, ?array $cookies = null): ?string
    {
        $stream = trim($measurementId);
        if (str_starts_with($stream, 'G-')) {
            $stream = substr($stream, 2);
        }
        if ($stream === '') {
            return null;
        }

        $cookies ??= $_COOKIE;
        $raw = $cookies[self::SESSION_COOKIE_PREFIX.$stream] ?? null;
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        return self::parseSessionCookie($raw);
    }

    /**
     * Extract the session id from a `_ga_<stream>` cookie value.
     * Exposed for callers that already hold the raw value (e.g. read
     * from a framework's cookie bag rather than $_COOKIE).
     */
    public static function parseSessionCookie(string $value): ?string
    {
        if (preg_match('/^GS\d+\.\d+\.s(\d+)/', $value, $m) === 1) {
            return $m[1];
        }

        $parts = explode('.', $value);
        if (count($parts) >= 3 && preg_match('/^\d+$/', $parts[2]) === 1) {
            return $parts[2];
        }

        return null;
    }
}
