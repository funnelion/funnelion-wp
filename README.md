# Funnelion Call Tracking — WordPress plugin

Official WordPress adapter for [Funnelion](https://funnelion.ai) **server-side call tracking**. It wraps the [`funnelion/sdk`](https://github.com/funnelion/funnelion-php) and does the WordPress-specific wiring for you:

- **Server-side DNI** — resolves each visitor's tracking numbers/emails and swaps them into the rendered page (bypasses ad blockers, ITP, CSP).
- **Session cookie** — set automatically, continues the visitor's session across page views.
- **GA4 attribution** — reads the first-party `_ga` cookies server-side so Funnelion's call/email events stitch to the right GA4 session.
- **Conversions** — reports Contact Form 7 and WooCommerce checkouts; a generic hook covers any other form.
- **Fail-open** — if Funnelion is unreachable/slow (500 ms default timeout), the page renders its hardcoded fallbacks. The site never breaks.

The `funnelion/sdk` is **bundled in `vendor/`**, so this installs like any normal plugin — no Composer step on the target host.

## Install

1. Copy this folder to `wp-content/plugins/funnelion-wp/` (or upload the zip).
2. Activate **Funnelion Call Tracking** in *Plugins*.
3. Go to **Settings → Funnelion** and paste the Site's **server-side token** (from the Funnelion dashboard). Optionally set the GA4 Measurement ID.

Prefer config-as-code? Define the token in `wp-config.php` instead — it overrides the settings field:

```php
define('FUNNELION_SERVER_SIDE_TOKEN', 'srv_xxx');
// define('FUNNELION_BASE_URI', 'https://dash.funnelion.ai'); // only to target another env
```

## Marking up your theme

Add `data-funnelion="<Zone name>"` to the phone/email elements. The hardcoded value inside is the fallback. Zone names must match the swap zones configured in Funnelion.

```html
<a href="tel:+37062633611" data-funnelion="Header phone">+370 626 33611</a>
<span data-funnelion="Footer phone">+370 626 33611</span>
<a href="mailto:hi@example.com" data-funnelion="Sales email">hi@example.com</a>
```

The element must be a **leaf** (text only, no nested tags). `tel:`/`mailto:` hrefs are rewritten too.

## Conversions

Contact Form 7 and WooCommerce work out of the box (toggle in settings). For any other form, call the generic hook from your handler after it succeeds:

```php
do_action('funnelion_form_event', [
    'email' => $email,
    'name'  => $name,
    'phone' => $phone,
], $optional_form_id);
```

## Analytics events (GA4 / Meta)

The plugin fires **client-side** conversion events when a visitor interacts with a tracked contact point — language-agnostic names, all configurable in **Settings → Funnelion** (with a master on/off):

| Event | Fires when |
|---|---|
| `phone_click` | a `tel:` link is clicked |
| `email_click` | a `mailto:` link is clicked |
| `lead_form_submit` | a Contact Form 7 form is submitted (`wpcf7mailsent`) |

Each fires via `gtag()` (GA4) and `fbq()` (Meta Pixel) when those exist on the page (e.g. loaded by Google Site Kit); it safely no-ops otherwise.

### GA4 server-side call attribution (GA-ids ping)

Funnelion fires GA4 events for **actual received calls/emails** from its own servers (configured in the Funnelion dashboard → Integrations → GA4, e.g. a `call` trigger → `phone_call_received`). For those to land in the visitor's real GA4 session, Funnelion needs the visitor's GA `client_id` / `session_id`.

`gtag` writes its `_ga` cookies only *after* the page renders, so the server-side resolve can't read them on first load. Set the **GA4 Measurement ID** in Settings → Funnelion and the plugin closes that gap: once `gtag` is live it reads the ids and beacons them to a same-origin AJAX endpoint (`?action=funnelion_ga`), which forwards them to Funnelion together with the HttpOnly session cookie — no secret ever reaches the browser. Without it, Funnelion still attributes the event from the session's UTMs; the ids just make it join the visitor's existing GA4 session instead of opening a new one.

These client-side events are separate from that server-side dispatch: the plugin covers click/submit **intent**, Funnelion covers the **actual** received call/email. Keep form conversions in one place (the plugin's `lead_form_submit`) to avoid double-counting with a server-side `form` trigger.

## Hooks & filters

| Filter | Purpose |
|---|---|
| `funnelion_should_run` (bool) | Return false to skip DNI for the current request. |
| `funnelion_client_ip` (string) | Override the detected visitor IP. |
| `funnelion_language` (?string) | Override the language sent for attribution. |
| `funnelion_form_fields` (array, $posted) | Reshape the `{email,name,phone}` map sent on a conversion. |

Language is auto-detected from Polylang → WPML → `get_locale()`.

## Requirements

PHP 8.1+ with `ext-curl`, `ext-dom`, `ext-json` (standard). WordPress 6.0+.

## License

MIT © Funnelion. Built on [`funnelion/sdk`](https://github.com/funnelion/funnelion-php).
