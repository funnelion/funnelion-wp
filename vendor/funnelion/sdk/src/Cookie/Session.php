<?php

declare(strict_types=1);

namespace Funnelion\Cookie;

/**
 * Helpers for the funnelion_session cookie that round-trips the
 * visitor_id between the customer's server and the visitor's browser.
 *
 * The SDK does not call PHP's setcookie() for you — frameworks have
 * their own conventions (Laravel cookies queued onto the response,
 * Symfony Response headers, WordPress's hooked output). Instead we
 * expose:
 *
 *  - Session::COOKIE_NAME            — the canonical cookie name
 *  - Session::headerValue(visitorId) — a ready-to-emit Set-Cookie
 *                                       header value (HttpOnly,
 *                                       Secure, SameSite=Lax, 30d)
 *  - Session::readFromGlobals()      — pluck the value out of $_COOKIE
 *
 * Customers are responsible for visitor consent (see README); the SDK
 * does not gate cookie emission on any consent signal.
 */
final class Session
{
    public const COOKIE_NAME = 'funnelion_session';

    public const DEFAULT_MAX_AGE_SECONDS = 2_592_000; // 30 days

    /**
     * Build a Set-Cookie header *value* — i.e. the part you'd assign
     * via `header('Set-Cookie: '.$value)`, or pass to your framework's
     * cookie API.
     */
    public static function headerValue(
        string $visitorId,
        int $maxAgeSeconds = self::DEFAULT_MAX_AGE_SECONDS,
        bool $secure = true,
        string $path = '/',
        ?string $domain = null,
        string $sameSite = 'Lax',
    ): string {
        $parts = [self::COOKIE_NAME.'='.rawurlencode($visitorId)];
        $parts[] = 'Path='.$path;
        if ($domain !== null && $domain !== '') {
            $parts[] = 'Domain='.$domain;
        }
        $parts[] = 'Max-Age='.$maxAgeSeconds;
        $parts[] = 'HttpOnly';
        if ($secure) {
            $parts[] = 'Secure';
        }
        $parts[] = 'SameSite='.$sameSite;

        return implode('; ', $parts);
    }

    /**
     * Read the funnelion_session cookie value from the $_COOKIE
     * superglobal. Returns null if the cookie is absent or empty.
     *
     * @param  array<string, string>|null  $cookies  defaults to $_COOKIE
     */
    public static function readFromGlobals(?array $cookies = null): ?string
    {
        $cookies ??= $_COOKIE;
        if (! isset($cookies[self::COOKIE_NAME])) {
            return null;
        }
        $value = $cookies[self::COOKIE_NAME];
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }
}
