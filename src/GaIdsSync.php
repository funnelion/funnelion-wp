<?php

declare(strict_types=1);

namespace FunnelionWP;

use Funnelion\Cookie\Session;
use Funnelion\Resolve\Request as ResolveRequest;
use Throwable;

/**
 * Hands the visitor's GA4 client_id / session_id to Funnelion.
 *
 * gtag writes its _ga cookies only after the page has rendered, so the
 * server-side resolve that produced the page could not read them. This
 * fills that gap: a footer script reads the ids once gtag is live and
 * beacons them to a same-origin endpoint; that endpoint forwards them to
 * Funnelion (via a resolve call carrying the HttpOnly session cookie),
 * so Funnelion's server-side call/email GA4 events land in the visitor's
 * actual GA4 session instead of opening a new one.
 *
 * Active only when a GA4 Measurement ID is set in Settings.
 */
final class GaIdsSync
{
    private const ACTION = 'funnelion_ga';

    public function __construct(private readonly Plugin $plugin)
    {
    }

    /** AJAX endpoint — registers even in admin context (admin-ajax). */
    public function bootAjax(): void
    {
        add_action('wp_ajax_nopriv_'.self::ACTION, [$this, 'receive']);
        add_action('wp_ajax_'.self::ACTION, [$this, 'receive']);
    }

    /** Browser-facing beacon script — front end only. */
    public function bootFrontend(): void
    {
        add_action('wp_footer', [$this, 'renderScript'], 100);
    }

    public function renderScript(): void
    {
        $mid = Settings::instance()->gaMeasurementId();
        if ($mid === '') {
            return;
        }

        $json = wp_json_encode([
            'mid' => $mid,
            'url' => admin_url('admin-ajax.php').'?action='.self::ACTION,
        ]);
        if ($json === false) {
            return;
        }
        ?>
<script>
(function () {
  var CFG = <?php echo $json; ?>;
  if (!CFG.mid || !CFG.url) return;
  function whenReady(fn) {
    if (typeof window.gtag === 'function') { fn(); return; }
    var n = 0, t = setInterval(function () {
      if (typeof window.gtag === 'function' || ++n > 20) { clearInterval(t); if (typeof window.gtag === 'function') fn(); }
    }, 250);
  }
  whenReady(function () {
    var ids = {}, pending = 2;
    function done() {
      if (--pending) return;
      if (!ids.ga_client_id && !ids.ga_session_id) return;
      var key = (ids.ga_client_id || '') + '|' + (ids.ga_session_id || '');
      try { if (sessionStorage.getItem('funnelion_ga') === key) return; } catch (e) {}
      try { navigator.sendBeacon(CFG.url, new Blob([JSON.stringify(ids)], { type: 'application/json' })); } catch (e) {}
      try { sessionStorage.setItem('funnelion_ga', key); } catch (e) {}
    }
    try {
      window.gtag('get', CFG.mid, 'client_id',  function (v) { ids.ga_client_id  = v || null; done(); });
      window.gtag('get', CFG.mid, 'session_id', function (v) { ids.ga_session_id = v ? String(v) : null; done(); });
    } catch (e) {}
  });
})();
</script>
        <?php
    }

    public function receive(): void
    {
        $raw  = file_get_contents('php://input');
        $body = is_string($raw) ? json_decode($raw, true) : null;

        $client = $this->plugin->client();
        if (is_array($body) && $client !== null) {
            $cid = isset($body['ga_client_id']) && is_string($body['ga_client_id']) ? $body['ga_client_id'] : null;
            $sid = isset($body['ga_session_id']) && is_string($body['ga_session_id']) ? $body['ga_session_id'] : null;

            if (($cid !== null || $sid !== null) && Session::readFromGlobals() !== null) {
                try {
                    $client->resolveOrNull(new ResolveRequest(
                        url:         Support::referrer() ?? Support::currentUrl(),
                        ip:          Support::clientIp(),
                        visitorId:   Session::readFromGlobals(),
                        gaClientId:  $cid,
                        gaSessionId: $sid,
                    ));
                    Support::log('ga-ids synced (client='.($cid ? 'y' : 'n').' session='.($sid ? 'y' : 'n').')');
                } catch (Throwable $e) {
                    Support::log('ga-ids sync threw: '.$e->getMessage());
                }
            }
        }

        wp_send_json_success();
    }
}
