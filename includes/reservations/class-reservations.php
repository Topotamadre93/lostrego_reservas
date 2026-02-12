<?php
/**
 * Clase principal de reservas.
 *
 * Gestiona las operaciones CRUD sobre la tabla wp_reservas:
 * obtener, crear, actualizar y contar reservas.
 *
 * @package    Lostrego_Reservas
 * @subpackage Lostrego_Reservas/includes/reservations
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase Lostrego_Reservations.
 *
 * Proporciona acceso centralizado a la tabla de reservas del plugin.
 * Todas las consultas usan prepared statements y los resultados
 * se registran en el sistema de logs.
 *
 * @since 1.0.0
 */
class Lostrego_Reservations {

	/**
	 * Nombre de la tabla de reservas (con prefijo WP).
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $table_name;

	/**
	 * Instancia de la base de datos global de WordPress.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    wpdb
	 */
	private $db;

	/**
	 * Estados de reserva permitidos.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $valid_statuses = array(
		'pendiente',
		'confirmada',
		'cancelada',
		'asistio',
		'no_show',
		'lista_espera',
	);

	/**
	 * Constructor.
	 *
	 * Inicializa la referencia a $wpdb y el nombre de la tabla.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->db         = $wpdb;
		$this->table_name = $wpdb->prefix . 'reservas';
	}

	/**
	 * Obtiene una reserva por su ID.
	 *
	 * Devuelve un objeto con todos los campos de la reserva
	 * o null si no existe.
	 *
	 * @since 1.0.0
	 *
	 * @param int $id ID de la reserva.
	 * @return object|null Objeto con los datos de la reserva o null.
	 */
	public function get_reservation( $id ) {
		$id = absint( $id );

		if ( 0 === $id ) {
			return null;
		}

		$reservation = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$id
			)
		);

		if ( null === $reservation ) {
			lostrego_log( 'warning', sprintf(
				/* translators: %d: reservation ID */
				__( 'Reserva con ID %d no encontrada.', 'lostrego-reservas' ),
				$id
			), array( 'reserva_id' => $id ) );
		}

		return $reservation;
	}

	/**
	 * Obtiene todas las reservas de un usuario.
	 *
	 * Devuelve un array de objetos ordenados por fecha descendente.
	 * Soporta paginacion mediante $args.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $user_id ID del usuario de WordPress.
	 * @param array $args    {
	 *     Argumentos opcionales.
	 *
	 *     @type string $status  Filtrar por estado (ver $valid_statuses).
	 *     @type int    $limit   Numero maximo de resultados. Por defecto 50.
	 *     @type int    $offset  Desplazamiento para paginacion. Por defecto 0.
	 *     @type string $orderby Campo para ordenar. Por defecto 'id'.
	 *     @type string $order   Direccion del orden: ASC o DESC. Por defecto 'DESC'.
	 * }
	 * @return array Array de objetos de reserva (puede estar vacio).
	 */
	public function get_reservations_by_user( $user_id, $args = array() ) {
		$user_id = absint( $user_id );

		if ( 0 === $user_id ) {
			return array();
		}

		$defaults = array(
			'status'  => '',
			'limit'   => 50,
			'offset'  => 0,
			'orderby' => 'id',
			'order'   => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		// TODO: Construir query con filtros opcionales.
		// TODO: Ejecutar prepared statement.
		// TODO: Registrar consulta en logs si aplica.

		return array();
	}

	/**
	 * Obtiene todas las reservas de un evento.
	 *
	 * Devuelve un array de objetos ordenados por fecha de creacion.
	 * Soporta filtrado por estado y paginacion.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $event_id ID del evento (post ID del CPT).
	 * @param array $args     {
	 *     Argumentos opcionales.
	 *
	 *     @type string $status  Filtrar por estado.
	 *     @type int    $limit   Numero maximo de resultados. Por defecto 100.
	 *     @type int    $offset  Desplazamiento para paginacion. Por defecto 0.
	 *     @type string $orderby Campo para ordenar. Por defecto 'id'.
	 *     @type string $order   Direccion del orden: ASC o DESC. Por defecto 'ASC'.
	 * }
	 * @return array Array de objetos de reserva (puede estar vacio).
	 */
	public function get_reservations_by_event( $event_id, $args = array() ) {
		$event_id = absint( $event_id );

		if ( 0 === $event_id ) {
			return array();
		}

		$defaults = array(
			'status'  => '',
			'limit'   => 100,
			'offset'  => 0,
			'orderby' => 'id',
			'order'   => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		// TODO: Construir query dinamica con prepared statements.
		// TODO: Aplicar filtros de estado si se proporcionan.
		// TODO: Registrar en logs.

		return array();
	}

	/**
	 * Crea una nueva reserva en la base de datos.
	 *
	 * Inserta un registro en wp_reservas con los datos proporcionados.
	 * Genera automaticamente el hash_ticket unico para el QR.
	 * Registra la accion en logs.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data {
	 *     Datos de la reserva. Campos obligatorios marcados con *.
	 *
	 *     @type int    $evento_id*                          ID del evento.
	 *     @type int    $usuario_id*                         ID del usuario WP.
	 *     @type int    $plazas*                             Numero de plazas reservadas.
	 *     @type string $estado                              Estado inicial. Por defecto 'confirmada'.
	 *     @type string $acompanante_tipo                    Quien acompana al nino.
	 *     @type bool   $ninos_sin_adulto                    Si el nino asiste sin adulto.
	 *     @type string $contacto_emergencia_1_nombre        Nombre contacto emergencia 1.
	 *     @type string $contacto_emergencia_1_relacion      Relacion contacto emergencia 1.
	 *     @type string $contacto_emergencia_1_telefono      Telefono contacto emergencia 1.
	 *     @type string $contacto_emergencia_2_nombre        Nombre contacto emergencia 2.
	 *     @type string $contacto_emergencia_2_relacion      Relacion contacto emergencia 2.
	 *     @type string $contacto_emergencia_2_telefono      Telefono contacto emergencia 2.
	 *     @type bool   $autorizacion_fotos                  Autorizacion fotos/video.
	 *     @type bool   $autorizacion_primeros_auxilios      Autorizacion primeros auxilios.
	 *     @type string $alergias_condiciones                Alergias o condiciones medicas.
	 * }
	 * @return int|false ID de la reserva creada o false en caso de error.
	 */
	public function create_reservation( $data ) {
		if ( empty( $data['evento_id'] ) || empty( $data['usuario_id'] ) ) {
			lostrego_log( 'error', __( 'Datos obligatorios ausentes al crear reserva.', 'lostrego-reservas' ), array(
				'data' => $data,
			) );
			return false;
		}

		// TODO: Sanitizar todos los campos de $data.
		// TODO: Generar hash_ticket unico con wp_generate_password() o similar.
		// TODO: Insertar en BD con $wpdb->insert() y formatos apropiados.
		// TODO: Registrar creacion en logs con lostrego_log().
		// TODO: Disparar hook 'lostrego_reservation_created'.
		// TODO: Devolver $wpdb->insert_id o false.

		return false;
	}

	/**
	 * Actualiza el estado de una reserva.
	 *
	 * Cambia el campo 'estado' de la reserva indicada.
	 * Valida que el estado proporcionado sea uno de los permitidos.
	 * Registra el cambio en logs.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $id     ID de la reserva.
	 * @param string $status Nuevo estado (ver $valid_statuses).
	 * @return bool True si se actualizo correctamente, false en caso contrario.
	 */
	public function update_status( $id, $status ) {
		$id = absint( $id );

		if ( 0 === $id ) {
			return false;
		}

		if ( ! in_array( $status, $this->valid_statuses, true ) ) {
			lostrego_log( 'error', sprintf(
				/* translators: %s: invalid status value */
				__( 'Estado de reserva no valido: %s', 'lostrego-reservas' ),
				sanitize_text_field( $status )
			), array(
				'reserva_id' => $id,
				'estado'     => $status,
			) );
			return false;
		}

		// TODO: Obtener estado anterior para el log.
		// TODO: Ejecutar UPDATE con prepared statement.
		// TODO: Registrar cambio de estado en logs.
		// TODO: Disparar hook 'lostrego_reservation_status_changed'.
		// TODO: Si el nuevo estado es 'cancelada', disparar acciones de lista de espera.

		return false;
	}

	/**
	 * Cuenta las reservas de un evento, opcionalmente filtradas por estado.
	 *
	 * Devuelve el numero total de reservas (o plazas reservadas)
	 * para el evento indicado.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $event_id ID del evento.
	 * @param string $status   Estado a filtrar. Vacio para todos los estados.
	 *                         Por defecto 'confirmada'.
	 * @return int Numero de reservas que cumplen los criterios.
	 */
	public function count_by_event( $event_id, $status = 'confirmada' ) {
		$event_id = absint( $event_id );

		if ( 0 === $event_id ) {
			return 0;
		}

		// TODO: Construir query COUNT con prepared statement.
		// TODO: Aplicar filtro de estado si no esta vacio.
		// TODO: Considerar sumar campo 'plazas' en lugar de contar filas.

		return 0;
	}

	/**
	 * Genera un hash unico no predecible para el ticket/QR.
	 *
	 * Combina el ID de reserva, usuario, timestamp y una sal aleatoria
	 * para producir un hash SHA-256 que se usa como identificador del QR.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param int $reservation_id ID de la reserva.
	 * @param int $user_id        ID del usuario.
	 * @return string Hash SHA-256 de 64 caracteres.
	 */
	private function generate_ticket_hash( $reservation_id, $user_id ) {
		$salt = wp_generate_password( 32, true, true );
		$raw  = $reservation_id . '|' . $user_id . '|' . microtime( true ) . '|' . $salt;

		return hash( 'sha256', $raw );
	}

	/**
	 * Devuelve los estados de reserva validos.
	 *
	 * Util para validaciones externas y para generar opciones de select.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array de estados validos con clave y etiqueta traducida.
	 */
	public function get_valid_statuses() {
		return array(
			'pendiente'    => __( 'Pendiente', 'lostrego-reservas' ),
			'confirmada'   => __( 'Confirmada', 'lostrego-reservas' ),
			'cancelada'    => __( 'Cancelada', 'lostrego-reservas' ),
			'asistio'      => __( 'Asistio', 'lostrego-reservas' ),
			'no_show'      => __( 'No-show', 'lostrego-reservas' ),
			'lista_espera' => __( 'Lista de espera', 'lostrego-reservas' ),
		);
	}
}
