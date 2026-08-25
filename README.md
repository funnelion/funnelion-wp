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
