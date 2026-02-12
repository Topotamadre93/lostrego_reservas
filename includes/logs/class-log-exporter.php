<?php
/**
 * CSV export for log entries.
 *
 * Allows administrators to export filtered log entries
 * to CSV format for external analysis or archival.
 *
 * @package    Lostrego_Reservas
 * @subpackage Lostrego_Reservas/includes/logs
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lostrego_Log_Exporter
 *
 * Handles exporting log entries to CSV format.
 * Supports the same filter criteria as the log viewer
 * so administrators can export exactly what they see.
 *
 * @since 1.0.0
 */
class Lostrego_Log_Exporter {

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
	 * Export filtered logs as a CSV file download.
	 *
	 * Queries the database with the provided filters,
	 * generates CSV content, and sends it as a downloadable
	 * file with appropriate HTTP headers.
	 *
	 * Must be called before any output is sent to the browser.
	 *
	 * @since 1.0.0
	 *
	 * @param array $filters Optional. Associative array of filter criteria.
	 *                       Same keys as Lostrego_Log_Viewer::get_logs().
	 * @return void Sends CSV file and exits, or returns on failure.
	 */
	public function export_csv( $filters = array() ) {
		// Verify user has permission to export.
		if ( ! current_user_can( 'exportar_datos' ) && ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'No tienes permisos para exportar logs.', 'lostrego-reservas' ) );
		}

		// Verify nonce for security.
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'lostrego_export_logs' ) ) {
			wp_die( esc_html__( 'Error de seguridad. Por favor, recarga la pagina.', 'lostrego-reservas' ) );
		}

		global $wpdb;

		// Build WHERE clause using filters.
		$log_filters  = new Lostrego_Log_Filters();
		$where_clause = $log_filters->build_where_clause( $filters );

		// Query all matching logs (no pagination for export).
		$query = "SELECT * FROM {$this->table_name}";
		if ( ! empty( $where_clause ) ) {
			$query .= " WHERE {$where_clause}";
		}
		$query .= " ORDER BY fecha DESC";

		$logs = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $logs ) ) {
			wp_die( esc_html__( 'No hay logs que exportar con los filtros seleccionados.', 'lostrego-reservas' ) );
		}

		// Generate and output CSV.
		$csv_content = $this->generate_csv_content( $logs );

		$filename = sprintf(
			'lostrego-logs-%s.csv',
			wp_date( 'Y-m-d-His' )
		);

		// Send headers for CSV download.
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $csv_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Generate CSV content from an array of log entries.
	 *
	 * Converts log data into a properly formatted CSV string
	 * with headers, escaped values, and UTF-8 BOM for Excel compatibility.
	 *
	 * @since 1.0.0
	 *
	 * @param array $logs Array of log entries as associative arrays.
	 * @return string The formatted CSV content with BOM prefix.
	 */
	public function generate_csv_content( $logs ) {
		if ( empty( $logs ) ) {
			return '';
		}

		// Use output buffering with php://temp to build CSV.
		$output = fopen( 'php://temp', 'r+' );
		if ( false === $output ) {
			return '';
		}

		// UTF-8 BOM for Excel compatibility.
		fwrite( $output, "\xEF\xBB\xBF" );

		// Write header row.
		$headers = $this->get_csv_headers();
		fputcsv( $output, $headers, ';' );

		// Write data rows.
		foreach ( $logs as $log ) {
			$row = array(
				isset( $log['id'] ) ? $log['id'] : '',
				isset( $log['tipo_accion'] ) ? $log['tipo_accion'] : '',
				isset( $log['usuario'] ) ? $log['usuario'] : '',
				isset( $log['evento'] ) ? $log['evento'] : '',
				isset( $log['fecha'] ) ? $log['fecha'] : '',
				isset( $log['detalles'] ) ? $log['detalles'] : '',
			);
			fputcsv( $output, $row, ';' );
		}

		// Read the content back.
		rewind( $output );
		$csv_content = stream_get_contents( $output );
		fclose( $output );

		return $csv_content;
	}

	/**
	 * Get CSV column headers.
	 *
	 * Returns an array of translated column headers
	 * for the CSV export file.
	 *
	 * @since 1.0.0
	 *
	 * @return array Array of translated header strings.
	 */
	public function get_csv_headers() {
		return array(
			__( 'ID', 'lostrego-reservas' ),
			__( 'Tipo de accion', 'lostrego-reservas' ),
			__( 'Usuario', 'lostrego-reservas' ),
			__( 'Evento', 'lostrego-reservas' ),
			__( 'Fecha', 'lostrego-reservas' ),
			__( 'Detalles', 'lostrego-reservas' ),
		);
	}
}
