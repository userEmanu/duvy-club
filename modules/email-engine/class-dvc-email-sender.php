<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DVC_Email_Sender {

    private $log;

    public function __construct() {
        $this->log = new DVC_Email_Log();
        add_action( 'wp_mail_failed', array( $this, 'log_mail_failure' ), 10, 1 );
    }

    /**
     * Envía un correo usando una plantilla del CPT y reemplaza variables.
     * @param int    $template_id ID de la plantilla dvc_email_template.
     * @param string $to          Correo destino.
     * @param array  $data        Array asociativo con las variables (ej. ['firstname'=>'Juan', 'lastname'=>'Perez']).
     * @param int    $user_id     ID del usuario (opcional, para log).
     * @return bool
     */
    public function send( $template_id, $to, $data = array(), $user_id = 0 ) {
        $template = DVC_Email_Template::get_template( $template_id );
        if ( ! $template ) {
            return false;
        }

        $subject = $template['subject'];
        $body    = $template['body'];

        // Reemplazar variables {{var}} con los datos
        foreach ( $data as $key => $value ) {
            $subject = str_replace( '{{' . $key . '}}', $value, $subject );
            $body    = str_replace( '{{' . $key . '}}', $value, $body );
        }

        // Headers para HTML
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );

        // Enviar
        $status = wp_mail( $to, $subject, $body, $headers );

        // Registrar en el log
        $this->log->add_entry( array(
            'recipient_email'   => $to,
            'recipient_user_id' => $user_id,
            'template_id'       => $template_id,
            'subject'           => $subject,
            'body'              => $body,
            'status'            => $status ? 'sent' : 'failed'
        ) );

        return $status;
    }

    /**
     * Envía un correo con contenido personalizado (sin plantilla guardada).
     */
    public function send_raw( $to, $subject, $body, $data = array(), $user_id = 0 ) {
        // Reemplazar variables básicas
        foreach ( $data as $key => $value ) {
            $subject = str_replace( '{{' . $key . '}}', $value, $subject );
            $body    = str_replace( '{{' . $key . '}}', $value, $body );
        }
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        $status = wp_mail( $to, $subject, $body, $headers );

        $this->log->add_entry( array(
            'recipient_email'   => $to,
            'recipient_user_id' => $user_id,
            'template_id'       => 0,
            'subject'           => $subject,
            'body'              => $body,
            'status'            => $status ? 'sent' : 'failed'
        ) );
        return $status;
    }

    public function log_mail_failure( $wp_error ) {
        // Se puede manejar aquí, pero ya tenemos el log desde el método send
    }
}