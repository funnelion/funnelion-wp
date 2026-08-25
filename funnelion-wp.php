<?php
/**
 * Plugin Name:       Funnelion Call Tracking
 * Plugin URI:        https://github.com/funnelion/funnelion-wp
 * Description:       Server-side dynamic number insertion (DNI) and conversion tracking for Funnelion. Wraps the official funnelion/sdk — resolves each visitor's tracking numbers server-side, swaps them into the page, sets the session cookie, and reports form/WooCommerce conversions.
 * Version:           0.2.1
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Funnelion
 * Author URI:        https://funnelion.ai
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       funnelion-wp
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // no direct access
}

define('FUNNELION_WP_VERSION', '0.2.1');
define('FUNNELION_WP_FILE', __FILE__);
define('FUNNELION_WP_DIR', plugin_dir_path(__FILE__));
define('FUNNELION_WP_BASENAME', plugin_basename(__FILE__));

// Composer autoloader (bundled vendor/ ships the funnelion/sdk).
$funnelion_wp_autoload = FUNNELION_WP_DIR . 'vendor/autoload.php';
if (is_readable($funnelion_wp_autoload)) {
    require $funnelion_wp_autoload;
}

// Defensive PSR-4 autoloader for our own classes (in case vendor/ was trimmed).
spl_autoload_register(static function (string $class): void {
    $prefix = 'FunnelionWP\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $rel  = substr($class, strlen($prefix));
    $file = FUNNELION_WP_DIR . 'src/' . str_replace('\\', '/', $rel) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});

// The SDK must be present or the plugin cannot function.
if (!class_exists(\Funnelion\Client::class)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p><strong>Funnelion Call Tracking:</strong> '
            . esc_html__('the funnelion/sdk library is missing (vendor/ not found). Reinstall the plugin package.', 'funnelion-wp')
            . '</p></div>';
    });
    return;
}

add_action('plugins_loaded', static function (): void {
    \FunnelionWP\Plugin::instance()->boot();
});
