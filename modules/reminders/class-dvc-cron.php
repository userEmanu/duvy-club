<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_Cron {

    public function __construct() {
        // Programar el evento diario
        add_action( 'wp', array( $this, 'schedule_events' ) );
        add_action( 'dvc_daily_campaign_check', array( $this, 'run_daily_check' ) );

        // También permitir ejecución manual desde admin
        add_action( 'admin_post_dvc_run_cron', array( $this, 'handle_manual_run' ) );
    }

    public function schedule_events() {
        if ( ! wp_next_scheduled( 'dvc_daily_campaign_check' ) ) {
            wp_schedule_event( time(), 'daily', 'dvc_daily_campaign_check' );
        }
    }

    public function run_daily_check() {
        // Buscar campañas programadas para hoy
        $today = date( 'Y-m-d' );
        $campaigns = get_posts( array(
            'post_type'      => 'dvc_campaign',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => '_dvc_campaign_trigger_date',
                    'value'   => $today . '%',
                    'compare' => 'LIKE'
                )
            )
        ) );

        $campaign_obj = new DVC_Campaign();
        foreach ( $campaigns as $c ) {
            // Enviar la campaña
            $campaign_obj->send_campaign( $c->ID );
            // Actualizar meta para no enviar de nuevo
            update_post_meta( $c->ID, '_dvc_campaign_sent', 'yes' );
        }
    }

    public function handle_manual_run() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'No autorizado' );
        }
        check_admin_referer( 'dvc_run_cron' );
        $this->run_daily_check();
        wp_redirect( add_query_arg( array( 'page' => 'duvy-club', 'cron' => 'ok' ), admin_url( 'admin.php' ) ) );
        exit;
    }
}