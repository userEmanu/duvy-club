<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_Campaign {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_dvc_campaign', array( $this, 'save_meta_boxes' ) );
        add_action( 'admin_post_dvc_send_campaign', array( $this, 'handle_send_campaign' ) );
        // No usamos ajax preview por ahora
    }

    public function add_meta_boxes() {
        add_meta_box(
            'dvc_campaign_details',
            'Configuración de Campaña',
            array( $this, 'render_meta_box' ),
            'dvc_campaign',
            'normal',
            'high'
        );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'dvc_campaign_save', 'dvc_campaign_nonce' );
        $type = get_post_meta( $post->ID, '_dvc_campaign_type', true );
        $template_id = get_post_meta( $post->ID, '_dvc_campaign_template_id', true );
        $coupon_id = get_post_meta( $post->ID, '_dvc_campaign_coupon_id', true );
        $gift_id = get_post_meta( $post->ID, '_dvc_campaign_gift_id', true );
        $custom_html = get_post_meta( $post->ID, '_dvc_campaign_html', true );
        $trigger_date = get_post_meta( $post->ID, '_dvc_campaign_trigger_date', true );

        // Campos para recompra y VIP
        $days_inactive = get_post_meta( $post->ID, '_dvc_days_inactive', true );
        $min_orders = get_post_meta( $post->ID, '_dvc_min_orders', true );
        if ( ! $min_orders ) $min_orders = 4;
        if ( ! $days_inactive ) $days_inactive = 30;

        ?>
        <p>
            <label>Tipo de campaña:</label><br>
            <select name="dvc_campaign_type" id="dvc_campaign_type">
                <option value="birthday" <?php selected( $type, 'birthday' ); ?>>Cumpleaños</option>
                <option value="repurchase" <?php selected( $type, 'repurchase' ); ?>>Recompra (inactivos)</option>
                <option value="vip" <?php selected( $type, 'vip' ); ?>>VIP / Fieles</option>
            </select>
        </p>

        <div id="dvc_repurchase_fields" style="<?php echo $type == 'repurchase' ? '' : 'display:none;'; ?>">
            <p>
                <label>Días de inactividad (mínimo):</label><br>
                <input type="number" name="dvc_days_inactive" value="<?php echo esc_attr( $days_inactive ); ?>" min="1" step="1" />
                <small>Clientes que no hayan comprado en los últimos X días.</small>
            </p>
        </div>
        <div id="dvc_vip_fields" style="<?php echo $type == 'vip' ? '' : 'display:none;'; ?>">
            <p>
                <label>Número mínimo de pedidos:</label><br>
                <input type="number" name="dvc_min_orders" value="<?php echo esc_attr( $min_orders ); ?>" min="1" step="1" />
                <small>Clientes con al menos esta cantidad de pedidos completados.</small>
            </p>
        </div>

        <p>
            <label>Plantilla de correo (Módulo 2):</label><br>
            <select name="dvc_campaign_template_id">
                <option value="">-- Usar HTML personalizado --</option>
                <?php
                $templates = get_posts( array( 'post_type' => 'dvc_email_template', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
                foreach ( $templates as $t ) {
                    echo '<option value="' . esc_attr( $t->ID ) . '" ' . selected( $template_id, $t->ID, false ) . '>' . esc_html( $t->post_title ) . '</option>';
                }
                ?>
            </select>
        </p>
        <p>
            <label>HTML personalizado (se usa si no eliges plantilla):</label><br>
            <textarea name="dvc_campaign_html" rows="8" style="width:100%;"><?php echo esc_textarea( $custom_html ); ?></textarea>
            <small>Puedes usar variables: {{firstname}}, {{lastname}}, {{email}}, {{coupon_code}}, {{gift_name}}</small>
        </p>
        <p>
            <label>Cupón enlazado:</label><br>
            <select name="dvc_campaign_coupon_id">
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
            <label>Regalo enlazado:</label><br>
            <select name="dvc_campaign_gift_id">
                <option value="">-- Ninguno --</option>
                <?php
                $gifts = get_posts( array( 'post_type' => 'dvc_gift', 'posts_per_page' => -1, 'post_status' => 'publish' ) );
                foreach ( $gifts as $g ) {
                    echo '<option value="' . esc_attr( $g->ID ) . '" ' . selected( $gift_id, $g->ID, false ) . '>' . esc_html( $g->post_title ) . '</option>';
                }
                ?>
            </select>
        </p>
        <p>
            <label>Fecha de disparo (para programar, opcional):</label><br>
            <input type="datetime-local" name="dvc_campaign_trigger_date" value="<?php echo esc_attr( $trigger_date ); ?>" />
        </p>
        <p>
            <button type="submit" name="dvc_send_now" value="1" class="button button-primary">Enviar ahora</button>
        </p>

        <script>
        document.getElementById('dvc_campaign_type').addEventListener('change', function() {
            var type = this.value;
            document.getElementById('dvc_repurchase_fields').style.display = (type === 'repurchase') ? '' : 'none';
            document.getElementById('dvc_vip_fields').style.display = (type === 'vip') ? '' : 'none';
        });
        </script>
        <?php
    }

    public function save_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['dvc_campaign_nonce'] ) || ! wp_verify_nonce( $_POST['dvc_campaign_nonce'], 'dvc_campaign_save' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $fields = array( 'campaign_type', 'campaign_template_id', 'campaign_coupon_id', 'campaign_gift_id', 'campaign_html', 'campaign_trigger_date', 'days_inactive', 'min_orders' );
        foreach ( $fields as $field ) {
            $key = 'dvc_' . $field;
            if ( isset( $_POST[ $key ] ) ) {
                $meta_key = '_dvc_' . $field;
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST[ $key ] ) );
            }
        }

        if ( isset( $_POST['dvc_send_now'] ) && $_POST['dvc_send_now'] == 1 ) {
            $this->send_campaign( $post_id );
        }
    }

    public function handle_send_campaign() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'No autorizado' );
        }
        $campaign_id = isset( $_GET['campaign_id'] ) ? intval( $_GET['campaign_id'] ) : 0;
        if ( $campaign_id ) {
            $this->send_campaign( $campaign_id );
        }
        wp_redirect( add_query_arg( array( 'post' => $campaign_id, 'action' => 'edit' ), admin_url( 'post.php' ) ) );
        exit;
    }

    public function send_campaign( $campaign_id ) {
        $campaign = get_post( $campaign_id );
        if ( ! $campaign || $campaign->post_type != 'dvc_campaign' ) {
            return false;
        }

        $type = get_post_meta( $campaign_id, '_dvc_campaign_type', true );
        $template_id = get_post_meta( $campaign_id, '_dvc_campaign_template_id', true );
        $custom_html = get_post_meta( $campaign_id, '_dvc_campaign_html', true );
        $coupon_id = get_post_meta( $campaign_id, '_dvc_campaign_coupon_id', true );
        $gift_id = get_post_meta( $campaign_id, '_dvc_campaign_gift_id', true );

        // Obtener destinatarios según el tipo
        $recipients = $this->get_recipients( $campaign_id );

        $sender = new DVC_Email_Sender();
        $sent_count = 0;

        foreach ( $recipients as $recipient ) {
            $user_id = $recipient->user_id;
            $email = $recipient->recipient_email;

            $data = array(
                'firstname' => $recipient->first_name ?: '',
                'lastname'  => $recipient->last_name ?: '',
                'email'     => $email,
                'campaign_title' => $campaign->post_title,
                'coupon_code' => '',
                'gift_name'   => ''
            );

            if ( $coupon_id ) {
                $coupon = new WC_Coupon( $coupon_id );
                $data['coupon_code'] = $coupon->get_code();
                global $wpdb;
                $wpdb->insert( $wpdb->prefix . 'dvc_benefit_usage', array(
                    'user_id'      => $user_id,
                    'benefit_type' => 'coupon',
                    'benefit_id'   => $coupon_id,
                    'campaign_id'  => $campaign_id,
                    'status'       => 'available',
                    'expires_at'   => $coupon->get_date_expires() ? $coupon->get_date_expires()->date( 'Y-m-d H:i:s' ) : null
                ) );
            }

            if ( $gift_id ) {
                $gift = DVC_Gift::get_gift_data( $gift_id );
                $data['gift_name'] = $gift['fictitious'] ?: get_the_title( $gift_id );
                global $wpdb;
                $wpdb->insert( $wpdb->prefix . 'dvc_benefit_usage', array(
                    'user_id'      => $user_id,
                    'benefit_type' => 'gift',
                    'benefit_id'   => $gift_id,
                    'campaign_id'  => $campaign_id,
                    'status'       => 'available'
                ) );
            }

            $subject = '¡Oferta especial para ti!';
            if ( $template_id ) {
                $status = $sender->send( $template_id, $email, $data, $user_id );
            } else {
                $body = $custom_html ?: 'Hola {{firstname}}, gracias por ser parte de Duvy Class. Disfruta de tu beneficio.';
                $status = $sender->send_raw( $email, $subject, $body, $data, $user_id );
            }

            global $wpdb;
            $wpdb->insert( $wpdb->prefix . 'dvc_campaign_recipients', array(
                'campaign_id'     => $campaign_id,
                'user_id'         => $user_id,
                'recipient_email' => $email,
                'status'          => $status ? 'sent' : 'failed',
                'sent_at'         => current_time( 'mysql' )
            ) );

            if ( $status ) {
                $sent_count++;
            }
        }

        return $sent_count;
    }

    private function get_recipients( $campaign_id ) {
        global $wpdb;
        $type = get_post_meta( $campaign_id, '_dvc_campaign_type', true );
        $recipients = array();

        if ( $type == 'birthday' ) {
            $month = date( 'm' );
            $day = date( 'd' );
            $users = get_users( array(
                'meta_key'     => '_dvc_fecha_nacimiento',
                'meta_compare' => 'EXISTS',
                'fields'       => array( 'ID', 'user_email', 'display_name', 'first_name', 'last_name' )
            ) );
            foreach ( $users as $user ) {
                $birth = get_user_meta( $user->ID, '_dvc_fecha_nacimiento', true );
                if ( $birth && date( 'm-d', strtotime( $birth ) ) == $month . '-' . $day ) {
                    $recipients[] = (object) array(
                        'user_id'         => $user->ID,
                        'recipient_email' => $user->user_email,
                        'first_name'      => $user->first_name ?: $user->display_name,
                        'last_name'       => $user->last_name
                    );
                }
            }
        } else if ( $type == 'repurchase' ) {
            $days = intval( get_post_meta( $campaign_id, '_dvc_days_inactive', true ) );
            if ( ! $days ) $days = 30;
            $date_limit = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
            $user_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT customer_id FROM {$wpdb->prefix}posts 
                WHERE post_type = 'shop_order' AND post_status = 'wc-completed' 
                AND customer_id > 0 AND post_date < %s",
                $date_limit
            ) );
            foreach ( $user_ids as $uid ) {
                $user = get_userdata( $uid );
                if ( $user ) {
                    $recipients[] = (object) array(
                        'user_id'         => $uid,
                        'recipient_email' => $user->user_email,
                        'first_name'      => $user->first_name ?: $user->display_name,
                        'last_name'       => $user->last_name
                    );
                }
            }
        } else if ( $type == 'vip' ) {
            $threshold = intval( get_post_meta( $campaign_id, '_dvc_min_orders', true ) );
            if ( ! $threshold ) $threshold = 4;
            $user_ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT customer_id, COUNT(ID) as order_count FROM {$wpdb->prefix}posts 
                WHERE post_type = 'shop_order' AND post_status = 'wc-completed' AND customer_id > 0 
                GROUP BY customer_id HAVING order_count >= %d",
                $threshold
            ) );
            foreach ( $user_ids as $uid ) {
                $user = get_userdata( $uid );
                if ( $user ) {
                    $recipients[] = (object) array(
                        'user_id'         => $uid,
                        'recipient_email' => $user->user_email,
                        'first_name'      => $user->first_name ?: $user->display_name,
                        'last_name'       => $user->last_name
                    );
                }
            }
        }

        return $recipients;
    }
}