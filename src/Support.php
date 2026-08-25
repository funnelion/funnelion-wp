<?php

declare(strict_types=1);

namespace FunnelionWP;

use Funnelion\Cookie\GoogleAnalytics;

/**
 * Request-shaped helpers shared by the resolver and the form-event
 * reporter: visitor IP, current URL, language and GA4 identifiers.
 */
final class Support
{
    /**
     * Best-effort visitor IP from the forwarding chain. Order matters:
     * trusted CDN headers first, then the leftmost X-Forwarded-For, then
     * the direct peer. Overridable via the `funnelion_client_ip` filter.
     */
    public static function clientIp(): string
    {
        $ip = '';
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_TRUE_CLIENT_IP'])) {
            $ip = (string) $_SERVER['HTTP_TRUE_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($parts[0]);
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = (string) $_SERVER['REMOTE_ADDR'];
        }

        /** @var string $ip */
        $ip = (string) apply_filters('funnelion_client_ip', $ip);
        return $ip;
    }

    /** Absolute URL of the current request. */
    public static function currentUrl(): string
    {
        $scheme = is_ssl() ? 'https' : 'http';
        $host   = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : (string) (wp_parse_url(home_url(), PHP_URL_HOST) ?? '');
        $uri    = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '/';
        return $scheme . '://' . $host . $uri;
    }

    public static function referrer(): ?string
    {
        return isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : null;
    }

    public static function userAgent(): ?string
    {
        return isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null;
    }

    /**
     * Page language for attribution. Prefers Polylang's current language,
     * then WPML, then WordPress' locale (normalised to the primary subtag).
     * Overridable via `funnelion_language`.
     */
    public static function language(): ?string
    {
        $lang = null;

        if (function_exists('pll_current_language')) {
            $v = pll_current_language('slug');
            if (is_string($v) && $v !== '') {
                $lang = $v;
            }
        }
        if ($lang === null && defined('ICL_LANGUAGE_CODE') && ICL_LANGUAGE_CODE) {
            $lang = (string) ICL_LANGUAGE_CODE;
        }
        if ($lang === null) {
            $locale = get_locale();               // e.g. "lt_LT"
            if (is_string($locale) && $locale !== '') {
                $lang = strtolower(substr($locale, 0, 2));
            }
        }

        /** @var string|null $lang */
        $lang = apply_filters('funnelion_language', $lang);
        return is_string($lang) && $lang !== '' ? $lang : null;
    }

    public static function gaClientId(): ?string
    {
        return GoogleAnalytics::clientIdFromGlobals();
    }

    public static function gaSessionId(string $measurementId): ?string
    {
        if ($measurementId === '') {
            return null;
        }
        return GoogleAnalytics::sessionIdFromGlobals($measurementId);
    }

    public static function log(string $message): void
    {
        if (Settings::instance()->debug()) {
            error_log('[funnelion-wp] ' . $message);
        }
    }
}
