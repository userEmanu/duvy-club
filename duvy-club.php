<?php
/**
 * Plugin Name: Duvy Club - Fidelización de Clientes
 * Plugin URI:  https://duvyclass.com/
 * Description: Motor de fidelización, campañas de cumpleaños, recompra y VIP, sincronización de invitados, cupones y regalos.
 * Version:     1.0.2
 * Author:      Duvy Class
 * Text Domain: duvy-club
 * Domain Path: /languages
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ============================================================
// DECLARAR COMPATIBILIDAD CON HPOS (WooCommerce 8.0+)
// ============================================================
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

// Forzar compatibilidad por si el mensaje persiste
add_filter( 'woocommerce_feature_compatibility_checked', '__return_true' );

// ============================================================
// CONSTANTES
// ============================================================
define( 'DVC_VERSION', '1.0.2' );
define( 'DVC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DVC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DVC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// ============================================================
// LOADER
// ============================================================
require_once DVC_PLUGIN_DIR . 'includes/class-dvc-loader.php';

$dvc_loader = new DVC_Loader();
$dvc_loader->run();

// ============================================================
// HOOKS DE ACTIVACIÓN / DESACTIVACIÓN
// ============================================================
register_activation_hook( __FILE__, array( 'DVC_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'DVC_Deactivator', 'deactivate' ) );