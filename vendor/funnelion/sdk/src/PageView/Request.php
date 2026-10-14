<?php

declare(strict_types=1);

namespace Funnelion\PageView;

/**
 * Input to Funnelion\Client::pageView(). Mirrors the POST body of
 * /api/v1/page-view: the visitor (funnelion_session cookie) and the page
 * they just opened.
 *
 * Send one for every page you render *without* calling resolve — most
 * often because you cache the resolve response per visitor. A resolve
 * already reports its own URL; pages served from your cache otherwise
 * never reach Funnelion, and the visitor's browsing history skips them.
 *
 * Funnelion only touches a live session: an unknown or expired
 * visitorId is accepted and ignored, never opens one.
 */
final class Request
{
    /**
     * @param  string  $visitorId  the funnelion_session cookie value (the
     *        visitor_id a previous resolve returned)
     * @param  string  $url  the full URL of the page being rendered
     * @param  ?string  $language  same semantics as
     *        Funnelion\Resolve\Request::$language
     * @param  ?string  $gaClientId  same semantics as
     *        Funnelion\Resolve\Request::$gaClientId
     * @param  ?string  $gaSessionId  same semantics as
     *        Funnelion\Resolve\Request::$gaSessionId
     */
    public function __construct(
        public readonly string $visitorId,
        public readonly string $url,
        public readonly ?string $language = null,
        public readonly ?string $gaClientId = null,
        public readonly ?string $gaSessionId = null,
    ) {
        if ($visitorId === '') {
            throw new \InvalidArgumentException('visitorId must not be empty.');
        }
        if ($url === '') {
            throw new \InvalidArgumentException('url must not be empty.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'visitor_id' => $this->visitorId,
            'url' => $this->url,
        ];
        if ($this->language !== null) {
            $out['language'] = $this->language;
        }
        if ($this->gaClientId !== null) {
            $out['ga_client_id'] = $this->gaClientId;
        }
        if ($this->gaSessionId !== null) {
            $out['ga_session_id'] = $this->gaSessionId;
        }

        return $out;
    }
}
