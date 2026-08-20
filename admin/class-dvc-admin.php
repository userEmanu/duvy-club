<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            'Duvy Club',
            'Duvy Club',
            'manage_options',
            'duvy-club',
            array( $this, 'render_dashboard' ),
            'dashicons-awards',
            30
        );

        // Sincronización de usuarios (con interfaz mejorada)
        add_submenu_page(
            'duvy-club',
            'Sincronizar Usuarios',
            'Sincronizar Usuarios',
            'manage_options',
            'dvc-sync-users',
            array( $this, 'render_sync_users' )
        );

        // Plantillas de correo
        add_submenu_page(
            'duvy-club',
            'Plantillas de Correo',
            'Plantillas Correo',
            'manage_options',
            'edit.php?post_type=dvc_email_template'
        );

        // Campañas
        add_submenu_page(
            'duvy-club',
            'Campañas',
            'Campañas',
            'manage_options',
            'edit.php?post_type=dvc_campaign'
        );

        // Regalos
        add_submenu_page(
            'duvy-club',
            'Regalos',
            'Regalos',
            'manage_options',
            'edit.php?post_type=dvc_gift'
        );

        // Cupones (solo si WooCommerce está activo)
        if ( class_exists( 'WooCommerce' ) ) {
            add_submenu_page(
                'duvy-club',
                'Cupones',
                'Cupones',
                'manage_options',
                'edit.php?post_type=shop_coupon'
            );
        } else {
            add_submenu_page(
                'duvy-club',
                'Cupones',
                'Cupones (WooCommerce inactivo)',
                'manage_options',
                'duvy-club-coupon-error',
                array( $this, 'render_woocommerce_missing' )
            );
        }

        // Historial de correos
        add_submenu_page(
            'duvy-club',
            'Historial de Correos',
            'Historial Correos',
            'manage_options',
            'dvc-email-log',
            array( $this, 'render_email_log' )
        );

        // Historial de campañas (para ver destinatarios)
        add_submenu_page(
            'duvy-club',
            'Historial de Campañas',
            'Historial Campañas',
            'manage_options',
            'dvc-campaign-log',
            array( $this, 'render_campaign_log' )
        );
    }

    public function render_dashboard() {
        echo '<div class="wrap"><h1>Duvy Club - Fidelización</h1>';
        echo '<p>Bienvenido al panel de control del plugin. Aquí puedes gestionar todas las funcionalidades.</p>';
        echo '</div>';
    }

    public function render_woocommerce_missing() {
        echo '<div class="wrap"><h1>Cupones</h1>';
        echo '<div class="notice notice-error"><p>El módulo de cupones requiere WooCommerce activo. Por favor, activa WooCommerce para gestionar cupones.</p></div>';
        echo '</div>';
    }

    public function render_sync_users() {
        $sync = new DVC_User_Sync();
        $table = new DVC_User_Table();
        $count = $table->count_all();
        $synced = isset( $_GET['synced'] ) ? intval( $_GET['synced'] ) : 0;
        $converted = isset( $_GET['converted'] ) ? intval( $_GET['converted'] ) : 0;
        ?>
        <div class="wrap">
            <h1>Sincronizar Usuarios Invitados</h1>
            <?php if ( $synced ) : ?>
                <div class="notice notice-success"><p>Se sincronizaron <?php echo $synced; ?> invitados desde pedidos.</p></div>
            <?php endif; ?>
            <?php if ( $converted ) : ?>
                <div class="notice notice-success"><p>Se convirtieron <?php echo $converted; ?> invitados a usuarios WordPress.</p></div>
            <?php endif; ?>
            <p>Usuarios en tabla temporal: <strong><?php echo intval( $count ); ?></strong></p>

            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <!-- Sincronizar todos -->
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'dvc_bulk_sync' ); ?>
                    <input type="hidden" name="action" value="dvc_sync_guest_to_wp">
                    <button type="submit" class="button button-primary">Sincronizar todos los invitados</button>
                </form>

                <!-- Sincronizar X -->
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <?php wp_nonce_field( 'dvc_limit_sync' ); ?>
                    <input type="hidden" name="action" value="dvc_sync_limit">
                    <input type="number" name="dvc_sync_limit" value="10" min="1" step="1" style="width:60px;">
                    <button type="submit" class="button">Sincronizar X usuarios</button>
                </form>
            </div>

            <hr>
            <h2>Lista de invitados</h2>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'dvc_selected_sync' ); ?>
                <input type="hidden" name="action" value="dvc_sync_selected">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr><th><input type="checkbox" id="select-all"></th><th>Email</th><th>Nombre</th><th>Pedidos</th><th>Último pedido</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                    <?php
                    $guests = $table->get_all( 100 ); // paginación pendiente
                    foreach ( $guests as $g ) :
                    ?>
                        <tr>
                            <td><input type="checkbox" name="dvc_selected_emails[]" value="<?php echo esc_attr( $g->email ); ?>"></td>
                            <td><?php echo esc_html( $g->email ); ?></td>
                            <td><?php echo esc_html( $g->first_name . ' ' . $g->last_name ); ?></td>
                            <td><?php echo intval( $g->order_count ); ?></td>
                            <td><?php echo esc_html( $g->last_order_date ); ?></td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin-post.php?action=dvc_sync_single_user&email=' . urlencode( $g->email ) . '&_wpnonce=' . wp_create_nonce( 'dvc_single_sync' ) ) ); ?>" class="button">Convertir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p><button type="submit" class="button button-primary">Convertir seleccionados</button></p>
            </form>
            <script>
            document.getElementById('select-all').addEventListener('change', function(e) {
                document.querySelectorAll('input[name="dvc_selected_emails[]"]').forEach(cb => cb.checked = e.target.checked);
            });
            </script>
        </div>
        <?php
    }

    public function render_email_log() {
        $log = new DVC_Email_Log();
        $logs = $log->get_logs( 50 );
        ?>
        <div class="wrap">
            <h1>Historial de Correos</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr><th>Destinatario</th><th>Asunto</th><th>Estado</th><th>Fecha</th></tr>
                </thead>
                <tbody>
                <?php foreach ( $logs as $entry ) : ?>
                    <tr>
                        <td><?php echo esc_html( $entry->recipient_email ); ?></td>
                        <td><?php echo esc_html( $entry->subject ); ?></td>
                        <td><?php echo esc_html( $entry->status ); ?></td>
                        <td><?php echo esc_html( $entry->sent_at ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function render_campaign_log() {
        global $wpdb;
        $table = $wpdb->prefix . 'dvc_campaign_recipients';
        $logs = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 50" );
        ?>
        <div class="wrap">
            <h1>Historial de Campañas</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr><th>Campaña ID</th><th>Usuario</th><th>Email</th><th>Estado</th><th>Fecha envío</th></tr>
                </thead>
                <tbody>
                <?php foreach ( $logs as $row ) : ?>
                    <tr>
                        <td><?php echo intval( $row->campaign_id ); ?></td>
                        <td><?php echo intval( $row->user_id ); ?></td>
                        <td><?php echo esc_html( $row->recipient_email ); ?></td>
                        <td><?php echo esc_html( $row->status ); ?></td>
                        <td><?php echo esc_html( $row->sent_at ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    public function enqueue_scripts( $hook ) {
        if ( strpos( $hook, 'duvy-club' ) !== false || strpos( $hook, 'dvc-' ) !== false ) {
            wp_enqueue_style( 'dvc-admin-css', DVC_PLUGIN_URL . 'admin/assets/css/admin.css', array(), DVC_VERSION );
            wp_enqueue_script( 'dvc-admin-js', DVC_PLUGIN_URL . 'admin/assets/js/admin.js', array( 'jquery' ), DVC_VERSION, true );
        }
    }
}