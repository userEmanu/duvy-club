<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_User_Table {

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'dvc_customers';
    }

    /**
     * Obtiene o crea un registro en la tabla temporal por email.
     */
    public function get_or_create_by_email( $email, $data = array() ) {
        $existing = $this->get_by_email( $email );
        if ( $existing ) {
            return $existing;
        }
        return $this->insert( array_merge( $data, array( 'email' => $email ) ) );
    }

    public function get_by_email( $email ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE email = %s",
            sanitize_email( $email )
        ) );
    }

    public function insert( $data ) {
        global $wpdb;
        $defaults = array(
            'email'            => '',
            'first_name'       => '',
            'last_name'        => '',
            'document'         => '',
            'phone'            => '',
            'billing_address'  => '',
            'shipping_address' => '',
            'order_count'      => 0,
            'last_order_date'  => null
        );
        $data = wp_parse_args( $data, $defaults );
        $data['email'] = sanitize_email( $data['email'] );
        if ( empty( $data['email'] ) ) {
            return false;
        }

        return $wpdb->insert( $this->table, array(
            'email'            => $data['email'],
            'first_name'       => sanitize_text_field( $data['first_name'] ),
            'last_name'        => sanitize_text_field( $data['last_name'] ),
            'document'         => sanitize_text_field( $data['document'] ),
            'phone'            => sanitize_text_field( $data['phone'] ),
            'billing_address'  => sanitize_textarea_field( $data['billing_address'] ),
            'shipping_address' => sanitize_textarea_field( $data['shipping_address'] ),
            'order_count'      => intval( $data['order_count'] ),
            'last_order_date'  => ! empty( $data['last_order_date'] ) ? $data['last_order_date'] : null
        ) );
    }

    public function update( $email, $data ) {
        global $wpdb;
        $data['email'] = sanitize_email( $email );
        if ( empty( $data['email'] ) ) {
            return false;
        }
        $update_data = array();
        $fields = array( 'first_name', 'last_name', 'document', 'phone', 'billing_address', 'shipping_address', 'order_count', 'last_order_date' );
        foreach ( $fields as $field ) {
            if ( isset( $data[ $field ] ) ) {
                if ( in_array( $field, array( 'billing_address', 'shipping_address' ) ) ) {
                    $update_data[ $field ] = sanitize_textarea_field( $data[ $field ] );
                } else if ( $field == 'order_count' ) {
                    $update_data[ $field ] = intval( $data[ $field ] );
                } else if ( $field == 'last_order_date' ) {
                    $update_data[ $field ] = $data[ $field ];
                } else {
                    $update_data[ $field ] = sanitize_text_field( $data[ $field ] );
                }
            }
        }
        if ( empty( $update_data ) ) {
            return false;
        }
        return $wpdb->update( $this->table, $update_data, array( 'email' => $data['email'] ) );
    }

    public function delete( $email ) {
        global $wpdb;
        return $wpdb->delete( $this->table, array( 'email' => sanitize_email( $email ) ) );
    }

    public function get_all( $limit = 1000, $offset = 0 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table} ORDER BY id ASC LIMIT %d OFFSET %d",
            $limit,
            $offset
        ) );
    }

    public function count_all() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" );
    }
}