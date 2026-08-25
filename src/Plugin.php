<?php

declare(strict_types=1);

namespace FunnelionWP;

use Funnelion\Client;
use Funnelion\Config;

/**
 * Plugin bootstrap: wires settings (admin) and the tracking components
 * (frontend). Holds the single configured SDK Client.
 */
final class Plugin
{
    private static ?self $instance = null;
    private ?Client $client = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        $settings = Settings::instance();
        $settings->boot();

        if (is_admin()) {
            return; // admin screens: settings UI only, no tracking
        }

        if (!$settings->isEnabled() || $settings->token() === '') {
            return; // not configured — stay completely inert
        }

        (new Resolver($this))->boot();

        if ($settings->formEventsEnabled()) {
            (new FormEvents($this))->boot();
        }

        if ($settings->analyticsEventsEnabled()) {
            (new AnalyticsEvents($this))->boot();
        }
    }

    /**
     * The shared, configured SDK client. Returns null if the token is
     * missing so callers can no-op safely.
     */
    public function client(): ?Client
    {
        $settings = Settings::instance();
        $token    = $settings->token();
        if ($token === '') {
            return null;
        }

        return $this->client ??= new Client(new Config(
            siteToken:      $token,
            baseUri:        $settings->baseUri(),
            timeoutSeconds: $settings->timeoutSeconds(),
        ));
    }
}
