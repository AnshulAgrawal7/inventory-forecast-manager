<?php
/**
 * Plugin Name: Inventory Forecast Manager
 * Description: Bestandsmanagement und Forecasting-Dashboard für WooCommerce mit Offline-Sales, KPI-Tracking und PDF-Export.
 * Version: 1.0
 * Author: Anshul Agrawal
 */

if (!defined('ABSPATH')) exit;

define('IFM_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Reihenfolge ist wichtig!
require_once IFM_PLUGIN_DIR . 'includes/class-ifm-admin-menu.php';
require_once IFM_PLUGIN_DIR . 'includes/class-ifm-offline-sales.php';
require_once IFM_PLUGIN_DIR . 'includes/class-ifm-forecast.php';
require_once IFM_PLUGIN_DIR . 'includes/class-ifm-kpis.php';
require_once IFM_PLUGIN_DIR . 'includes/class-ifm-stock-central.php';
require_once IFM_PLUGIN_DIR . 'includes/class-ifm-pdf-export.php';

// PDF Export Hooks initialisieren
IFM_PDF_Export::init();

// Tabellen bei Aktivierung anlegen
register_activation_hook(__FILE__, 'ifm_activate_plugin');
function ifm_activate_plugin() {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table = $wpdb->prefix . 'ifm_offline_sales';
    $sql = "CREATE TABLE $table (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        product_id BIGINT UNSIGNED NOT NULL,
        quantity INT NOT NULL,
        sale_price DECIMAL(10,2),
        sale_date DATE NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta($sql);
}

// Admin Menü initialisieren
add_action('admin_menu', function() {
    IFM_Admin_Menu::init();
});

// Formular-Handler initialisieren
add_action('admin_init', function() {
    IFM_Offline_Sales::handle_form();
    IFM_Forecast::handle();
});

// AJAX-Handler für Preisabruf
add_action('wp_ajax_ifm_get_product_price', function () {
    if (!current_user_can('manage_options')) wp_send_json_error();
    $product_id = intval($_POST['product_id'] ?? 0);
    $product = wc_get_product($product_id);
    if ($product) {
        wp_send_json_success(['price' => (float) $product->get_price()]);
    }
    wp_send_json_error();
});

// Chart.js im Backend für ALLE IFM-Seiten laden
add_action('admin_enqueue_scripts', function($hook) {
    if (isset($_GET['page']) && strpos($_GET['page'], 'ifm-') === 0) {
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
    }
});
