<?php

declare(strict_types=1);

namespace FunnelionWP;

use Funnelion\Cookie\Session;
use Funnelion\FormEvent\Request as FormEventRequest;
use Throwable;
use WC_Order;
use WPCF7_Submission;

/**
 * Reports conversions to Funnelion. Ships adapters for Contact Form 7 and
 * WooCommerce checkout, plus a generic `funnelion_form_event` action for
 * any other form plugin or custom handler.
 */
final class FormEvents
{
    public function __construct(private readonly Plugin $plugin)
    {
    }

    public function boot(): void
    {
        // Contact Form 7 — fire when a submission passes validation (mail-
        // independent): the lead is captured even if the notification email
        // later bounces or SMTP is down.
        add_action('wpcf7_before_send_mail', [$this, 'contactForm7'], 10, 1);

        // WooCommerce — order successfully placed.
        add_action('woocommerce_checkout_order_processed', [$this, 'wooOrder'], 20, 1);

        // Generic hook: do_action('funnelion_form_event', ['email'=>..., 'phone'=>...]);
        add_action('funnelion_form_event', [$this, 'manual'], 10, 2);
    }

    /** @param mixed $contactForm */
    public function contactForm7($contactForm = null): void
    {
        if (!class_exists(WPCF7_Submission::class)) {
            return;
        }
        $submission = WPCF7_Submission::get_instance();
        if (!$submission) {
            return;
        }
        $posted = $submission->get_posted_data();
        if (!is_array($posted)) {
            return;
        }

        $formId = null;
        if (is_object($contactForm) && method_exists($contactForm, 'id')) {
            $formId = (int) $contactForm->id();
        }

        $this->report($this->mapFields($posted), $formId);
    }

    /** @param int $orderId */
    public function wooOrder($orderId): void
    {
        if (!function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order($orderId);
        if (!$order instanceof WC_Order) {
            return;
        }

        $fields = [
            'email' => $order->get_billing_email(),
            'name'  => trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'phone' => $order->get_billing_phone(),
        ];

        $this->report($fields, (int) $orderId);
    }

    /**
     * @param array<string,mixed> $fields
     * @param int|null            $formId
     */
    public function manual($fields, $formId = null): void
    {
        if (is_array($fields)) {
            $this->report($this->mapFields($fields), is_numeric($formId) ? (int) $formId : null);
        }
    }

    /**
     * Reduce arbitrary posted data to the {email,name,phone} shape
     * Funnelion expects. Overridable wholesale via `funnelion_form_fields`.
     *
     * @param array<string,mixed> $posted
     * @return array<string,string>
     */
    private function mapFields(array $posted): array
    {
        $flat = [];
        foreach ($posted as $key => $value) {
            $k = strtolower((string) $key);
            // Drop Contact Form 7 internals (_wpcf7*) — never useful as lead data.
            if (str_starts_with($k, '_wpcf7')) {
                continue;
            }
            $flat[$k] = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
        }

        // Track which raw keys were consumed by a canonical field so we don't
        // also pass them through under their raw name.
        $used = [];
        $pick = static function (array $candidates) use ($flat, &$used): string {
            foreach ($candidates as $k) {
                if (!empty($flat[$k])) { $used[] = $k; return $flat[$k]; }
            }
            return '';
        };

        // email: explicit key first, else the first value that validates.
        $email = $pick(['email', 'your-email', 'e-mail', 'el-pastas']);
        if ($email === '') {
            foreach ($flat as $k => $v) {
                if (is_email($v)) { $email = $v; $used[] = $k; break; }
            }
        }

        $name    = $pick(['name', 'your-name', 'fullname', 'vardas']);
        $phone   = $pick(Settings::instance()->phoneFieldNames());
        $message = $pick(['message', 'your-message', 'comment', 'comments', 'zinute', 'žinutė', 'tekstas', 'klausimas']);

        $fields = array_filter([
            'email'   => $email,
            'name'    => $name,
            'phone'   => $phone,
            'message' => $message,
        ], static fn ($v) => $v !== '');

        // Pass through any remaining non-internal fields under their own key
        // (e.g. subject, a custom select) so nothing the visitor entered is lost.
        foreach ($flat as $k => $v) {
            if ($v === '' || in_array($k, $used, true) || isset($fields[$k])) {
                continue;
            }
            if (str_contains($k, 'honeypot')) {
                continue;
            }
            $fields[$k] = $v;
        }

        /** @var array<string,string> $fields */
        $fields = apply_filters('funnelion_form_fields', $fields, $posted);
        return $fields;
    }

    /**
     * @param array<string,string> $fields
     */
    private function report(array $fields, ?int $formId): void
    {
        if ($fields === []) {
            Support::log('form event skipped: no recognisable fields');
            return;
        }

        $client = $this->plugin->client();
        if ($client === null) {
            return;
        }

        $settings = Settings::instance();

        try {
            $response = $client->formEventOrNull(new FormEventRequest(
                ip:          Support::clientIp(),
                fields:      $fields,
                url:         Support::currentUrl(),
                referrer:    Support::referrer(),
                userAgent:   Support::userAgent(),
                visitorId:   Session::readFromGlobals(),
                formId:      $formId,
                language:    Support::language(),
                gaClientId:  Support::gaClientId(),
                gaSessionId: Support::gaSessionId($settings->gaMeasurementId()),
            ));

            if ($response !== null) {
                Support::log('form event: status=' . $response->status . ' attribution=' . (string) $response->attributionStatus);
            } else {
                Support::log('form event returned null (fail-open)');
            }
        } catch (Throwable $e) {
            Support::log('form event threw: ' . $e->getMessage());
        }
    }
}
