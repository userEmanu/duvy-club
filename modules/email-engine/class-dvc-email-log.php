<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_Email_Log {

    public function add_entry( $args ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dvc_email_log';

        $defaults = array(
            'recipient_email'   => '',
            'recipient_user_id' => 0,
            'template_id'       => 0,
            'subject'           => '',
            'body'              => '',
            'status'            => 'sent',
            'error_message'     => ''
        );
        $data = wp_parse_args( $args, $defaults );

        return $wpdb->insert( $table, array(
            'recipient_email'   => sanitize_email( $data['recipient_email'] ),
            'recipient_user_id' => intval( $data['recipient_user_id'] ),
            'template_id'       => intval( $data['template_id'] ),
            'subject'           => sanitize_text_field( $data['subject'] ),
            'body'              => wp_kses_post( $data['body'] ),
            'status'            => sanitize_text_field( $data['status'] ),
            'error_message'     => sanitize_text_field( $data['error_message'] ),
            'sent_at'           => current_time( 'mysql' )
        ) );
    }

    public function get_logs( $limit = 100, $offset = 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dvc_email_log';
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY sent_at DESC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ) );
    }
}