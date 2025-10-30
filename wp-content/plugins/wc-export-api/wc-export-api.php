<?php
/**
 * Plugin Name: WooCommerce Export API
 * Description: REST API endpoints to export WooCommerce Orders, Products, and Customers as JSON or CSV.
 * Version: 1.0.0
 * Author: Cursor AI
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Simple check to ensure WooCommerce is available
add_action('plugins_loaded', static function () {
    if (!class_exists('WooCommerce')) {
        if (is_admin()) {
            add_action('admin_notices', static function () {
                echo '<div class="notice notice-error"><p><strong>WooCommerce Export API</strong> requires WooCommerce to be active.</p></div>';
            });
        }
        return;
    }

    require_once __DIR__ . '/includes/class-wc-export-formatter.php';
    require_once __DIR__ . '/includes/class-wc-export-api.php';

    // Initialize the API
    new WC_Export_API();
});
