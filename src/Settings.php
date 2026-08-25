<?php

declare(strict_types=1);

namespace FunnelionWP;

/**
 * Options storage + admin settings screen (Settings → Funnelion).
 *
 * The server-side token and base URI may also be supplied via PHP
 * constants (FUNNELION_SERVER_SIDE_TOKEN / FUNNELION_BASE_URI) so a site
 * can keep the secret in wp-config.php instead of the database. A
 * constant always wins over the stored option.
 */
final class Settings
{
    public const OPTION = 'funnelion_wp_settings';

    private static ?self $instance = null;

    /** @var array<string,mixed>|null */
    private ?array $cache = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function boot(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_filter('plugin_action_links_' . FUNNELION_WP_BASENAME, [$this, 'actionLinks']);
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        if ($this->cache === null) {
            $stored = get_option(self::OPTION, []);
            $this->cache = array_merge($this->defaults(), is_array($stored) ? $stored : []);
        }
        return $this->cache;
    }

    /** @return array<string,mixed> */
    private function defaults(): array
    {
        return [
            'enabled'             => true,
            'server_side_token'   => '',
            'base_uri'            => 'https://dash.funnelion.ai',
            'timeout_ms'          => 1500,
            'ga_measurement_id'   => '',
            'form_events_enabled' => true,
            'cache_ttl'           => 60,
            'phone_field_names'   => 'phone,tel,telefonas,phone-number,your-phone',
            'debug'               => false,
        ];
    }

    public function isEnabled(): bool
    {
        return (bool) $this->all()['enabled'];
    }

    public function token(): string
    {
        if (defined('FUNNELION_SERVER_SIDE_TOKEN') && FUNNELION_SERVER_SIDE_TOKEN) {
            return (string) FUNNELION_SERVER_SIDE_TOKEN;
        }
        return trim((string) $this->all()['server_side_token']);
    }

    public function baseUri(): string
    {
        if (defined('FUNNELION_BASE_URI') && FUNNELION_BASE_URI) {
            return (string) FUNNELION_BASE_URI;
        }
        $v = trim((string) $this->all()['base_uri']);
        return $v !== '' ? untrailingslashit($v) : 'https://dash.funnelion.ai';
    }

    public function timeoutSeconds(): float
    {
        $ms = (int) $this->all()['timeout_ms'];
        if ($ms < 100) {
            $ms = 100;
        }
        return $ms / 1000;
    }

    public function gaMeasurementId(): string
    {
        return trim((string) $this->all()['ga_measurement_id']);
    }

    public function formEventsEnabled(): bool
    {
        return (bool) $this->all()['form_events_enabled'];
    }

    public function cacheTtl(): int
    {
        return max(0, (int) $this->all()['cache_ttl']);
    }

    /** @return list<string> */
    public function phoneFieldNames(): array
    {
        $raw = (string) $this->all()['phone_field_names'];
        $out = array_filter(array_map('trim', explode(',', strtolower($raw))));
        return array_values($out);
    }

    public function debug(): bool
    {
        return (bool) $this->all()['debug'];
    }

    // ---- Admin UI ----

    public function registerMenu(): void
    {
        add_options_page(
            __('Funnelion Call Tracking', 'funnelion-wp'),
            __('Funnelion', 'funnelion-wp'),
            'manage_options',
            'funnelion-wp',
            [$this, 'renderPage']
        );
    }

    public function registerSettings(): void
    {
        register_setting('funnelion_wp', self::OPTION, [$this, 'sanitize']);
    }

    /**
     * @param  mixed  $input
     * @return array<string,mixed>
     */
    public function sanitize($input): array
    {
        $in = is_array($input) ? $input : [];
        $d  = $this->defaults();

        return [
            'enabled'             => !empty($in['enabled']),
            'server_side_token'   => sanitize_text_field((string) ($in['server_side_token'] ?? '')),
            'base_uri'            => esc_url_raw((string) ($in['base_uri'] ?? $d['base_uri'])),
            'timeout_ms'          => max(100, (int) ($in['timeout_ms'] ?? $d['timeout_ms'])),
            'ga_measurement_id'   => sanitize_text_field((string) ($in['ga_measurement_id'] ?? '')),
            'form_events_enabled' => !empty($in['form_events_enabled']),
            'cache_ttl'           => max(0, (int) ($in['cache_ttl'] ?? $d['cache_ttl'])),
            'phone_field_names'   => sanitize_text_field((string) ($in['phone_field_names'] ?? $d['phone_field_names'])),
            'debug'               => !empty($in['debug']),
        ];
    }

    /**
     * @param array<int,string> $links
     * @return array<int,string>
     */
    public function actionLinks(array $links): array
    {
        $url = admin_url('options-general.php?page=funnelion-wp');
        array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'funnelion-wp') . '</a>');
        return $links;
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $o = $this->all();
        $tokenFromConst = defined('FUNNELION_SERVER_SIDE_TOKEN') && FUNNELION_SERVER_SIDE_TOKEN;
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Funnelion Call Tracking', 'funnelion-wp'); ?></h1>
            <p><?php echo esc_html__('Server-side dynamic number insertion. Mark phone/email elements in your theme with data-funnelion="Zone name"; this plugin swaps them per visitor.', 'funnelion-wp'); ?></p>
            <form method="post" action="options.php">
                <?php settings_fields('funnelion_wp'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><?php echo esc_html__('Enabled', 'funnelion-wp'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[enabled]" value="1" <?php checked($o['enabled']); ?>> <?php echo esc_html__('Run tracking on the front end', 'funnelion-wp'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fw_token"><?php echo esc_html__('Server-side token', 'funnelion-wp'); ?></label></th>
                        <td>
                            <?php if ($tokenFromConst): ?>
                                <em><?php echo esc_html__('Defined in wp-config.php (FUNNELION_SERVER_SIDE_TOKEN) — this field is ignored.', 'funnelion-wp'); ?></em>
                            <?php else: ?>
                                <input type="text" id="fw_token" class="regular-text code" name="<?php echo esc_attr(self::OPTION); ?>[server_side_token]" value="<?php echo esc_attr($o['server_side_token']); ?>" autocomplete="off">
                                <p class="description"><?php echo esc_html__('The Site\'s server-side token from the Funnelion dashboard.', 'funnelion-wp'); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fw_ga"><?php echo esc_html__('GA4 Measurement ID', 'funnelion-wp'); ?></label></th>
                        <td><input type="text" id="fw_ga" class="regular-text code" name="<?php echo esc_attr(self::OPTION); ?>[ga_measurement_id]" value="<?php echo esc_attr($o['ga_measurement_id']); ?>" placeholder="G-XXXXXXX">
                        <p class="description"><?php echo esc_html__('Optional — enables GA4 session stitching (reads the _ga cookies server-side).', 'funnelion-wp'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Form conversions', 'funnelion-wp'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[form_events_enabled]" value="1" <?php checked($o['form_events_enabled']); ?>> <?php echo esc_html__('Report Contact Form 7 and WooCommerce conversions', 'funnelion-wp'); ?></label></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fw_phone"><?php echo esc_html__('Phone field names', 'funnelion-wp'); ?></label></th>
                        <td><input type="text" id="fw_phone" class="regular-text code" name="<?php echo esc_attr(self::OPTION); ?>[phone_field_names]" value="<?php echo esc_attr($o['phone_field_names']); ?>">
                        <p class="description"><?php echo esc_html__('Comma-separated form field names treated as the phone number.', 'funnelion-wp'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fw_timeout"><?php echo esc_html__('API timeout (ms)', 'funnelion-wp'); ?></label></th>
                        <td><input type="number" id="fw_timeout" min="100" step="50" name="<?php echo esc_attr(self::OPTION); ?>[timeout_ms]" value="<?php echo esc_attr((string) $o['timeout_ms']); ?>">
                        <p class="description"><?php echo esc_html__('Fail-open: if Funnelion is slower than this, the page renders its hardcoded fallbacks.', 'funnelion-wp'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fw_cache"><?php echo esc_html__('Session cache (seconds)', 'funnelion-wp'); ?></label></th>
                        <td><input type="number" id="fw_cache" min="0" step="10" name="<?php echo esc_attr(self::OPTION); ?>[cache_ttl]" value="<?php echo esc_attr((string) $o['cache_ttl']); ?>">
                        <p class="description"><?php echo esc_html__('Cache each visitor\'s resolved numbers for this many seconds to skip an API call on every page view. 0 disables. Keep it well under the pool idle timeout.', 'funnelion-wp'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fw_base"><?php echo esc_html__('API base URI', 'funnelion-wp'); ?></label></th>
                        <td><input type="text" id="fw_base" class="regular-text code" name="<?php echo esc_attr(self::OPTION); ?>[base_uri]" value="<?php echo esc_attr($o['base_uri']); ?>">
                        <p class="description"><?php echo esc_html__('Leave as the default unless testing against another environment.', 'funnelion-wp'); ?></p></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html__('Debug log', 'funnelion-wp'); ?></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION); ?>[debug]" value="1" <?php checked($o['debug']); ?>> <?php echo esc_html__('Write resolve/form-event outcomes to the PHP error log', 'funnelion-wp'); ?></label></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
