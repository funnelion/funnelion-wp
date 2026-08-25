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

        $settings  = Settings::instance();
        $ttl       = $settings->cacheTtl();
        $visitorId = Session::readFromGlobals();

        // Cache hit: a known visitor's resolved zones are reused for the
        // TTL window, so most page views make no API call at all. The TTL
        // is kept well below the pool's idle timeout, so an actively
        // browsing visitor still re-resolves often enough to keep their
        // server-side number assignment alive.
        if ($ttl > 0 && $visitorId !== null) {
            $cached = get_transient($this->cacheKey($visitorId));
            if (is_array($cached)) {
                Support::log('cache hit — no API call');
                $this->response = ResolveResponse::fromArray($cached);
                ob_start([$this, 'swap']); // cookie already present; no HTTP call
                return;
            }
        }

        $client = $this->plugin->client();
        if ($client === null) {
            return;
        }

        Support::log('resolve API call');

        try {
            $this->response = $client->resolveOrNull(new ResolveRequest(
                url:         Support::currentUrl(),
                ip:          Support::clientIp(),
                referrer:    Support::referrer(),
                userAgent:   Support::userAgent(),
                visitorId:   $visitorId,
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

        // Cache this visitor's resolution to skip the API call next time.
        if ($ttl > 0 && $this->response->visitorId !== null) {
            set_transient(
                $this->cacheKey($this->response->visitorId),
                $this->responseToArray($this->response),
                $ttl,
            );
        }

        ob_start([$this, 'swap']);
    }

    private function cacheKey(string $visitorId): string
    {
        return 'funnelion_wp_' . md5($visitorId);
    }

    /**
     * Serialise a Response back to the API-shaped array that
     * ResolveResponse::fromArray() reads, so it round-trips through the
     * transient cache.
     *
     * @return array<string, mixed>
     */
    private function responseToArray(ResolveResponse $r): array
    {
        return [
            'session_id'      => $r->sessionId,
            'visitor_id'      => $r->visitorId,
            'matched_rule_id' => $r->matchedRuleId,
            'reason'          => $r->reason,
            'swap_zones'      => array_map(static fn ($z): array => [
                'name'            => $z->name,
                'channel_kind'    => $z->channelKind,
                'address'         => $z->address,
                'source_label'    => $z->sourceLabel,
                'pool_id'         => $z->poolId,
                'mask_pattern'    => $z->maskPattern,
                'matched_rule_id' => $z->matchedRuleId,
                'start_token'     => $z->startToken,
                'selectors'       => $z->selectors,
            ], $r->swapZones),
        ];
    }

    /**
     * Output-buffer callback: swap the resolved zones into the page.
     * Selector-based swap first (numbers placed by the zone's own CSS
     * selectors, no page markup needed), then the marker-based swap for
     * any data-funnelion elements. Never throws — on any error the
     * original, pre-swap HTML is returned so the page keeps its
     * hardcoded fallbacks.
     */
    public function swap(string $html): string
    {
        if ($this->response === null || $this->response->swapZones === []) {
            return $html;
        }

        $original = $html;

        try {
            $html = (new SelectorSwapper())->swap($html, $this->response);

            if (strpos($html, 'data-funnelion') !== false) {
                $html = (new ZoneSwapper())->swap($html, $this->response);
            }
        } catch (Throwable $e) {
            Support::log('swap threw: ' . $e->getMessage());

            return $original; // fail-open
        }

        return $html;
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
