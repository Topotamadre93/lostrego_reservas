<?php
/**
 * Backend log viewer for Lostrego Reservas.
 *
 * Provides a paginated, filterable interface for viewing
 * log entries in the WordPress admin panel. Supports
 * expandable detail views with formatted JSON.
 *
 * @package    Lostrego_Reservas
 * @subpackage Lostrego_Reservas/includes/logs
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lostrego_Log_Viewer
 *
 * Manages the backend log viewing interface with pagination,
 * filtering, and detail expansion. Integrates with
 * Lostrego_Log_Filters for combinable filter support.
 *
 * @since 1.0.0
 */
class Lostrego_Log_Viewer {

	/**
	 * Database table name for logs.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $table_name;

	/**
	 * Default number of logs per page.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $default_per_page = 25;

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
	 * Get paginated log entries with optional filters.
	 *
	 * Retrieves log entries from the database, applying any
	 * provided filters (user, event, action type, date range,
	 * text search). Results are paginated.
	 *
	 * @since 1.0.0
	 *
	 * @param array $filters  Optional. Associative array of filter criteria.
	 *                        Supported keys: 'usuario', 'evento', 'tipo_accion',
	 *                        'fecha_desde', 'fecha_hasta', 'busqueda', 'level'.
	 * @param int   $page     Optional. Page number (1-based). Default 1.
	 * @param int   $per_page Optional. Number of results per page. Default 25.
	 * @return array {
	 *     Array of log data.
	 *
	 *     @type array  $logs       Array of log entry objects.
	 *     @type int    $total      Total number of matching entries.
	 *     @type int    $page       Current page number.
	 *     @type int    $per_page   Results per page.
	 *     @type int    $total_pages Total number of pages.
	 * }
	 */
	public function get_logs( $filters = array(), $page = 1, $per_page = 0 ) {
		global $wpdb;

		if ( $per_page <= 0 ) {
			$per_page = $this->default_per_page;
		}

		$page     = max( 1, absint( $page ) );
		$per_page = absint( $per_page );
		$offset   = ( $page - 1 ) * $per_page;

		// Build WHERE clause using Log_Filters.
		$log_filters  = new Lostrego_Log_Filters();
		$where_clause = $log_filters->build_where_clause( $filters );

		// Get total count for pagination.
		$total = $this->get_total_count( $filters );

		// Query logs with pagination.
		$query = "SELECT * FROM {$this->table_name}";
		if ( ! empty( $where_clause ) ) {
			$query .= " WHERE {$where_clause}";
		}
		$query .= " ORDER BY fecha DESC";
		$query .= $wpdb->prepare( " LIMIT %d OFFSET %d", $per_page, $offset );

		$logs = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'logs'        => $logs ? $logs : array(),
			'total'       => $total,
			'page'        => $page,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total / $per_page ),
		);
	}

	/**
	 * Get detailed information for a single log entry.
	 *
	 * Retrieves the full log entry including the parsed JSON
	 * details field, user display name, and event title.
	 *
	 * @since 1.0.0
	 *
	 * @param int $log_id The log entry ID.
	 * @return object|null The log entry with parsed details, or null if not found.
	 */
	public function get_log_detail( $log_id ) {
		global $wpdb;

		$log_id = absint( $log_id );
		if ( $log_id <= 0 ) {
			return null;
		}

		$log = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_name} WHERE id = %d",
				$log_id
			)
		);

		if ( ! $log ) {
			return null;
		}

		// Parse the JSON details field.
		if ( ! empty( $log->detalles ) ) {
			$log->detalles_parsed = json_decode( $log->detalles, true );
		} else {
			$log->detalles_parsed = array();
		}

		// Enrich with user display name.
		if ( ! empty( $log->usuario ) ) {
			$user = get_userdata( absint( $log->usuario ) );
			$log->usuario_nombre = $user ? $user->display_name : __( 'Usuario eliminado', 'lostrego-reservas' );
		} else {
			$log->usuario_nombre = __( 'Sistema', 'lostrego-reservas' );
		}

		// Enrich with event title.
		if ( ! empty( $log->evento ) ) {
			$evento_title = get_the_title( absint( $log->evento ) );
			$log->evento_titulo = $evento_title ? $evento_title : __( 'Evento eliminado', 'lostrego-reservas' );
		} else {
			$log->evento_titulo = __( 'N/A', 'lostrego-reservas' );
		}

		return $log;
	}

	/**
	 * Get total count of log entries matching the given filters.
	 *
	 * Used for pagination calculations and display.
	 *
	 * @since 1.0.0
	 *
	 * @param array $filters Optional. Associative array of filter criteria.
	 *                       Same keys as get_logs().
	 * @return int Total number of matching log entries.
	 */
	public function get_total_count( $filters = array() ) {
		global $wpdb;

		$log_filters  = new Lostrego_Log_Filters();
		$where_clause = $log_filters->build_where_clause( $filters );

		$query = "SELECT COUNT(*) FROM {$this->table_name}";
		if ( ! empty( $where_clause ) ) {
			$query .= " WHERE {$where_clause}";
		}

		return absint( $wpdb->get_var( $query ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Render the log viewer interface in the admin panel.
	 *
	 * Outputs the complete log viewer HTML including filter form,
	 * paginated log table, expandable detail rows, and export button.
	 * Checks user capabilities before rendering.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_viewer() {
		// Verify user has permission to view logs.
		if ( ! current_user_can( 'ver_logs' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para ver los logs.', 'lostrego-reservas' ) );
		}

		// Get current filter values from request.
		$filters = array();
		if ( isset( $_GET['usuario'] ) ) {
			$filters['usuario'] = absint( $_GET['usuario'] );
		}
		if ( isset( $_GET['evento'] ) ) {
			$filters['evento'] = absint( $_GET['evento'] );
		}
		if ( isset( $_GET['tipo_accion'] ) && ! empty( $_GET['tipo_accion'] ) ) {
			$filters['tipo_accion'] = sanitize_text_field( wp_unslash( $_GET['tipo_accion'] ) );
		}
		if ( isset( $_GET['fecha_desde'] ) && ! empty( $_GET['fecha_desde'] ) ) {
			$filters['fecha_desde'] = sanitize_text_field( wp_unslash( $_GET['fecha_desde'] ) );
		}
		if ( isset( $_GET['fecha_hasta'] ) && ! empty( $_GET['fecha_hasta'] ) ) {
			$filters['fecha_hasta'] = sanitize_text_field( wp_unslash( $_GET['fecha_hasta'] ) );
		}
		if ( isset( $_GET['busqueda'] ) && ! empty( $_GET['busqueda'] ) ) {
			$filters['busqueda'] = sanitize_text_field( wp_unslash( $_GET['busqueda'] ) );
		}

		$page     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : $this->default_per_page;

		// Retrieve filtered and paginated logs.
		$result = $this->get_logs( $filters, $page, $per_page );

		// Get filter options for dropdowns.
		$log_filters    = new Lostrego_Log_Filters();
		$filter_options = $log_filters->get_filter_options();

		// Include the viewer partial template.
		include plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'admin/partials/logs-viewer.php';
	}
}
