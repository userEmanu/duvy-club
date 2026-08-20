<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_Email_Template {

    public function __construct() {
        add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_dvc_email_template', array( $this, 'save_meta_boxes' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets( $hook ) {
        global $post;
        if ( $hook == 'post-new.php' || $hook == 'post.php' ) {
            if ( $post && $post->post_type == 'dvc_email_template' ) {
                wp_enqueue_style( 'dvc-admin-css', DVC_PLUGIN_URL . 'admin/assets/css/admin.css', array(), DVC_VERSION );
                wp_enqueue_script( 'dvc-admin-js', DVC_PLUGIN_URL . 'admin/assets/js/admin.js', array( 'jquery' ), DVC_VERSION, true );
            }
        }
    }

    public function add_meta_boxes() {
        add_meta_box(
            'dvc_template_subject',
            'Asunto del Correo',
            array( $this, 'render_subject_meta' ),
            'dvc_email_template',
            'normal',
            'high'
        );
        add_meta_box(
            'dvc_template_variables',
            'Variables Disponibles',
            array( $this, 'render_variables_meta' ),
            'dvc_email_template',
            'side',
            'default'
        );
    }

    public function render_subject_meta( $post ) {
        wp_nonce_field( 'dvc_save_template', 'dvc_template_nonce' );
        $subject = get_post_meta( $post->ID, '_dvc_template_subject', true );
        ?>
        <input type="text" name="dvc_template_subject" value="<?php echo esc_attr( $subject ); ?>" style="width:100%;" />
        <?php
    }

    public function render_variables_meta( $post ) {
        $vars = get_post_meta( $post->ID, '_dvc_template_variables', true );
        $list = ! empty( $vars ) ? explode( ',', $vars ) : array( 'firstname', 'lastname', 'email' );
        ?>
        <p><strong>Variables que usa esta plantilla:</strong></p>
        <textarea name="dvc_template_variables" rows="4" style="width:100%;"><?php echo esc_textarea( implode( ', ', $list ) ); ?></textarea>
        <p><small>Separa con comas. Ej: firstname, lastname, email, password, coupon_code</small></p>
        <p><strong>Variables comunes:</strong> firstname, lastname, email, password, coupon_code, gift_name, campaign_title</p>
        <button type="button" onclick="insertVar('{{firstname}}')" class="button">Insertar {{firstname}}</button>
        <button type="button" onclick="insertVar('{{lastname}}')" class="button">Insertar {{lastname}}</button>
        <script>
        function insertVar( varText ) {
            if ( typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor ) {
                tinyMCE.activeEditor.execCommand( 'mceInsertContent', false, varText );
            } else {
                var editor = document.getElementById( 'content' );
                if ( editor ) {
                    editor.value += varText;
                }
            }
        }
        </script>
        <?php
    }

    public function save_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['dvc_template_nonce'] ) || ! wp_verify_nonce( $_POST['dvc_template_nonce'], 'dvc_save_template' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_POST['dvc_template_subject'] ) ) {
            update_post_meta( $post_id, '_dvc_template_subject', sanitize_text_field( $_POST['dvc_template_subject'] ) );
        }
        if ( isset( $_POST['dvc_template_variables'] ) ) {
            $vars = array_map( 'trim', explode( ',', sanitize_text_field( $_POST['dvc_template_variables'] ) ) );
            update_post_meta( $post_id, '_dvc_template_variables', implode( ',', $vars ) );
        }
    }

    /**
     * Obtiene una plantilla y sus metadatos
     */
    public static function get_template( $template_id ) {
        $post = get_post( $template_id );
        if ( ! $post || $post->post_type != 'dvc_email_template' ) {
            return false;
        }
        return array(
            'id'        => $post->ID,
            'title'     => $post->post_title,
            'body'      => $post->post_content,
            'subject'   => get_post_meta( $post->ID, '_dvc_template_subject', true ),
            'variables' => get_post_meta( $post->ID, '_dvc_template_variables', true ),
        );
    }
}