<?php
/**
 * Combinable filter system for log entries.
 *
 * Provides a flexible filter builder that supports combining
 * multiple filter criteria (user, event, action type, date range,
 * text search) into a single WHERE clause.
 *
 * @package    Lostrego_Reservas
 * @subpackage Lostrego_Reservas/includes/logs
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lostrego_Log_Filters
 *
 * Builds combinable WHERE clauses for log queries.
 * All filters can be combined freely: by user, event,
 * action type, date range, and free text search.
 *
 * @since 1.0.0
 */
class Lostrego_Log_Filters {

	/**
	 * Database table name for logs.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor.
	 *
	 * Sets up the table name using the WordPress database prefix.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'reservas_logs';
	}

	/**
	 * Apply filters to a WP_Query-like array or SQL query string.
	 *
	 * Takes an existing query and a set of filters, then returns
	 * the modified query with filter conditions applied.
	 *
	 * @since 1.0.0
	 *
	 * @param string $query   The base SQL query string.
	 * @param array  $filters Associative array of filter criteria.
	 *                        Supported keys:
	 *                        - 'usuario'     (int)    User ID.
	 *                        - 'evento'      (int)    Event/post ID.
	 *                        - 'tipo_accion' (string) Action type.
	 *                        - 'level'       (string) Log level (error/warning/info/debug).
	 *                        - 'fecha_desde' (string) Start date (Y-m-d).
	 *                        - 'fecha_hasta' (string) End date (Y-m-d).
	 *                        - 'busqueda'    (string) Free text search in detalles.
	 * @return string The modified SQL query string with WHERE conditions.
	 */
	public function apply_filters( $query, $filters ) {
		$where_clause = $this->build_where_clause( $filters );

		if ( ! empty( $where_clause ) ) {
			// Check if query already has a WHERE clause.
			if ( stripos( $query, 'WHERE' ) !== false ) {
				$query .= " AND {$where_clause}";
			} else {
				$query .= " WHERE {$where_clause}";
			}
		}

		return $query;
	}

	/**
	 * Get available filter options for the UI dropdowns.
	 *
	 * Queries the database to get unique values for each
	 * filterable field, providing data for the filter form
	 * select elements.
	 *
	 * @since 1.0.0
	 *
	 * @return array {
	 *     Associative array of filter options.
	 *
	 *     @type array $tipos_accion Array of unique action types.
	 *     @type array $usuarios     Array of user objects (ID, display_name).
	 *     @type array $eventos      Array of event objects (ID, post_title).
	 *     @type array $levels       Array of available log levels.
	 * }
	 */
	public function get_filter_options() {
		global $wpdb;

		$options = array();

		// Get unique action types from logs.
		$options['tipos_accion'] = $wpdb->get_col(
			"SELECT DISTINCT tipo_accion FROM {$this->table_name} WHERE tipo_accion != '' ORDER BY tipo_accion ASC"
		);

		// Get unique users from logs.
		$user_ids = $wpdb->get_col(
			"SELECT DISTINCT usuario FROM {$this->table_name} WHERE usuario > 0 ORDER BY usuario ASC"
		);
		$options['usuarios'] = array();
		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( absint( $user_id ) );
			if ( $user ) {
				$options['usuarios'][] = array(
					'id'     => absint( $user_id ),
					'nombre' => $user->display_name,
				);
			}
		}

		// Get unique events from logs.
		$evento_ids = $wpdb->get_col(
			"SELECT DISTINCT evento FROM {$this->table_name} WHERE evento > 0 ORDER BY evento ASC"
		);
		$options['eventos'] = array();
		foreach ( $evento_ids as $evento_id ) {
			$title = get_the_title( absint( $evento_id ) );
			if ( $title ) {
				$options['eventos'][] = array(
					'id'     => absint( $evento_id ),
					'titulo' => $title,
				);
			}
		}

		// Available log levels.
		$options['levels'] = array(
			'error'   => __( 'Error', 'lostrego-reservas' ),
			'warning' => __( 'Advertencia', 'lostrego-reservas' ),
			'info'    => __( 'Informacion', 'lostrego-reservas' ),
			'debug'   => __( 'Depuracion', 'lostrego-reservas' ),
		);

		return $options;
	}

	/**
	 * Build a SQL WHERE clause from an array of filters.
	 *
	 * Constructs a safe, parameterized WHERE clause string
	 * by combining all applicable filter conditions with AND.
	 * Uses $wpdb->prepare() for all user-supplied values.
	 *
	 * @since 1.0.0
	 *
	 * @param array $filters Associative array of filter criteria.
	 *                       Supported keys: 'usuario', 'evento', 'tipo_accion',
	 *                       'level', 'fecha_desde', 'fecha_hasta', 'busqueda'.
	 * @return string The WHERE clause without the 'WHERE' keyword,
	 *                or empty string if no filters apply.
	 */
	public function build_where_clause( $filters ) {
		global $wpdb;

		if ( ! is_array( $filters ) || empty( $filters ) ) {
			return '';
		}

		$conditions = array();

		// Filter by user ID.
		if ( isset( $filters['usuario'] ) && $filters['usuario'] > 0 ) {
			$conditions[] = $wpdb->prepare( 'usuario = %d', absint( $filters['usuario'] ) );
		}

		// Filter by event ID.
		if ( isset( $filters['evento'] ) && $filters['evento'] > 0 ) {
			$conditions[] = $wpdb->prepare( 'evento = %d', absint( $filters['evento'] ) );
		}

		// Filter by action type.
		if ( isset( $filters['tipo_accion'] ) && ! empty( $filters['tipo_accion'] ) ) {
			$conditions[] = $wpdb->prepare( 'tipo_accion = %s', sanitize_text_field( $filters['tipo_accion'] ) );
		}

		// Filter by log level (stored inside JSON detalles).
		if ( isset( $filters['level'] ) && ! empty( $filters['level'] ) ) {
			$level_safe   = sanitize_text_field( $filters['level'] );
			$conditions[] = $wpdb->prepare( 'detalles LIKE %s', '%"level":"' . $wpdb->esc_like( $level_safe ) . '"%' );
		}

		// Filter by start date.
		if ( isset( $filters['fecha_desde'] ) && ! empty( $filters['fecha_desde'] ) ) {
			$fecha_desde  = sanitize_text_field( $filters['fecha_desde'] );
			$conditions[] = $wpdb->prepare( 'fecha >= %s', $fecha_desde . ' 00:00:00' );
		}

		// Filter by end date.
		if ( isset( $filters['fecha_hasta'] ) && ! empty( $filters['fecha_hasta'] ) ) {
			$fecha_hasta  = sanitize_text_field( $filters['fecha_hasta'] );
			$conditions[] = $wpdb->prepare( 'fecha <= %s', $fecha_hasta . ' 23:59:59' );
		}

		// Free text search in detalles JSON field.
		if ( isset( $filters['busqueda'] ) && ! empty( $filters['busqueda'] ) ) {
			$busqueda     = sanitize_text_field( $filters['busqueda'] );
			$conditions[] = $wpdb->prepare( 'detalles LIKE %s', '%' . $wpdb->esc_like( $busqueda ) . '%' );
		}

		if ( empty( $conditions ) ) {
			return '';
		}

		return implode( ' AND ', $conditions );
	}
}
