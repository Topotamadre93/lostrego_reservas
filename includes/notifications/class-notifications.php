<?php
/**
 * Clase principal del sistema de notificaciones.
 *
 * Orquesta el envio de notificaciones con sistema de prioridades.
 * Soporta cola de envio y procesamiento por lotes.
 *
 * Prioridades:
 *   CRITICO (1) - Confirmacion de reserva
 *   ALTO    (2) - Lista de espera
 *   MEDIO   (3) - Recordatorio 24h
 *   BAJO    (4) - Recordatorio 1h
 *
 * @package    Lostrego_Reservas
 * @subpackage Lostrego_Reservas/includes/notifications
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lostrego_Notifications
 *
 * Orquestador principal de notificaciones del plugin.
 * Gestiona el envio directo y en cola con prioridades.
 *
 * @since 1.0.0
 */
class Lostrego_Notifications {

	/**
	 * Prioridad critica. Envio inmediato.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const PRIORITY_CRITICO = 1;

	/**
	 * Prioridad alta. Envio inmediato si es posible.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const PRIORITY_ALTO = 2;

	/**
	 * Prioridad media. Puede encolarse.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const PRIORITY_MEDIO = 3;

	/**
	 * Prioridad baja. Siempre se encola.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	const PRIORITY_BAJO = 4;

	/**
	 * Nombre de la opcion para la cola de notificaciones en wp_options.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const QUEUE_OPTION = 'lostrego_notification_queue';

	/**
	 * Instancia del remitente de emails.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Lostrego_Email_Sender
	 */
	private $email_sender;

	/**
	 * Instancia del gestor de plantillas.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Lostrego_Email_Templates
	 */
	private $templates;

	/**
	 * Instancia del reemplazador de placeholders.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Lostrego_Placeholders
	 */
	private $placeholders;

	/**
	 * Mapa de tipos de notificacion con sus prioridades y configuracion.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $notification_types;

	/**
	 * Constructor.
	 *
	 * Inicializa las dependencias y registra los tipos de notificacion.
	 *
	 * @since 1.0.0
	 *
	 * @param Lostrego_Email_Sender    $email_sender  Instancia del remitente de emails.
	 * @param Lostrego_Email_Templates $templates     Instancia del gestor de plantillas.
	 * @param Lostrego_Placeholders    $placeholders  Instancia del reemplazador de placeholders.
	 */
	public function __construct( $email_sender = null, $templates = null, $placeholders = null ) {
		$this->email_sender  = $email_sender;
		$this->templates     = $templates;
		$this->placeholders  = $placeholders;

		$this->register_notification_types();
	}

	/**
	 * Registra los tipos de notificacion disponibles con sus prioridades.
	 *
	 * Cada tipo tiene:
	 * - priority: Nivel de prioridad (CRITICO, ALTO, MEDIO, BAJO).
	 * - template: Nombre de la plantilla asociada.
	 * - enabled_by_default: Si esta activo por defecto.
	 * - description: Descripcion traducible del tipo.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @return void
	 */
	private function register_notification_types() {
		$this->notification_types = array(
			'confirmacion'       => array(
				'priority'           => self::PRIORITY_CRITICO,
				'template'           => 'confirmacion',
				'enabled_by_default' => true,
				'description'        => __( 'Confirmacion de reserva', 'lostrego-reservas' ),
			),
			'lista_espera'       => array(
				'priority'           => self::PRIORITY_ALTO,
				'template'           => 'lista-espera',
				'enabled_by_default' => true,
				'description'        => __( 'Promocion desde lista de espera', 'lostrego-reservas' ),
			),
			'recordatorio_24h'   => array(
				'priority'           => self::PRIORITY_MEDIO,
				'template'           => 'recordatorio',
				'enabled_by_default' => true,
				'description'        => __( 'Recordatorio 24 horas antes', 'lostrego-reservas' ),
			),
			'recordatorio_1h'    => array(
				'priority'           => self::PRIORITY_BAJO,
				'template'           => 'recordatorio',
				'enabled_by_default' => false,
				'description'        => __( 'Recordatorio 1 hora antes', 'lostrego-reservas' ),
			),
			'cancelacion'        => array(
				'priority'           => self::PRIORITY_CRITICO,
				'template'           => 'cancelacion',
				'enabled_by_default' => true,
				'description'        => __( 'Confirmacion de cancelacion', 'lostrego-reservas' ),
			),
			'cambio_evento'      => array(
				'priority'           => self::PRIORITY_ALTO,
				'template'           => 'confirmacion',
				'enabled_by_default' => true,
				'description'        => __( 'Cambios en el evento', 'lostrego-reservas' ),
			),
			'codigo_acceso'      => array(
				'priority'           => self::PRIORITY_CRITICO,
				'template'           => 'confirmacion',
				'enabled_by_default' => true,
				'description'        => __( 'Codigo de acceso al panel', 'lostrego-reservas' ),
			),
		);

		/**
		 * Filtra los tipos de notificacion registrados.
		 *
		 * Permite a otros plugins o temas anadir, modificar o eliminar
		 * tipos de notificacion.
		 *
		 * @since 1.0.0
		 *
		 * @param array $notification_types Array de tipos de notificacion.
		 */
		$this->notification_types = apply_filters(
			'lostrego_notification_types',
			$this->notification_types
		);
	}

	/**
	 * Envia una notificacion de forma inmediata.
	 *
	 * Procesa la notificacion segun su tipo, renderiza la plantilla
	 * correspondiente, reemplaza los placeholders y envia el email.
	 * Si falla el envio HTML, intenta enviar en texto plano como fallback.
	 *
	 * Las notificaciones criticas siempre se envian inmediatamente.
	 * Las de prioridad baja se encolan automaticamente a menos que
	 * se fuerce el envio inmediato.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type    Tipo de notificacion (ej: 'confirmacion', 'cancelacion').
	 * @param int    $user_id ID del usuario destinatario.
	 * @param array  $data    Datos contextuales para la notificacion. Puede incluir:
	 *                        - 'evento_id'    (int)    ID del evento.
	 *                        - 'reserva_id'   (int)    ID de la reserva.
	 *                        - 'attachments'  (array)  Archivos adjuntos.
	 *                        - 'force'        (bool)   Forzar envio inmediato.
	 *
	 * @return bool True si el envio fue exitoso, false en caso contrario.
	 */
	public function send( $type, $user_id, $data = array() ) {
		// Verificar que el tipo de notificacion existe.
		if ( ! isset( $this->notification_types[ $type ] ) ) {
			if ( function_exists( 'lostrego_log' ) ) {
				lostrego_log( 'error', sprintf(
					/* translators: %s: notification type name */
					__( 'Tipo de notificacion desconocido: %s', 'lostrego-reservas' ),
					$type
				), array( 'type' => $type, 'user_id' => $user_id ) );
			}
			return false;
		}

		// Verificar que la notificacion esta habilitada.
		if ( ! $this->is_enabled( $type ) ) {
			return false;
		}

		// Obtener datos del usuario.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			if ( function_exists( 'lostrego_log' ) ) {
				lostrego_log( 'error', sprintf(
					/* translators: %d: user ID */
					__( 'Usuario no encontrado para notificacion: %d', 'lostrego-reservas' ),
					$user_id
				), array( 'type' => $type, 'user_id' => $user_id ) );
			}
			return false;
		}

		$notification_config = $this->notification_types[ $type ];

		// Encolar notificaciones de baja prioridad si no se fuerza el envio.
		$force = isset( $data['force'] ) ? (bool) $data['force'] : false;
		if ( ! $force && $notification_config['priority'] >= self::PRIORITY_BAJO ) {
			return $this->queue( $type, $user_id, $data );
		}

		// Renderizar plantilla.
		$template_name = $notification_config['template'];
		$html_body     = '';
		$subject       = '';

		if ( $this->templates ) {
			$html_body = $this->templates->render_template( $template_name, $data );
		}

		// Reemplazar placeholders en el cuerpo.
		if ( $this->placeholders && ! empty( $html_body ) ) {
			$html_body = $this->placeholders->replace( $html_body, $data );
		}

		// Construir asunto del email.
		$subject = $this->get_subject_for_type( $type, $data );
		if ( $this->placeholders ) {
			$subject = $this->placeholders->replace( $subject, $data );
		}

		// Preparar adjuntos.
		$attachments = isset( $data['attachments'] ) ? (array) $data['attachments'] : array();

		// Enviar email.
		$sent = false;
		if ( $this->email_sender ) {
			if ( ! empty( $html_body ) ) {
				$sent = $this->email_sender->send_html(
					$user->user_email,
					$subject,
					$html_body,
					$attachments
				);
			}

			// Fallback a texto plano si falla el HTML.
			if ( ! $sent ) {
				$plain_body = wp_strip_all_tags( $html_body );
				$sent = $this->email_sender->send_plain_fallback(
					$user->user_email,
					$subject,
					$plain_body
				);
			}
		}

		// Guardar notificacion en BD como fallback si falla el email.
		if ( ! $sent ) {
			$this->save_notification_to_db( $type, $user_id, $subject, $html_body, $data );
		}

		// Registrar en logs.
		if ( function_exists( 'lostrego_log' ) ) {
			lostrego_log(
				$sent ? 'info' : 'warning',
				$sent
					? __( 'Notificacion enviada correctamente', 'lostrego-reservas' )
					: __( 'Notificacion guardada en BD (fallo envio email)', 'lostrego-reservas' ),
				array(
					'type'    => $type,
					'user_id' => $user_id,
					'email'   => $user->user_email,
					'sent'    => $sent,
				)
			);
		}

		/**
		 * Se ejecuta despues de intentar enviar una notificacion.
		 *
		 * @since 1.0.0
		 *
		 * @param string $type    Tipo de notificacion.
		 * @param int    $user_id ID del usuario.
		 * @param bool   $sent    Si el envio fue exitoso.
		 * @param array  $data    Datos de la notificacion.
		 */
		do_action( 'lostrego_notification_sent', $type, $user_id, $sent, $data );

		return $sent;
	}

	/**
	 * Encola una notificacion para envio posterior.
	 *
	 * Las notificaciones encoladas se procesan via WP-Cron
	 * ordenadas por prioridad. Utiles para notificaciones no criticas
	 * que pueden esperar al siguiente ciclo de cron.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type    Tipo de notificacion.
	 * @param int    $user_id ID del usuario destinatario.
	 * @param array  $data    Datos contextuales para la notificacion.
	 *
	 * @return bool True si se encolo correctamente, false en caso contrario.
	 */
	public function queue( $type, $user_id, $data = array() ) {
		if ( ! isset( $this->notification_types[ $type ] ) ) {
			return false;
		}

		$queue = get_option( self::QUEUE_OPTION, array() );

		$queue[] = array(
			'type'      => sanitize_text_field( $type ),
			'user_id'   => absint( $user_id ),
			'data'      => $data,
			'priority'  => $this->notification_types[ $type ]['priority'],
			'queued_at' => current_time( 'mysql' ),
		);

		// Ordenar por prioridad (menor numero = mayor prioridad).
		usort( $queue, array( $this, 'sort_by_priority' ) );

		$saved = update_option( self::QUEUE_OPTION, $queue, false );

		if ( $saved && function_exists( 'lostrego_log' ) ) {
			lostrego_log( 'info', __( 'Notificacion encolada', 'lostrego-reservas' ), array(
				'type'    => $type,
				'user_id' => $user_id,
			) );
		}

		return $saved;
	}

	/**
	 * Procesa la cola de notificaciones pendientes.
	 *
	 * Envia las notificaciones encoladas ordenadas por prioridad.
	 * Se ejecuta normalmente via WP-Cron. Procesa un maximo de
	 * elementos por lote para evitar timeouts.
	 *
	 * @since 1.0.0
	 *
	 * @param int $batch_size Numero maximo de notificaciones a procesar por lote. Por defecto 20.
	 *
	 * @return array Resultado del procesamiento con claves 'processed', 'success', 'failed'.
	 */
	public function process_queue( $batch_size = 20 ) {
		$queue   = get_option( self::QUEUE_OPTION, array() );
		$results = array(
			'processed' => 0,
			'success'   => 0,
			'failed'    => 0,
		);

		if ( empty( $queue ) ) {
			return $results;
		}

		$batch_size = absint( $batch_size );
		if ( $batch_size < 1 ) {
			$batch_size = 20;
		}

		$to_process = array_splice( $queue, 0, $batch_size );

		foreach ( $to_process as $item ) {
			$type    = isset( $item['type'] ) ? $item['type'] : '';
			$user_id = isset( $item['user_id'] ) ? absint( $item['user_id'] ) : 0;
			$data    = isset( $item['data'] ) ? $item['data'] : array();

			if ( empty( $type ) || empty( $user_id ) ) {
				$results['failed']++;
				$results['processed']++;
				continue;
			}

			// Forzar envio inmediato al procesar la cola.
			$data['force'] = true;
			$sent = $this->send( $type, $user_id, $data );

			if ( $sent ) {
				$results['success']++;
			} else {
				$results['failed']++;
			}
			$results['processed']++;
		}

		// Actualizar la cola con los elementos restantes.
		update_option( self::QUEUE_OPTION, $queue, false );

		if ( function_exists( 'lostrego_log' ) ) {
			lostrego_log( 'info', __( 'Cola de notificaciones procesada', 'lostrego-reservas' ), $results );
		}

		return $results;
	}

	/**
	 * Devuelve todos los tipos de notificacion registrados.
	 *
	 * Cada tipo incluye su prioridad, plantilla asociada,
	 * estado por defecto y descripcion traducida.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array asociativo de tipos de notificacion.
	 */
	public function get_notification_types() {
		return $this->notification_types;
	}

	/**
	 * Comprueba si un tipo de notificacion esta habilitado.
	 *
	 * Consulta la opcion guardada en wp_options. Si no hay opcion
	 * guardada, devuelve el valor por defecto del tipo.
	 *
	 * @since 1.0.0
	 *
	 * @param string $type Tipo de notificacion a comprobar.
	 *
	 * @return bool True si esta habilitado, false si esta deshabilitado o no existe.
	 */
	public function is_enabled( $type ) {
		if ( ! isset( $this->notification_types[ $type ] ) ) {
			return false;
		}

		$settings = get_option( 'lostrego_notifications_settings', array() );

		if ( isset( $settings[ $type ] ) ) {
			return (bool) $settings[ $type ];
		}

		// Valor por defecto si no hay configuracion guardada.
		return (bool) $this->notification_types[ $type ]['enabled_by_default'];
	}

	/**
	 * Obtiene el asunto del email segun el tipo de notificacion.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param string $type Tipo de notificacion.
	 * @param array  $data Datos contextuales.
	 *
	 * @return string Asunto del email traducido.
	 */
	private function get_subject_for_type( $type, $data = array() ) {
		$festival_name = get_option( 'lostrego_festival_name', __( 'Lostrego Festival de Cine', 'lostrego-reservas' ) );

		$subjects = array(
			'confirmacion'     => sprintf(
				/* translators: %s: festival name */
				__( 'Confirmacion de reserva - %s', 'lostrego-reservas' ),
				$festival_name
			),
			'lista_espera'     => sprintf(
				/* translators: %s: festival name */
				__( 'Plaza disponible en lista de espera - %s', 'lostrego-reservas' ),
				$festival_name
			),
			'recordatorio_24h' => sprintf(
				/* translators: %s: festival name */
				__( 'Recordatorio: tu evento es manana - %s', 'lostrego-reservas' ),
				$festival_name
			),
			'recordatorio_1h'  => sprintf(
				/* translators: %s: festival name */
				__( 'Recordatorio: tu evento es en 1 hora - %s', 'lostrego-reservas' ),
				$festival_name
			),
			'cancelacion'      => sprintf(
				/* translators: %s: festival name */
				__( 'Cancelacion confirmada - %s', 'lostrego-reservas' ),
				$festival_name
			),
			'cambio_evento'    => sprintf(
				/* translators: %s: festival name */
				__( 'Cambios en tu evento - %s', 'lostrego-reservas' ),
				$festival_name
			),
			'codigo_acceso'    => sprintf(
				/* translators: %s: festival name */
				__( 'Tu codigo de acceso - %s', 'lostrego-reservas' ),
				$festival_name
			),
		);

		/**
		 * Filtra los asuntos de email por tipo de notificacion.
		 *
		 * @since 1.0.0
		 *
		 * @param array  $subjects Array de asuntos.
		 * @param string $type     Tipo de notificacion.
		 * @param array  $data     Datos contextuales.
		 */
		$subjects = apply_filters( 'lostrego_notification_subjects', $subjects, $type, $data );

		if ( isset( $subjects[ $type ] ) ) {
			return $subjects[ $type ];
		}

		return sprintf(
			/* translators: %s: festival name */
			__( 'Notificacion - %s', 'lostrego-reservas' ),
			$festival_name
		);
	}

	/**
	 * Guarda una notificacion en la base de datos como fallback.
	 *
	 * Cuando el email no puede enviarse, la notificacion se guarda
	 * en la BD para que el usuario pueda verla en "Mis Reservas".
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param string $type    Tipo de notificacion.
	 * @param int    $user_id ID del usuario.
	 * @param string $subject Asunto de la notificacion.
	 * @param string $body    Cuerpo de la notificacion.
	 * @param array  $data    Datos contextuales.
	 *
	 * @return bool True si se guardo correctamente, false en caso contrario.
	 */
	private function save_notification_to_db( $type, $user_id, $subject, $body, $data = array() ) {
		$stored = get_user_meta( $user_id, 'lostrego_pending_notifications', true );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$stored[] = array(
			'type'       => sanitize_text_field( $type ),
			'subject'    => sanitize_text_field( $subject ),
			'body'       => wp_kses_post( $body ),
			'data'       => $data,
			'created_at' => current_time( 'mysql' ),
			'read'       => false,
		);

		return update_user_meta( $user_id, 'lostrego_pending_notifications', $stored );
	}

	/**
	 * Funcion de ordenacion por prioridad para usort.
	 *
	 * Ordena de menor a mayor (prioridad 1 = CRITICO va primero).
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param array $a Primer elemento.
	 * @param array $b Segundo elemento.
	 *
	 * @return int Resultado de la comparacion.
	 */
	private function sort_by_priority( $a, $b ) {
		$priority_a = isset( $a['priority'] ) ? (int) $a['priority'] : self::PRIORITY_BAJO;
		$priority_b = isset( $b['priority'] ) ? (int) $b['priority'] : self::PRIORITY_BAJO;

		if ( $priority_a === $priority_b ) {
			return 0;
		}

		return ( $priority_a < $priority_b ) ? -1 : 1;
	}
}
