<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_Deactivator {

    public static function deactivate() {
        flush_rewrite_rules();
        // No borramos datos en desactivación, solo limpiamos caché de WP
        wp_clear_scheduled_hook( 'dvc_daily_campaign_check' );
    }
}