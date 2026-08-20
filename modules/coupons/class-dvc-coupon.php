<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_Coupon {

    public function __construct() {
        // Añadir campos extra al cupón nativo de WooCommerce
        add_action( 'woocommerce_coupon_options', array( $this, 'add_coupon_fields' ), 10, 2 );
        add_action( 'woocommerce_coupon_options_save', array( $this, 'save_coupon_fields' ), 10, 2 );

        // Asignación de usuarios mediante meta
        add_action( 'woocommerce_coupon_options_usage_restriction', array( $this, 'add_user_assignment_field' ) );
        add_action( 'woocommerce_coupon_options_save', array( $this, 'save_user_assignment_field' ), 20, 2 );

        // Trazar uso del cupón en nuestra tabla
        add_action( 'woocommerce_order_status_completed', array( $this, 'track_coupon_usage' ), 10, 1 );
        add_action( 'woocommerce_order_status_processing', array( $this, 'track_coupon_usage' ), 10, 1 );

        // Limitar uso por usuario (si no está configurado en el cupón)
        add_filter( 'woocommerce_coupon_is_valid', array( $this, 'validate_user_limit' ), 10, 3 );
    }

    public function add_coupon_fields() {
        global $post;
        $campaign_id = get_post_meta( $post->ID, '_dvc_campaign_id', true );
        ?>
        <div class="options_group">
            <p class="form-field">
                <label>ID de Campaña origen</label>
                <input type="text" name="dvc_campaign_id" value="<?php echo esc_attr( $campaign_id ); ?>" />
                <span class="description">ID de la campaña que generó este cupón (opcional).</span>
            </p>
        </div>
        <?php
    }

    public function add_user_assignment_field() {
        global $post;
        $assigned_users = get_post_meta( $post->ID, '_dvc_assigned_users', true );
        if ( ! is_array( $assigned_users ) ) {
            $assigned_users = array();
        }
        ?>
        <div class="options_group">
            <p class="form-field">
                <label>Usuarios asignados (IDs separados por coma)</label>
                <input type="text" name="dvc_assigned_users" value="<?php echo esc_attr( implode( ',', $assigned_users ) ); ?>" style="width:50%;" />
                <span class="description">Dejar vacío para que sea global.</span>
            </p>
        </div>
        <?php
    }

    public function save_coupon_fields( $post_id, $coupon ) {
        if ( isset( $_POST['dvc_campaign_id'] ) ) {
            update_post_meta( $post_id, '_dvc_campaign_id', intval( $_POST['dvc_campaign_id'] ) );
        }
    }

    public function save_user_assignment_field( $post_id, $coupon ) {
        if ( isset( $_POST['dvc_assigned_users'] ) && ! empty( $_POST['dvc_assigned_users'] ) ) {
            $ids = array_map( 'intval', explode( ',', sanitize_text_field( $_POST['dvc_assigned_users'] ) ) );
            update_post_meta( $post_id, '_dvc_assigned_users', array_filter( $ids ) );
        } else {
            delete_post_meta( $post_id, '_dvc_assigned_users' );
        }
    }

    public function track_coupon_usage( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }
        $coupons = $order->get_coupons();
        foreach ( $coupons as $coupon_item ) {
            $coupon_code = $coupon_item->get_code();
            $coupon = new WC_Coupon( $coupon_code );
            $coupon_id = $coupon->get_id();

            // Registrar uso en nuestra tabla
            global $wpdb;
            $user_id = $order->get_customer_id();
            if ( $user_id ) {
                $exists = $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}dvc_benefit_usage 
                    WHERE user_id = %d AND benefit_type = 'coupon' AND benefit_id = %d AND status = 'available'",
                    $user_id,
                    $coupon_id
                ) );
                if ( $exists ) {
                    $wpdb->update(
                        $wpdb->prefix . 'dvc_benefit_usage',
                        array( 'status' => 'used', 'used_at' => current_time( 'mysql' ) ),
                        array( 'id' => $exists )
                    );
                } else {
                    // Si no existe, la creamos (puede ser cupón manual)
                    $wpdb->insert( $wpdb->prefix . 'dvc_benefit_usage', array(
                        'user_id'      => $user_id,
                        'benefit_type' => 'coupon',
                        'benefit_id'   => $coupon_id,
                        'status'       => 'used',
                        'used_at'      => current_time( 'mysql' )
                    ) );
                }
            }
        }
    }

    public function validate_user_limit( $valid, $coupon, $discount ) {
        if ( ! $valid ) {
            return $valid;
        }
        $user_id = get_current_user_id();
        if ( ! $user_id ) {
            return $valid;
        }

        $assigned_users = get_post_meta( $coupon->get_id(), '_dvc_assigned_users', true );
        if ( ! empty( $assigned_users ) && is_array( $assigned_users ) ) {
            if ( ! in_array( $user_id, $assigned_users ) ) {
                return false; // No asignado a este usuario
            }
        }

        // Verificar límite de uso por usuario (si el cupón no tiene límite, lo ponemos en 1 por defecto si es de Duvy)
        $usage_limit_per_user = $coupon->get_usage_limit_per_user();
        if ( $usage_limit_per_user <= 0 ) {
            // Si es un cupón creado por Duvy, forzamos 1 uso por defecto a menos que se haya configurado específicamente
            $campaign_id = get_post_meta( $coupon->get_id(), '_dvc_campaign_id', true );
            if ( $campaign_id ) {
                // Contar usos de este cupón por este usuario
                global $wpdb;
                $used_count = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}dvc_benefit_usage 
                    WHERE user_id = %d AND benefit_type = 'coupon' AND benefit_id = %d AND status = 'used'",
                    $user_id,
                    $coupon->get_id()
                ) );
                if ( $used_count >= 1 ) {
                    return false;
                }
            }
        }

        return $valid;
    }
}