<?php

declare(strict_types=1);

namespace FunnelionWP;

/**
 * Fires GA4 (and Meta Pixel, when present) conversion events client-side:
 *   - phone_click       — visitor clicks a tel: link  ("call")
 *   - email_click       — visitor clicks a mailto: link ("mail")
 *   - lead_form_submit  — a Contact Form 7 form is successfully submitted
 *
 * Mirrors the varenapadel.lt convention with language-agnostic names
 * (all event names are configurable in Settings). Client-side only: a
 * tiny footer script that calls gtag()/fbq() if the site has them (this
 * site loads GA via Google Site Kit). Independent of the number swap.
 */
final class AnalyticsEvents
{
    public function __construct(private readonly Plugin $plugin)
    {
    }

    public function boot(): void
    {
        add_action('wp_footer', [$this, 'renderScript'], 99);
    }

    public function renderScript(): void
    {
        $settings = Settings::instance();

        $json = wp_json_encode([
            'phone' => $settings->eventPhone(),
            'email' => $settings->eventEmail(),
            'form'  => $settings->eventForm(),
        ]);
        if ($json === false) {
            return;
        }
        ?>
<script>
(function () {
  var CFG = <?php echo $json; ?>;
  function fire(name, params) {
    if (!name) return;
    try { if (typeof window.gtag === 'function') window.gtag('event', name, params); } catch (e) {}
    try { if (typeof window.fbq === 'function') window.fbq('trackCustom', name, params); } catch (e) {}
  }
  // Call / mail: delegated click on tel: and mailto: links.
  document.addEventListener('click', function (e) {
    var t = e.target;
    var a = t && t.closest ? t.closest('a[href^="tel:"], a[href^="mailto:"]') : null;
    if (!a) return;
    var href = a.getAttribute('href') || '';
    if (href.indexOf('tel:') === 0) {
      fire(CFG.phone, { link_url: href, number: href.replace(/^tel:/, ''), page_location: location.href });
    } else if (href.indexOf('mailto:') === 0) {
      fire(CFG.email, { link_url: href, email: href.replace(/^mailto:/, '').split('?')[0], page_location: location.href });
    }
  }, true);
  // Lead form: Contact Form 7 fires wpcf7mailsent on a successful submit.
  document.addEventListener('wpcf7mailsent', function (e) {
    var d = e.detail || {};
    fire(CFG.form, { form_id: d.contactFormId || null, page_location: location.href });
  }, false);
})();
</script>
        <?php
    }
}
