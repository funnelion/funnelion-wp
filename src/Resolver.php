<?php

declare(strict_types=1);

namespace FunnelionWP;

use Funnelion\Cookie\Session;
use Funnelion\Html\ZoneSwapper;
use Funnelion\Resolve\Request as ResolveRequest;
use Funnelion\Resolve\Response as ResolveResponse;
use Throwable;

/**
 * Front-end DNI: resolve the visitor early, set the session cookie while
 * headers are still open, then swap data-funnelion zones in the fully
 * rendered page via an output buffer. Fail-open at every step.
 */
final class Resolver
{
    private ?ResolveResponse $response = null;

    public function __construct(private readonly Plugin $plugin)
    {
    }

    public function boot(): void
    {
        // Priority 1: run before themes emit output, so the cookie header
        // can still be sent and the buffer wraps the whole document.
        add_action('template_redirect', [$this, 'start'], 1);
    }

    public function start(): void
    {
        if (!$this->shouldRun()) {
            return;
        }

        $client = $this->plugin->client();
        if ($client === null) {
            return;
        }

        $settings = Settings::instance();

        try {
            $this->response = $client->resolveOrNull(new ResolveRequest(
                url:         Support::currentUrl(),
                ip:          Support::clientIp(),
                referrer:    Support::referrer(),
                userAgent:   Support::userAgent(),
                visitorId:   Session::readFromGlobals(),
                language:    Support::language(),
                gaClientId:  Support::gaClientId(),
                gaSessionId: Support::gaSessionId($settings->gaMeasurementId()),
            ));
        } catch (Throwable $e) {
            Support::log('resolve threw: ' . $e->getMessage());
            $this->response = null;
        }

        if ($this->response === null) {
            Support::log('resolve returned null (fail-open)');
            return;
        }

        // Continue the session on subsequent requests.
        if ($this->response->visitorId !== null && !headers_sent()) {
            header('Set-Cookie: ' . Session::headerValue(
                visitorId: $this->response->visitorId,
                secure:    is_ssl(),
            ), false);
        }

        ob_start([$this, 'swap']);
    }

    /**
     * Output-buffer callback: swap the resolved zones into the page.
     * Skips instantly when the page has no markers, and never throws.
     */
    public function swap(string $html): string
    {
        if ($this->response === null) {
            return $html;
        }
        if (strpos($html, 'data-funnelion') === false) {
            return $html; // nothing to swap — avoid the DOM parse cost
        }
        try {
            return (new ZoneSwapper())->swap($html, $this->response);
        } catch (Throwable $e) {
            Support::log('swap threw: ' . $e->getMessage());
            return $html; // fail-open: ship the fallbacks
        }
    }

    /** Only run on real, front-end, GET page views. */
    private function shouldRun(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return false;
        }
        if (is_admin() || wp_doing_ajax()) {
            return false;
        }
        if ((defined('REST_REQUEST') && REST_REQUEST) || (defined('DOING_CRON') && DOING_CRON) || (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)) {
            return false;
        }
        if (is_feed() || is_robots() || is_trackback() || is_favicon()) {
            return false;
        }

        return (bool) apply_filters('funnelion_should_run', true);
    }
}
