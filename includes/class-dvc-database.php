<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_Database {

    public function __construct() {
        add_action( 'plugins_loaded', array( $this, 'check_db_version' ) );
    }

    public function check_db_version() {
        if ( get_option( 'dvc_db_version' ) !== DVC_VERSION ) {
            $this->create_tables();
            update_option( 'dvc_db_version', DVC_VERSION );
        }
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        $sqls = array();

        // 1. Tabla de clientes invitados (temporal)
        $sqls[] = "CREATE TABLE {$prefix}dvc_customers (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email varchar(100) NOT NULL,
            first_name varchar(50) NOT NULL,
            last_name varchar(50) NOT NULL,
            document varchar(50) DEFAULT '',
            phone varchar(20) DEFAULT '',
            billing_address text DEFAULT '',
            shipping_address text DEFAULT '',
            order_count int(11) DEFAULT 0,
            last_order_date datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email)
        ) $charset_collate;";

        // 2. Log de correos
        $sqls[] = "CREATE TABLE {$prefix}dvc_email_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            recipient_email varchar(100) NOT NULL,
            recipient_user_id bigint(20) DEFAULT NULL,
            template_id bigint(20) DEFAULT NULL,
            subject varchar(255) NOT NULL,
            body longtext NOT NULL,
            status varchar(20) DEFAULT 'sent', -- sent, failed, opened (opcional)
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            error_message text DEFAULT '',
            PRIMARY KEY (id),
            KEY recipient_email (recipient_email),
            KEY sent_at (sent_at)
        ) $charset_collate;";

        // 3. Trazabilidad de beneficios (cupones y regalos)
        $sqls[] = "CREATE TABLE {$prefix}dvc_benefit_usage (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            benefit_type varchar(20) NOT NULL, -- coupon, gift
            benefit_id bigint(20) NOT NULL,
            campaign_id bigint(20) DEFAULT NULL,
            status varchar(20) DEFAULT 'available', -- available, used, expired
            used_at datetime DEFAULT NULL,
            expires_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY benefit_type (benefit_type)
        ) $charset_collate;";

        // 4. Destinatarios de campañas
        $sqls[] = "CREATE TABLE {$prefix}dvc_campaign_recipients (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            campaign_id bigint(20) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            recipient_email varchar(100) NOT NULL,
            status varchar(20) DEFAULT 'pending', -- pending, sent, failed
            sent_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY campaign_id (campaign_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $sqls as $sql ) {
            dbDelta( $sql );
        }
    }
}