<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_User_Sync {

    private $table;

    public function __construct() {
        $this->table = new DVC_User_Table();
        add_action( 'admin_post_dvc_sync_guest_to_wp', array( $this, 'handle_bulk_sync' ) );
        add_action( 'admin_post_dvc_sync_selected', array( $this, 'handle_selected_sync' ) );
        add_action( 'admin_post_dvc_sync_single_user', array( $this, 'handle_single_sync' ) );
        // También para sincronizar cantidad específica
        add_action( 'admin_post_dvc_sync_limit', array( $this, 'handle_limit_sync' ) );
    }

    /**
     * Sincroniza todos los invitados desde pedidos (población inicial).
     */
    public function sync_guests_from_orders() {
        global $wpdb;

        $orders = wc_get_orders( array(
            'customer' => 0, // invitados
            'limit'    => -1,
            'return'   => 'ids'
        ) );

        $guest_data = array();
        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( ! $order ) {
                continue;
            }
            $email = $order->get_billing_email();
            if ( empty( $email ) ) {
                continue;
            }

            if ( ! isset( $guest_data[ $email ] ) ) {
                $guest_data[ $email ] = array(
                    'first_name'       => $order->get_billing_first_name(),
                    'last_name'        => $order->get_billing_last_name(),
                    'document'         => $order->get_meta( '_billing_document' ) ?: '',
                    'phone'            => $order->get_billing_phone(),
                    'billing_address'  => $order->get_formatted_billing_address(),
                    'shipping_address' => $order->get_formatted_shipping_address(),
                    'order_count'      => 0,
                    'last_order_date'  => null
                );
            } else {
                // Actualizar con datos más completos si están vacíos
                if ( empty( $guest_data[ $email ]['first_name'] ) && $order->get_billing_first_name() ) {
                    $guest_data[ $email ]['first_name'] = $order->get_billing_first_name();
                }
                if ( empty( $guest_data[ $email ]['last_name'] ) && $order->get_billing_last_name() ) {
                    $guest_data[ $email ]['last_name'] = $order->get_billing_last_name();
                }
                // ... más campos si se desea
            }
            $guest_data[ $email ]['order_count']++;
            $date_created = $order->get_date_created();
            if ( $date_created ) {
                $date_str = $date_created->date( 'Y-m-d H:i:s' );
                if ( ! $guest_data[ $email ]['last_order_date'] || $date_str > $guest_data[ $email ]['last_order_date'] ) {
                    $guest_data[ $email ]['last_order_date'] = $date_str;
                }
            }
        }

        // Insertar/Actualizar en tabla temporal
        $count = 0;
        foreach ( $guest_data as $email => $data ) {
            $existing = $this->table->get_by_email( $email );
            if ( $existing ) {
                $this->table->update( $email, $data );
            } else {
                $data['email'] = $email;
                $this->table->insert( $data );
            }
            $count++;
        }

        return $count;
    }

    /**
     * Convierte un usuario de la tabla temporal a usuario WordPress.
     * @param string $email
     * @return int|false ID del nuevo usuario o false.
     */
    public function convert_to_wp_user( $email ) {
        $guest = $this->table->get_by_email( $email );
        if ( ! $guest ) {
            return false;
        }

        // Verificar si ya existe usuario WP con ese email
        $user_id = email_exists( $email );
        if ( $user_id ) {
            // Ya existe, solo migrar datos y eliminar de temporal
            $this->migrate_guest_data_to_user( $guest, $user_id );
            $this->table->delete( $email );
            return $user_id;
        }

        // Generar contraseña aleatoria
        $password = wp_generate_password( 12, true, true );

        // Crear usuario
        $user_id = wp_create_user( $email, $password, $email );
        if ( is_wp_error( $user_id ) ) {
            return false;
        }

        // Asignar rol customer
        $user = new WP_User( $user_id );
        $user->set_role( 'customer' );

        // Rellenar datos
        update_user_meta( $user_id, 'first_name', $guest->first_name );
        update_user_meta( $user_id, 'last_name', $guest->last_name );
        update_user_meta( $user_id, 'billing_phone', $guest->phone );
        update_user_meta( $user_id, '_dvc_document', $guest->document );
        update_user_meta( $user_id, 'billing_address_1', $guest->billing_address );
        update_user_meta( $user_id, 'shipping_address_1', $guest->shipping_address );

        // Migrar pedidos (asignar al usuario)
        $this->assign_orders_to_user( $email, $user_id );

        // Enviar correo de bienvenida
        $this->send_welcome_email( $user_id, $password );

        // Eliminar de tabla temporal
        $this->table->delete( $email );

        return $user_id;
    }

    private function assign_orders_to_user( $email, $user_id ) {
        $orders = wc_get_orders( array(
            'billing_email' => $email,
            'limit'         => -1,
            'return'        => 'ids'
        ) );
        foreach ( $orders as $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $order->set_customer_id( $user_id );
                $order->save();
            }
        }
    }

    private function send_welcome_email( $user_id, $password ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        // Buscar plantilla de bienvenida por título
        $template_posts = get_posts( array(
            'post_type'      => 'dvc_email_template',
            'title'          => 'Bienvenida a Duvy Class',
            'posts_per_page' => 1,
            'post_status'    => 'publish'
        ) );

        $template_id = 0;
        if ( ! empty( $template_posts ) ) {
            $template_id = $template_posts[0]->ID;
        }

        $sender = new DVC_Email_Sender();
        $data = array(
            'firstname' => $user->first_name ?: $user->display_name,
            'lastname'  => $user->last_name,
            'email'     => $user->user_email,
            'password'  => $password
        );

        if ( $template_id ) {
            $sender->send( $template_id, $user->user_email, $data, $user_id );
        } else {
            $subject = '¡Bienvenido a Duvy Class!';
            $body = 'Hola ' . $data['firstname'] . ' ' . $data['lastname'] . ',<br><br>Tu cuenta ha sido activada. Tu contraseña temporal es: <strong>' . $password . '</strong><br><br>Inicia sesión para comenzar.';
            $sender->send_raw( $user->user_email, $subject, $body, array(), $user_id );
        }
    }

    private function migrate_guest_data_to_user( $guest, $user_id ) {
        update_user_meta( $user_id, 'first_name', $guest->first_name );
        update_user_meta( $user_id, 'last_name', $guest->last_name );
        update_user_meta( $user_id, 'billing_phone', $guest->phone );
        update_user_meta( $user_id, '_dvc_document', $guest->document );
        $this->assign_orders_to_user( $guest->email, $user_id );
    }

    // ========== HANDLERS ADMIN ==========

    public function handle_bulk_sync() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'No autorizado' );
        }
        check_admin_referer( 'dvc_bulk_sync' );

        $count = $this->sync_guests_from_orders();
        wp_redirect( add_query_arg( array( 'page' => 'dvc-sync-users', 'synced' => $count ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_limit_sync() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'No autorizado' );
        }
        check_admin_referer( 'dvc_limit_sync' );

        $limit = isset( $_POST['dvc_sync_limit'] ) ? intval( $_POST['dvc_sync_limit'] ) : 10;
        if ( $limit < 1 ) {
            $limit = 10;
        }

        // Obtener los primeros $limit invitados de la tabla temporal
        $guests = $this->table->get_all( $limit );
        $converted = 0;
        foreach ( $guests as $g ) {
            if ( $this->convert_to_wp_user( $g->email ) ) {
                $converted++;
            }
        }
        wp_redirect( add_query_arg( array( 'page' => 'dvc-sync-users', 'converted' => $converted ), admin_url( 'admin.php' ) ) );
        exit;
    }

    public function handle_selected_sync() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'No autorizado' );
        }
        check_admin_referer( 'dvc_selected_sync' );

        if ( isset( $_POST['dvc_selected_emails'] ) && is_array( $_POST['dvc_selected_emails'] ) ) {
            $emails = array_map( 'sanitize_email', $_POST['dvc_selected_emails'] );
            $converted = 0;
            foreach ( $emails as $email ) {
                if ( $this->convert_to_wp_user( $email ) ) {
                    $converted++;
                }
            }
            wp_redirect( add_query_arg( array( 'page' => 'dvc-sync-users', 'converted' => $converted ), admin_url( 'admin.php' ) ) );
        } else {
            wp_redirect( add_query_arg( array( 'page' => 'dvc-sync-users', 'error' => 'no_selected' ), admin_url( 'admin.php' ) ) );
        }
        exit;
    }

    public function handle_single_sync() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'No autorizado' );
        }
        check_admin_referer( 'dvc_single_sync' );

        $email = isset( $_GET['email'] ) ? sanitize_email( $_GET['email'] ) : '';
        if ( $email ) {
            $this->convert_to_wp_user( $email );
        }
        wp_redirect( add_query_arg( array( 'page' => 'dvc-sync-users' ), admin_url( 'admin.php' ) ) );
        exit;
    }
}