<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_Activator {

    public static function activate() {
        global $wpdb;

        // Crear tablas
        $database = new DVC_Database();
        $database->create_tables();

        // Guardar versión
        update_option( 'dvc_db_version', DVC_VERSION );

        // Registrar CPTs (flush rewrite rules)
        $post_types = new DVC_Post_Types();
        $post_types->register_all();
        flush_rewrite_rules();

        // Crear plantilla de correo por defecto para bienvenida
        self::create_default_templates();
    }

    private static function create_default_templates() {
        // Si no existe una plantilla de bienvenida, la creamos
        $existing = get_posts( array(
            'post_type'      => 'dvc_email_template',
            'title'          => 'Bienvenida a Duvy Class',
            'posts_per_page' => 1,
            'post_status'    => 'any'
        ) );

        if ( empty( $existing ) ) {
            $post_id = wp_insert_post( array(
                'post_title'   => 'Bienvenida a Duvy Class',
                'post_content' => 'Hola {{firstname}} {{lastname}},<br><br>Tu cuenta en Duvy Class ha sido activada. Tu contraseña temporal es: <strong>{{password}}</strong><br><br>Te recomendamos cambiarla al iniciar sesión.<br><br>¡Gracias por confiar en nosotros!',
                'post_status'  => 'publish',
                'post_type'    => 'dvc_email_template',
            ) );
            if ( $post_id ) {
                update_post_meta( $post_id, '_dvc_template_subject', '¡Bienvenido a Duvy Class!' );
                update_post_meta( $post_id, '_dvc_template_variables', 'firstname, lastname, password, email' );
            }
        }
    }
}