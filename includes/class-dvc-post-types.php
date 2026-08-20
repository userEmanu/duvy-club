<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_Post_Types {

    public function __construct() {
        add_action( 'init', array( $this, 'register_all' ) );
    }

    public function register_all() {
        $this->register_email_template();
        $this->register_campaign();
        $this->register_gift();
    }

    private function register_email_template() {
        $labels = array(
            'name'               => 'Plantillas de Correo',
            'singular_name'      => 'Plantilla',
            'add_new'            => 'Añadir Nueva',
            'add_new_item'       => 'Añadir Nueva Plantilla',
            'edit_item'          => 'Editar Plantilla',
            'new_item'           => 'Nueva Plantilla',
            'view_item'          => 'Ver Plantilla',
            'search_items'       => 'Buscar Plantillas',
            'not_found'          => 'No se encontraron plantillas',
            'not_found_in_trash' => 'No hay plantillas en la papelera',
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => false, // Lo añadimos via DVC_Admin
            'supports'            => array( 'title', 'editor' ),
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
        );
        register_post_type( 'dvc_email_template', $args );
    }

    private function register_campaign() {
        $labels = array(
            'name'               => 'Campañas',
            'singular_name'      => 'Campaña',
            'add_new'            => 'Nueva Campaña',
            'add_new_item'       => 'Añadir Nueva Campaña',
            'edit_item'          => 'Editar Campaña',
            'new_item'           => 'Nueva Campaña',
            'view_item'          => 'Ver Campaña',
            'search_items'       => 'Buscar Campañas',
            'not_found'          => 'No se encontraron campañas',
        );
        $args = array(
            'labels'       => $labels,
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false,
            'supports'     => array( 'title' ),
            'capabilities' => array(
                'edit_post'          => 'manage_options',
                'read_post'          => 'manage_options',
                'delete_post'        => 'manage_options',
                'edit_posts'         => 'manage_options',
                'edit_others_posts'  => 'manage_options',
                'delete_posts'       => 'manage_options',
                'publish_posts'      => 'manage_options',
                'read_private_posts' => 'manage_options'
            )
        );
        register_post_type( 'dvc_campaign', $args );
    }

    private function register_gift() {
        $labels = array(
            'name'               => 'Regalos',
            'singular_name'      => 'Regalo',
            'add_new'            => 'Nuevo Regalo',
            'add_new_item'       => 'Añadir Nuevo Regalo',
            'edit_item'          => 'Editar Regalo',
            'new_item'           => 'Nuevo Regalo',
            'view_item'          => 'Ver Regalo',
            'search_items'       => 'Buscar Regalos',
            'not_found'          => 'No se encontraron regalos',
        );
        $args = array(
            'labels'       => $labels,
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => false,
            'supports'     => array( 'title' ),
            'capabilities' => array(
                'edit_post'          => 'manage_options',
                'read_post'          => 'manage_options',
                'delete_post'        => 'manage_options',
                'edit_posts'         => 'manage_options',
                'edit_others_posts'  => 'manage_options',
                'delete_posts'       => 'manage_options',
                'publish_posts'      => 'manage_options',
                'read_private_posts' => 'manage_options'
            )
        );
        register_post_type( 'dvc_gift', $args );
    }
}