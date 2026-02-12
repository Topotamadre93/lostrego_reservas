<?php
/**
 * Centralized logging system for Lostrego Reservas.
 *
 * Provides a unified interface for logging all plugin actions
 * to the wp_reservas_logs table. Every significant action
 * (reservations, cancellations, QR scans, etc.) is recorded.
 *
 * @package    Lostrego_Reservas
 * @subpackage Lostrego_Reservas/includes/logs
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lostrego_Logger
 *
 * Centralized logger that writes to the wp_reservas_logs table.
 * Supports four log levels: error, warning, info, debug.
 * Each log entry includes PHP version, WP version, and plugin version.
 *
 * @since 1.0.0
 */
class Lostrego_Logger {

	/**
	 * Log level constants.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const LEVEL_ERROR   = 'error';
	const LEVEL_WARNING = 'warning';
	const LEVEL_INFO    = 'info';
	const LEVEL_DEBUG   = 'debug';

	/**
	 * Allowed log levels.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private $allowed_levels = array( 'error', 'warning', 'info', 'debug' );

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var Lostrego_Logger|null
	 */
	private static $instance = null;

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
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Lostrego_Logger The singleton instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Log a message with the specified level.
	 *
	 * This is the main logging method. All other level-specific methods
	 * delegate to this one. The log entry is written to wp_reservas_logs
	 * with context data serialized as JSON, and system info appended
	 * (PHP version, WP version, plugin version).
	 *
	 * @since 1.0.0
	 *
	 * @param string $level   Log level: 'error', 'warning', 'info', or 'debug'.
	 * @param string $message Human-readable log message.
	 * @param array  $context Optional. Associative array of contextual data
	 *                        (e.g., reserva_id, usuario_id, evento_id).
	 * @return int|false The log entry ID on success, false on failure.
	 */
	public function log( $level, $message, $context = array() ) {
		global $wpdb;

		// Validate log level.
		if ( ! in_array( $level, $this->allowed_levels, true ) ) {
			$level = self::LEVEL_INFO;
		}

		// Sanitize message.
		$message = sanitize_text_field( $message );

		// Enrich context with system information.
		$context = $this->enrich_context( $context );

		// Extract user and event IDs from context if present.
		$usuario_id = isset( $context['usuario_id'] ) ? absint( $context['usuario_id'] ) : 0;
		$evento_id  = isset( $context['evento_id'] ) ? absint( $context['evento_id'] ) : 0;

		// Determine action type from context or default to level.
		$tipo_accion = isset( $context['tipo_accion'] ) ? sanitize_text_field( $context['tipo_accion'] ) : $level;

		// Insert log entry into database.
		$result = $wpdb->insert(
			$this->table_name,
			array(
				'tipo_accion' => $tipo_accion,
				'usuario'     => $usuario_id,
				'evento'      => $evento_id,
				'fecha'       => current_time( 'mysql' ),
				'detalles'    => wp_json_encode( array(
					'level'          => $level,
					'message'        => $message,
					'context'        => $context,
					'php_version'    => PHP_VERSION,
					'wp_version'     => get_bloginfo( 'version' ),
					'plugin_version' => defined( 'LOSTREGO_RESERVAS_VERSION' ) ? LOSTREGO_RESERVAS_VERSION : '1.0.0',
				) ),
			),
			array( '%s', '%d', '%d', '%s', '%s' )
		);

		if ( false === $result ) {
			// If database insert fails, fall back to error_log.
			error_log( sprintf(
				'[Lostrego Reservas][%s] %s | Context: %s',
				strtoupper( $level ),
				$message,
				wp_json_encode( $context )
			) );
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Log an error message.
	 *
	 * Shortcut for log('error', ...). Use for critical failures
	 * that prevent normal operation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Human-readable error message.
	 * @param array  $context Optional. Contextual data.
	 * @return int|false The log entry ID on success, false on failure.
	 */
	public function error( $message, $context = array() ) {
		return $this->log( self::LEVEL_ERROR, $message, $context );
	}

	/**
	 * Log a warning message.
	 *
	 * Shortcut for log('warning', ...). Use for non-critical issues
	 * that should be reviewed (e.g., deprecated usage, near-capacity events).
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Human-readable warning message.
	 * @param array  $context Optional. Contextual data.
	 * @return int|false The log entry ID on success, false on failure.
	 */
	public function warning( $message, $context = array() ) {
		return $this->log( self::LEVEL_WARNING, $message, $context );
	}

	/**
	 * Log an informational message.
	 *
	 * Shortcut for log('info', ...). Use for normal operations
	 * (e.g., reservation created, QR scanned, email sent).
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Human-readable info message.
	 * @param array  $context Optional. Contextual data.
	 * @return int|false The log entry ID on success, false on failure.
	 */
	public function info( $message, $context = array() ) {
		return $this->log( self::LEVEL_INFO, $message, $context );
	}

	/**
	 * Log a debug message.
	 *
	 * Shortcut for log('debug', ...). Use for detailed diagnostic
	 * information during development or troubleshooting.
	 * Only recorded if WP_DEBUG is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Human-readable debug message.
	 * @param array  $context Optional. Contextual data.
	 * @return int|false The log entry ID on success, false on failure.
	 */
	public function debug( $message, $context = array() ) {
		// Only log debug messages when WP_DEBUG is enabled.
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return false;
		}
		return $this->log( self::LEVEL_DEBUG, $message, $context );
	}

	/**
	 * Enrich context with system information.
	 *
	 * Appends PHP version, WordPress version, plugin version,
	 * current user ID, and timestamp to the context array.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param array $context Original context data.
	 * @return array Enriched context data.
	 */
	private function enrich_context( $context ) {
		if ( ! is_array( $context ) ) {
			$context = array();
		}

		$context['php_version']    = PHP_VERSION;
		$context['wp_version']     = get_bloginfo( 'version' );
		$context['plugin_version'] = defined( 'LOSTREGO_RESERVAS_VERSION' ) ? LOSTREGO_RESERVAS_VERSION : '1.0.0';
		$context['timestamp']      = current_time( 'mysql' );

		// Add current user if not already set.
		if ( ! isset( $context['usuario_id'] ) && function_exists( 'get_current_user_id' ) ) {
			$current_user_id = get_current_user_id();
			if ( $current_user_id > 0 ) {
				$context['usuario_id'] = $current_user_id;
			}
		}

		return $context;
	}

	/**
	 * Get the log table name.
	 *
	 * @since 1.0.0
	 *
	 * @return string The fully prefixed table name.
	 */
	public function get_table_name() {
		return $this->table_name;
	}
}

/**
 * Global logging function.
 *
 * Wrapper around Lostrego_Logger for convenient global access.
 * Usage:
 *   lostrego_log( 'info', 'Reserva creada', array( 'reserva_id' => $id ) );
 *
 * @since 1.0.0
 *
 * @param string $level   Log level: 'error', 'warning', 'info', or 'debug'.
 * @param string $message Human-readable log message.
 * @param array  $context Optional. Associative array of contextual data.
 * @return int|false The log entry ID on success, false on failure.
 */
function lostrego_log( $level, $message, $context = array() ) {
	$logger = Lostrego_Logger::get_instance();
	return $logger->log( $level, $message, $context );
}
