<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_Public {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'woocommerce_before_cart', array( $this, 'display_benefits' ) );
        add_action( 'woocommerce_before_checkout_form', array( $this, 'display_benefits' ) );
        add_action( 'wp_ajax_dvc_add_gift_to_cart', array( $this, 'ajax_add_gift_to_cart' ) );
        add_action( 'wp_ajax_nopriv_dvc_add_gift_to_cart', array( $this, 'ajax_add_gift_to_cart' ) );
    }

    public function enqueue_scripts() {
        if ( is_cart() || is_checkout() ) {
            wp_enqueue_style( 'dvc-public-css', DVC_PLUGIN_URL . 'public/assets/css/public.css', array(), DVC_VERSION );
            wp_enqueue_script( 'dvc-public-js', DVC_PLUGIN_URL . 'public/assets/js/public.js', array( 'jquery' ), DVC_VERSION, true );
            wp_localize_script( 'dvc-public-js', 'dvc_ajax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'dvc_gift_nonce' )
            ) );
        }
    }

    public function display_benefits() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return;
        }

        global $wpdb;
        // Buscar cupones disponibles
        $coupons = $wpdb->get_results( $wpdb->prepare(
            "SELECT benefit_id FROM {$wpdb->prefix}dvc_benefit_usage 
            WHERE user_id = %d AND benefit_type = 'coupon' AND status = 'available'",
            $user_id
        ) );
        // Buscar regalos disponibles
        $gifts = $wpdb->get_results( $wpdb->prepare(
            "SELECT benefit_id FROM {$wpdb->prefix}dvc_benefit_usage 
            WHERE user_id = %d AND benefit_type = 'gift' AND status = 'available'",
            $user_id
        ) );

        if ( empty( $coupons ) && empty( $gifts ) ) {
            return;
        }

        echo '<div class="dvc-benefits-box">';
        echo '<h3>Tus beneficios disponibles</h3>';
        if ( ! empty( $coupons ) ) {
            echo '<ul>';
            foreach ( $coupons as $c ) {
                $coupon = new WC_Coupon( $c->benefit_id );
                if ( $coupon->get_id() ) {
                    echo '<li>Cupón: <strong>' . esc_html( $coupon->get_code() ) . '</strong> - ' . esc_html( $coupon->get_description() ) . '</li>';
                }
            }
            echo '</ul>';
        }
        if ( ! empty( $gifts ) ) {
            echo '<ul>';
            foreach ( $gifts as $g ) {
                $gift_post = get_post( $g->benefit_id );
                if ( $gift_post ) {
                    $gift_data = DVC_Gift::get_gift_data( $g->benefit_id );
                    $product_id = $gift_data['product_id'];
                    $fictitious = $gift_data['fictitious'];
                    $name = $fictitious ?: $gift_post->post_title;
                    echo '<li>Regalo: <strong>' . esc_html( $name ) . '</strong>';
                    if ( $product_id ) {
                        echo ' <a href="#" class="dvc-add-gift" data-gift-id="' . esc_attr( $g->benefit_id ) . '" data-product-id="' . esc_attr( $product_id ) . '">Añadir al carrito</a>';
                    } else {
                        echo ' (Regalo ficticio, contacta con atención al cliente)';
                    }
                    echo '</li>';
                }
            }
            echo '</ul>';
        }
        echo '</div>';
    }

    public function ajax_add_gift_to_cart() {
        check_ajax_referer( 'dvc_gift_nonce', 'nonce' );

        $gift_id = isset( $_POST['gift_id'] ) ? intval( $_POST['gift_id'] ) : 0;
        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        $user_id = get_current_user_id();

        if ( ! $gift_id || ! $product_id || ! $user_id ) {
            wp_send_json_error( 'Datos incompletos' );
        }

        // Verificar que el regalo esté disponible para este usuario
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}dvc_benefit_usage 
            WHERE user_id = %d AND benefit_id = %d AND benefit_type = 'gift' AND status = 'available'",
            $user_id,
            $gift_id
        ) );

        if ( ! $exists ) {
            wp_send_json_error( 'Este regalo no está disponible.' );
        }

        // Añadir producto al carrito (precio 0)
        $cart = WC()->cart;
        $added = $cart->add_to_cart( $product_id, 1 );
        if ( $added ) {
            // Marcar regalo como usado
            $wpdb->update(
                $wpdb->prefix . 'dvc_benefit_usage',
                array( 'status' => 'used', 'used_at' => current_time( 'mysql' ) ),
                array( 'id' => $exists )
            );
            wp_send_json_success( 'Regalo añadido al carrito.' );
        } else {
            wp_send_json_error( 'No se pudo añadir el regalo al carrito.' );
        }
    }
}