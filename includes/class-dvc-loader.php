<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_Loader {

    protected $actions = array();
    protected $filters = array();

    public function __construct() {
        $this->register_autoloader();
        $this->load_dependencies();
    }

    /**
     * Autoload simple: DVC_Algo -> class-dvc-algo.php
     */
    private function register_autoloader() {
        spl_autoload_register( function ( $class_name ) {
            if ( strpos( $class_name, 'DVC_' ) !== 0 ) {
                return;
            }

            // Convertir DVC_Algo_Grande a algo-grande
            $parts = explode( '_', $class_name );
            array_shift( $parts ); // Quitar 'DVC'
            $file_name = 'class-dvc-' . strtolower( implode( '-', $parts ) ) . '.php';

            // Posibles rutas donde buscar
            $paths = array(
                DVC_PLUGIN_DIR . 'includes/',
                DVC_PLUGIN_DIR . 'modules/birthday-field/',
                DVC_PLUGIN_DIR . 'modules/email-engine/',
                DVC_PLUGIN_DIR . 'modules/user-sync/',
                DVC_PLUGIN_DIR . 'modules/coupons/',
                DVC_PLUGIN_DIR . 'modules/gifts/',
                DVC_PLUGIN_DIR . 'modules/campaigns/',
                DVC_PLUGIN_DIR . 'admin/',
                DVC_PLUGIN_DIR . 'public/'
            );

            foreach ( $paths as $path ) {
                if ( file_exists( $path . $file_name ) ) {
                    require_once $path . $file_name;
                    return;
                }
            }
        } );
    }

    private function load_dependencies() {
        // Clases base siempre necesarias
        new DVC_Database();
        new DVC_Post_Types();

        // Módulo 1
        new DVC_Birthday_Field();

        // Módulo 2
        new DVC_Email_Template();
        new DVC_Email_Sender();
        new DVC_Email_Log();

        // Módulo 3
        new DVC_User_Table();
        new DVC_User_Sync();

        // Módulo 4
        new DVC_Coupon();

        // Módulo 5
        new DVC_Gift();

        // Módulo 6
        new DVC_Campaign();

        // Admin y Public
        new DVC_Admin();
        new DVC_Public();

        // Registrar acciones/filtros (se añaden aquí o en cada clase mediante $this->add_action)
    }

    public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->actions[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );
    }

    public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->filters[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        );
    }

    public function run() {
        foreach ( $this->filters as $hook ) {
            add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
        }
        foreach ( $this->actions as $hook ) {
            add_action( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
        }
    }
}