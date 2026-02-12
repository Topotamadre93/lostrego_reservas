<?php
/**
 * Formularios condicionales de reserva.
 *
 * Genera formularios de reserva que se adaptan dinamicamente
 * segun el tipo de evento: infantil, adulto, mixto, etc.
 * Los campos condicionales (datos familiares, contactos de emergencia)
 * se muestran u ocultan segun las reglas definidas en CLAUDE.md.
 *
 * @package    Lostrego_Reservas
 * @subpackage Lostrego_Reservas/includes/reservations
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase Lostrego_Reservation_Form.
 *
 * Renderiza los formularios de reserva con campos condicionales
 * y validacion del lado del cliente.
 *
 * @since 1.0.0
 */
class Lostrego_Reservation_Form {

	/**
	 * Tipos de evento que se consideran infantiles.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $infantil_types = array(
		'taller_infantil',
	);

	/**
	 * Tipos de evento con restriccion de edad (+18).
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $adult_types = array();

	/**
	 * Tipos de evento mixtos (pueden tener ninos y adultos).
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $mixed_types = array(
		'proyeccion',
		'actividad',
	);

	/**
	 * Constructor.
	 *
	 * Inicializa los tipos de evento y aplica filtros para permitir
	 * personalizacion desde temas o plugins externos.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		/**
		 * Filtra los tipos de evento considerados infantiles.
		 *
		 * @since 1.0.0
		 *
		 * @param array $infantil_types Array de slugs de tipos infantiles.
		 */
		$this->infantil_types = apply_filters( 'lostrego_infantil_event_types', $this->infantil_types );

		/**
		 * Filtra los tipos de evento con restriccion de edad.
		 *
		 * @since 1.0.0
		 *
		 * @param array $adult_types Array de slugs de tipos para adultos.
		 */
		$this->adult_types = apply_filters( 'lostrego_adult_event_types', $this->adult_types );
	}

	/**
	 * Renderiza el formulario de reserva para un evento.
	 *
	 * Genera el HTML completo del formulario incluyendo:
	 * - Campos basicos obligatorios (7 campos del usuario).
	 * - Campos condicionales segun tipo de evento.
	 * - Campos de contacto de emergencia si aplica.
	 * - Nonce de seguridad.
	 * - Checkboxes RGPD.
	 * - Boton de envio.
	 *
	 * Si el usuario ya tiene cuenta, los campos se pre-rellenan.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id ID del evento (post ID del CPT).
	 * @return string HTML del formulario completo.
	 */
	public function render_form( $event_id ) {
		$event_id = absint( $event_id );

		if ( 0 === $event_id ) {
			return '<p class="lostrego-error">' .
				esc_html__( 'Evento no valido.', 'lostrego-reservas' ) .
				'</p>';
		}

		// TODO: Verificar que el evento existe y esta abierto a reservas.
		// TODO: Verificar aforo disponible.
		// TODO: Obtener campos del formulario con get_form_fields().
		// TODO: Pre-rellenar datos si el usuario esta logueado.
		// TODO: Generar nonce con wp_nonce_field().
		// TODO: Renderizar campos condicionales con get_conditional_fields().
		// TODO: Anadir checkboxes RGPD obligatorios.
		// TODO: Registrar script de validacion en tiempo real.
		// TODO: Aplicar filtro 'lostrego_reservation_form_html'.

		ob_start();
		?>
		<form id="lostrego-reservation-form"
			class="lostrego-form lostrego-form--reserva"
			method="post"
			action=""
			data-event-id="<?php echo esc_attr( $event_id ); ?>">

			<?php wp_nonce_field( 'lostrego_reservar_' . $event_id, 'lostrego_nonce' ); ?>

			<input type="hidden" name="action" value="lostrego_procesar_reserva" />
			<input type="hidden" name="evento_id" value="<?php echo esc_attr( $event_id ); ?>" />

			<!-- TODO: Campos basicos del usuario -->
			<!-- TODO: Campos condicionales -->
			<!-- TODO: Checkboxes RGPD -->
			<!-- TODO: Boton envio -->

			<button type="submit" class="lostrego-btn lostrego-btn--primary">
				<?php esc_html_e( 'Reservar plaza', 'lostrego-reservas' ); ?>
			</button>
		</form>
		<?php

		return ob_get_clean();
	}

	/**
	 * Obtiene la lista de campos del formulario para un evento.
	 *
	 * Devuelve un array de definiciones de campo con tipo, etiqueta,
	 * obligatoriedad y opciones. Incluye los 7 campos basicos
	 * mas los campos condicionales del tipo de evento.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id ID del evento.
	 * @return array Array asociativo de campos. Cada campo contiene:
	 *               'type'        => string  Tipo de input HTML.
	 *               'label'       => string  Etiqueta traducida.
	 *               'required'    => bool    Si el campo es obligatorio.
	 *               'options'     => array   Opciones para select/radio (si aplica).
	 *               'placeholder' => string  Texto placeholder (si aplica).
	 *               'validation'  => array   Reglas de validacion.
	 */
	public function get_form_fields( $event_id ) {
		$event_id = absint( $event_id );

		// Campos basicos obligatorios (siempre presentes).
		$fields = array(
			'nombre' => array(
				'type'        => 'text',
				'label'       => __( 'Nombre', 'lostrego-reservas' ),
				'required'    => true,
				'placeholder' => __( 'Tu nombre', 'lostrego-reservas' ),
				'validation'  => array( 'min_length' => 2 ),
			),
			'apellidos' => array(
				'type'        => 'text',
				'label'       => __( 'Apellidos', 'lostrego-reservas' ),
				'required'    => true,
				'placeholder' => __( 'Tus apellidos', 'lostrego-reservas' ),
				'validation'  => array( 'min_length' => 2 ),
			),
			'email' => array(
				'type'        => 'email',
				'label'       => __( 'Email', 'lostrego-reservas' ),
				'required'    => true,
				'placeholder' => __( 'tu@email.com', 'lostrego-reservas' ),
				'validation'  => array( 'is_email' => true ),
			),
			'fecha_nacimiento' => array(
				'type'     => 'date',
				'label'    => __( 'Fecha de nacimiento', 'lostrego-reservas' ),
				'required' => true,
			),
			'codigo_postal' => array(
				'type'        => 'text',
				'label'       => __( 'Codigo postal', 'lostrego-reservas' ),
				'required'    => true,
				'placeholder' => __( '00000', 'lostrego-reservas' ),
				'validation'  => array(
					'pattern'   => '^\d{5}$',
					'maxlength' => 5,
				),
			),
			'genero' => array(
				'type'     => 'select',
				'label'    => __( 'Genero', 'lostrego-reservas' ),
				'required' => true,
				'options'  => array(
					''                   => __( '-- Seleccionar --', 'lostrego-reservas' ),
					'hombre'             => __( 'Hombre', 'lostrego-reservas' ),
					'mujer'              => __( 'Mujer', 'lostrego-reservas' ),
					'otro'               => __( 'Otro', 'lostrego-reservas' ),
					'prefiero_no_decir'  => __( 'Prefiero no decir', 'lostrego-reservas' ),
				),
			),
			'telefono' => array(
				'type'        => 'text',
				'label'       => __( 'Telefono', 'lostrego-reservas' ),
				'required'    => false,
				'placeholder' => __( '+34 600 000 000', 'lostrego-reservas' ),
			),
		);

		// Anadir campos condicionales segun tipo de evento.
		$conditional = $this->get_conditional_fields( $event_id );
		$fields      = array_merge( $fields, $conditional );

		/**
		 * Filtra los campos del formulario de reserva.
		 *
		 * @since 1.0.0
		 *
		 * @param array $fields   Array de definiciones de campo.
		 * @param int   $event_id ID del evento.
		 */
		return apply_filters( 'lostrego_reservation_form_fields', $fields, $event_id );
	}

	/**
	 * Determina si un evento es de tipo infantil.
	 *
	 * Comprueba la taxonomia del evento y sus meta datos
	 * para saber si se trata de un evento infantil o mixto
	 * donde pueden asistir ninos.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id ID del evento.
	 * @return bool True si el evento es infantil o mixto con ninos.
	 */
	public function is_infantil_event( $event_id ) {
		$event_id = absint( $event_id );

		if ( 0 === $event_id ) {
			return false;
		}

		// TODO: Obtener tipo de evento desde taxonomia.
		// TODO: Comprobar contra $this->infantil_types y $this->mixed_types.
		// TODO: Comprobar meta 'clasificacion_edad' del evento.
		// TODO: Aplicar filtro 'lostrego_is_infantil_event'.

		return false;
	}

	/**
	 * Determina si un evento requiere mayoria de edad (+18).
	 *
	 * Comprueba la clasificacion de edad del evento para saber
	 * si se requiere verificacion de DNI en puerta.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id ID del evento.
	 * @return bool True si el evento es para mayores de 18 anos.
	 */
	public function is_adult_event( $event_id ) {
		$event_id = absint( $event_id );

		if ( 0 === $event_id ) {
			return false;
		}

		// TODO: Obtener meta 'clasificacion_edad' del evento.
		// TODO: Comprobar si es '+18'.
		// TODO: Comprobar contra $this->adult_types.
		// TODO: Aplicar filtro 'lostrego_is_adult_event'.

		return false;
	}

	/**
	 * Obtiene los campos condicionales segun el tipo de evento.
	 *
	 * Devuelve campos adicionales que se muestran dependiendo
	 * del tipo de evento:
	 *
	 * - Eventos infantiles/mixtos: datos familiares, acompanante.
	 * - Nino sin adulto: contactos de emergencia, autorizaciones.
	 * - Eventos +18: checkbox confirmacion mayoria de edad.
	 *
	 * @since 1.0.0
	 *
	 * @param int $event_id ID del evento.
	 * @return array Array de campos condicionales con la misma estructura
	 *               que get_form_fields().
	 */
	public function get_conditional_fields( $event_id ) {
		$event_id    = absint( $event_id );
		$fields      = array();
		$is_infantil = $this->is_infantil_event( $event_id );
		$is_adult    = $this->is_adult_event( $event_id );

		// Campos para eventos infantiles y mixtos.
		if ( $is_infantil ) {
			$fields['asiste_con_ninos'] = array(
				'type'     => 'checkbox',
				'label'    => __( 'Asiste con ninos', 'lostrego-reservas' ),
				'required' => true,
			);

			$fields['numero_ninos'] = array(
				'type'       => 'number',
				'label'      => __( 'Numero de ninos que trae', 'lostrego-reservas' ),
				'required'   => true,
				'validation' => array(
					'min' => 1,
					'max' => 10,
				),
				'conditional' => array(
					'field' => 'asiste_con_ninos',
					'value' => true,
				),
			);

			$fields['acompanante_tipo'] = array(
				'type'     => 'select',
				'label'    => __( 'Quien acompana hoy', 'lostrego-reservas' ),
				'required' => true,
				'options'  => array(
					''              => __( '-- Seleccionar --', 'lostrego-reservas' ),
					'ambos_padres'  => __( 'Ambos padres', 'lostrego-reservas' ),
					'madre'         => __( 'Madre', 'lostrego-reservas' ),
					'padre'         => __( 'Padre', 'lostrego-reservas' ),
					'abuelos'       => __( 'Abuelos', 'lostrego-reservas' ),
					'otro'          => __( 'Otro', 'lostrego-reservas' ),
				),
			);

			$fields['ninos_sin_adulto'] = array(
				'type'     => 'checkbox',
				'label'    => __( 'El nino asiste sin adulto acompanante', 'lostrego-reservas' ),
				'required' => false,
			);

			// Contactos de emergencia (obligatorios si nino sin adulto).
			$fields['contacto_emergencia_1_nombre'] = array(
				'type'        => 'text',
				'label'       => __( 'Contacto emergencia 1 - Nombre', 'lostrego-reservas' ),
				'required'    => false,
				'conditional' => array(
					'field' => 'ninos_sin_adulto',
					'value' => true,
				),
			);

			$fields['contacto_emergencia_1_relacion'] = array(
				'type'        => 'select',
				'label'       => __( 'Contacto emergencia 1 - Relacion', 'lostrego-reservas' ),
				'required'    => false,
				'options'     => array(
					''         => __( '-- Seleccionar --', 'lostrego-reservas' ),
					'madre'    => __( 'Madre', 'lostrego-reservas' ),
					'padre'    => __( 'Padre', 'lostrego-reservas' ),
					'tutor'    => __( 'Tutor', 'lostrego-reservas' ),
					'familiar' => __( 'Familiar', 'lostrego-reservas' ),
				),
				'conditional' => array(
					'field' => 'ninos_sin_adulto',
					'value' => true,
				),
			);

			$fields['contacto_emergencia_1_telefono'] = array(
				'type'        => 'text',
				'label'       => __( 'Contacto emergencia 1 - Telefono', 'lostrego-reservas' ),
				'required'    => false,
				'conditional' => array(
					'field' => 'ninos_sin_adulto',
					'value' => true,
				),
			);

			$fields['contacto_emergencia_2_nombre'] = array(
				'type'        => 'text',
				'label'       => __( 'Contacto emergencia 2 - Nombre', 'lostrego-reservas' ),
				'required'    => false,
				'conditional' => array(
					'field' => 'ninos_sin_adulto',
					'value' => true,
				),
			);

			$fields['contacto_emergencia_2_relacion'] = array(
				'type'        => 'select',
				'label'       => __( 'Contacto emergencia 2 - Relacion', 'lostrego-reservas' ),
				'required'    => false,
				'options'     => array(
					''         => __( '-- Seleccionar --', 'lostrego-reservas' ),
					'madre'    => __( 'Madre', 'lostrego-reservas' ),
					'padre'    => __( 'Padre', 'lostrego-reservas' ),
					'tutor'    => __( 'Tutor', 'lostrego-reservas' ),
					'familiar' => __( 'Familiar', 'lostrego-reservas' ),
				),
				'conditional' => array(
					'field' => 'ninos_sin_adulto',
					'value' => true,
				),
			);

			$fields['contacto_emergencia_2_telefono'] = array(
				'type'        => 'text',
				'label'       => __( 'Contacto emergencia 2 - Telefono', 'lostrego-reservas' ),
				'required'    => false,
				'conditional' => array(
					'field' => 'ninos_sin_adulto',
					'value' => true,
				),
			);

			$fields['autorizacion_fotos'] = array(
				'type'        => 'checkbox',
				'label'       => __( 'Autorizo la toma de fotos y video', 'lostrego-reservas' ),
				'required'    => false,
				'conditional' => array(
					'field' => 'ninos_sin_adulto',
					'value' => true,
				),
			);

			$fields['autorizacion_primeros_auxilios'] = array(
				'type'        => 'checkbox',
				'label'       => __( 'Autorizo la asistencia de primeros auxilios si fuera necesario', 'lostrego-reservas' ),
				'required'    => false,
				'conditional' => array(
					'field' => 'ninos_sin_adulto',
					'value' => true,
				),
			);

			$fields['alergias_condiciones'] = array(
				'type'        => 'textarea',
				'label'       => __( 'Alergias o condiciones medicas', 'lostrego-reservas' ),
				'required'    => false,
				'placeholder' => __( 'Indique alergias o condiciones medicas relevantes (opcional)', 'lostrego-reservas' ),
				'conditional' => array(
					'field' => 'ninos_sin_adulto',
					'value' => true,
				),
			);
		}

		// Campo para eventos +18.
		if ( $is_adult ) {
			$fields['confirmar_mayor_edad'] = array(
				'type'     => 'checkbox',
				'label'    => __( 'Confirmo que soy mayor de 18 anos', 'lostrego-reservas' ),
				'required' => true,
			);
		}

		/**
		 * Filtra los campos condicionales del formulario de reserva.
		 *
		 * @since 1.0.0
		 *
		 * @param array $fields   Array de campos condicionales.
		 * @param int   $event_id ID del evento.
		 */
		return apply_filters( 'lostrego_reservation_conditional_fields', $fields, $event_id );
	}
}
