<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_Gift {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_dvc_gift', array( $this, 'save_meta_boxes' ) );
        add_action( 'admin_post_dvc_assign_gift', array( $this, 'handle_assign_gift' ) );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'dvc_gift_details',
            'Detalles del Regalo',
            array( $this, 'render_meta_box' ),
            'dvc_gift',
            'normal',
            'high'
        );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'dvc_gift_save', 'dvc_gift_nonce' );
        $product_id = get_post_meta( $post->ID, '_dvc_gift_product_id', true );
        $fictitious = get_post_meta( $post->ID, '_dvc_gift_fictitious_name', true );
        $coupon_id = get_post_meta( $post->ID, '_dvc_gift_coupon_id', true );
        $assigned_users = get_post_meta( $post->ID, '_dvc_assigned_users', true );
        if ( ! is_array( $assigned_users ) ) {
            $assigned_users = array();
        }
        ?>
        <p>
            <label>Producto real (WooCommerce):</label><br>
            <select name="dvc_gift_product_id" style="width:50%;">
                <option value="">-- Ninguno (Ficticio) --</option>
                <?php
                $products = wc_get_products( array( 'limit' => -1, 'status' => 'publish' ) );
                foreach ( $products as $product ) {
                    echo '<option value="' . esc_attr( $product->get_id() ) . '" ' . selected( $product_id, $product->get_id(), false ) . '>' . esc_html( $product->get_name() ) . '</option>';
                }
                ?>
            </select>
        </p>
        <p>
            <label>Nombre de producto ficticio (si no usas producto real):</label><br>
            <input type="text" name="dvc_gift_fictitious" value="<?php echo esc_attr( $fictitious ); ?>" style="width:50%;" />
        </p>
        <p>
            <label>Cupón enlazado (opcional):</label><br>
            <select name="dvc_gift_coupon_id" style="width:50%;">
                <option value="">-- Ninguno --</option>
                <?php
                $coupons = get_posts( array( 'post_type' => 'shop_coupon', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
                foreach ( $coupons as $c ) {
                    echo '<option value="' . esc_attr( $c->ID ) . '" ' . selected( $coupon_id, $c->ID, false ) . '>' . esc_html( $c->post_title ) . '</option>';
                }
                ?>
            </select>
        </p>
        <p>
            <label>Usuarios asignados (IDs separados por coma):</label><br>
            <input type="text" name="dvc_assigned_users" value="<?php echo esc_attr( implode( ',', $assigned_users ) ); ?>" style="width:50%;" />
        </p>
        <?php
    }

    public function save_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['dvc_gift_nonce'] ) || ! wp_verify_nonce( $_POST['dvc_gift_nonce'], 'dvc_gift_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['dvc_gift_product_id'] ) ) {
            update_post_meta( $post_id, '_dvc_gift_product_id', intval( $_POST['dvc_gift_product_id'] ) );
        }
        if ( isset( $_POST['dvc_gift_fictitious'] ) ) {
            update_post_meta( $post_id, '_dvc_gift_fictitious_name', sanitize_text_field( $_POST['dvc_gift_fictitious'] ) );
        }
        if ( isset( $_POST['dvc_gift_coupon_id'] ) ) {
            update_post_meta( $post_id, '_dvc_gift_coupon_id', intval( $_POST['dvc_gift_coupon_id'] ) );
        }
        if ( isset( $_POST['dvc_assigned_users'] ) && ! empty( $_POST['dvc_assigned_users'] ) ) {
            $ids = array_map( 'intval', explode( ',', sanitize_text_field( $_POST['dvc_assigned_users'] ) ) );
            update_post_meta( $post_id, '_dvc_assigned_users', array_filter( $ids ) );
        } else {
            delete_post_meta( $post_id, '_dvc_assigned_users' );
        }
    }

    public function handle_assign_gift() {
        // Para asignar regalos desde el admin (se llama con nonce)
    }

    public static function get_gift_data( $gift_id ) {
        $product_id = get_post_meta( $gift_id, '_dvc_gift_product_id', true );
        $fictitious = get_post_meta( $gift_id, '_dvc_gift_fictitious_name', true );
        $coupon_id = get_post_meta( $gift_id, '_dvc_gift_coupon_id', true );
        return array(
            'product_id'    => $product_id,
            'fictitious'    => $fictitious,
            'coupon_id'     => $coupon_id,
            'assigned_to'   => get_post_meta( $gift_id, '_dvc_assigned_users', true )
        );
    }
}