<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_Birthday_Field {

    public function __construct() {
        // Mostrar campo en registro y edición de cuenta
        add_action( 'woocommerce_register_form', array( $this, 'display_field' ) );
        add_action( 'woocommerce_edit_account_form', array( $this, 'display_field' ) );

        // Guardar campo
        add_action( 'woocommerce_created_customer', array( $this, 'save_field' ) );
        add_action( 'woocommerce_save_account_details', array( $this, 'save_field' ) );

        // Validar campo
        add_filter( 'woocommerce_registration_errors', array( $this, 'validate_field' ), 10, 3 );
        add_filter( 'woocommerce_save_account_details_errors', array( $this, 'validate_field' ), 10, 3 );

        // Bloquear edición si ya existe (mostrar mensaje + disabled)
        add_action( 'woocommerce_edit_account_form', array( $this, 'maybe_disable_field' ), 1 );
    }

    public function display_field() {
        $user_id = get_current_user_id();
        $value = get_user_meta( $user_id, '_dvc_fecha_nacimiento', true );
        $disabled = ! empty( $value ) ? 'disabled' : '';
        ?>
        <p class="woocommerce-form-row form-row">
            <label for="dvc_birth_date">Fecha de nacimiento <?php if ( $disabled ) echo '(No editable)'; ?></label>
            <input type="date" class="woocommerce-Input woocommerce-Input--text input-text" 
                   name="dvc_birth_date" id="dvc_birth_date" 
                   value="<?php echo esc_attr( $value ); ?>" 
                   <?php echo esc_attr( $disabled ); ?> />
            <?php if ( $disabled ) : ?>
                <small>Esta información ya fue registrada y no se puede modificar.</small>
            <?php endif; ?>
        </p>
        <?php
    }

    public function maybe_disable_field() {
        // Solo para la edición, marcamos que el campo no debe actualizarse si ya existe
        // Lo manejamos en save_field
    }

    public function validate_field( $errors, $username = null, $email = null ) {
        if ( isset( $_POST['dvc_birth_date'] ) && ! empty( $_POST['dvc_birth_date'] ) ) {
            $date = sanitize_text_field( $_POST['dvc_birth_date'] );
            if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
                $errors->add( 'dvc_birth_invalid', 'Formato de fecha de nacimiento inválido (YYYY-MM-DD).' );
            }
            // Validar que no sea mayor a 120 años (opcional)
            $timestamp = strtotime( $date );
            if ( $timestamp && $timestamp > time() ) {
                $errors->add( 'dvc_birth_future', 'La fecha de nacimiento no puede ser en el futuro.' );
            }
        }
        return $errors;
    }

    public function save_field( $user_id ) {
        if ( isset( $_POST['dvc_birth_date'] ) && ! empty( $_POST['dvc_birth_date'] ) ) {
            $existing = get_user_meta( $user_id, '_dvc_fecha_nacimiento', true );
            // Si ya existe, no sobrescribir (bloqueado)
            if ( empty( $existing ) ) {
                $date = sanitize_text_field( $_POST['dvc_birth_date'] );
                update_user_meta( $user_id, '_dvc_fecha_nacimiento', $date );
            }
        }
    }
}