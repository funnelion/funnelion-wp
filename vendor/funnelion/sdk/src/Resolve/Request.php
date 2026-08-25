<?php

declare(strict_types=1);

namespace Funnelion\Resolve;

/**
 * Input to Funnelion\Client::resolve(). Mirrors the POST body documented
 * in docs/api-v1-resolve.md.
 *
 * `$ip` must be the *visitor's* IP as the consumer's server determined
 * it (e.g. from X-Forwarded-For / CF-Connecting-IP), not the consumer's
 * own server IP.
 */
final class Request
{
    /**
     * @param  ?string  $language  the language code of the page the
     *        visitor is currently on (free-form — "en", "lt",
     *        "de-DE", "zh-Hans-CN" — whatever vocabulary your site
     *        uses). Funnelion stores this on the visitor's session
     *        and uses it to attribute downstream events (e.g. inbound
     *        emails / calls fired to GA4 with the matching {language}
     *        param). Pass it explicitly from your i18n framework's
     *        current-locale value. See README "Language" section.
     * @param  ?string  $gaClientId  the visitor's GA4 client id, from the
     *        `_ga` cookie your gtag.js writes — `Cookie\GoogleAnalytics::
     *        clientIdFromGlobals()` reads it for you. Funnelion sends its
     *        GA4 events for this visitor's calls / emails under this id,
     *        which is what makes GA4 attribute them to the traffic source
     *        that brought the visitor in. Without it those events read as
     *        "Unassigned" in GA4.
     * @param  ?string  $gaSessionId  the visitor's GA4 session id, from
     *        the per-stream `_ga_<stream>` cookie —
     *        `Cookie\GoogleAnalytics::sessionIdFromGlobals('G-ABC123')`.
     *        Joins the event to the session the visitor is in rather than
     *        opening a fresh one. Send it alongside `$gaClientId`; either
     *        may be null on the first request of a visit (gtag writes its
     *        cookies after the page loads) and Funnelion keeps the newest
     *        non-null value it receives.
     */
    public function __construct(
        public readonly string $url,
        public readonly string $ip,
        public readonly ?string $referrer = null,
        public readonly ?string $userAgent = null,
        public readonly ?string $visitorId = null,
        public readonly ?string $language = null,
        public readonly ?string $gaClientId = null,
        public readonly ?string $gaSessionId = null,
    ) {
        if ($url === '') {
            throw new \InvalidArgumentException('url must not be empty.');
        }
        if ($ip === '') {
            throw new \InvalidArgumentException('ip must not be empty.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $out = [
            'url' => $this->url,
            'ip' => $this->ip,
        ];
        if ($this->referrer !== null) {
            $out['referrer'] = $this->referrer;
        }
        if ($this->userAgent !== null) {
            $out['user_agent'] = $this->userAgent;
        }
        if ($this->visitorId !== null) {
            $out['visitor_id'] = $this->visitorId;
        }
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
