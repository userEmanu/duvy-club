<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// 1. Borrar opciones propias
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'dvc_%'" );

// 2. Borrar tablas personalizadas
$tables = array(
    $wpdb->prefix . 'dvc_customers',
    $wpdb->prefix . 'dvc_email_log',
    $wpdb->prefix . 'dvc_benefit_usage',
    $wpdb->prefix . 'dvc_campaign_recipients'
);
foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// 3. Borrar meta de usuarios (fecha nacimiento)
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key = '_dvc_fecha_nacimiento'" );

// 4. Borrar meta de posts de los CPT creados (dvc_gift, dvc_campaign, dvc_email_template)
$post_types = array( 'dvc_gift', 'dvc_campaign', 'dvc_email_template' );
$post_ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('" . implode( "','", $post_types ) . "')" );
if ( ! empty( $post_ids ) ) {
    foreach ( $post_ids as $post_id ) {
        wp_delete_post( $post_id, true );
    }
}

// 5. Eliminar metadatos de cupones (shop_coupon) asociados al plugin
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_dvc_%'" );