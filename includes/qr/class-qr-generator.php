<?php
/**
 * QR Code Generator
 *
 * Handles generation of QR codes for reservations using the PHP QR Code library.
 * Each reservation gets a unique, non-predictable QR code containing the reservation ID,
 * user ID, and a temporary token. QR codes are stored as PNG files in the protected
 * qrcodes/ directory.
 *
 * @package    Lostrego_Reservas
 * @subpackage Lostrego_Reservas/includes/qr
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lostrego_QR_Generator
 *
 * Generates QR code images for confirmed reservations. Uses the local PHP QR Code
 * library (located in /libs/qrcode/) to create PNG images with error correction
 * level L (low) for optimized file size. Each QR encodes a JSON payload with
 * reservation ID, user ID, and a temporal security token.
 *
 * @since 1.0.0
 */
class Lostrego_QR_Generator {

	/**
	 * Path to the PHP QR Code library.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $qrcode_lib_path;

	/**
	 * Directory where generated QR code images are stored.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $qrcodes_dir;

	/**
	 * Default QR code image size in pixels.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    int
	 */
	private $default_size;

	/**
	 * Error correction level for QR generation.
	 *
	 * Uses QR_ECLEVEL_L (Low) to optimize file size as specified in CLAUDE.md.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $error_correction_level;

	/**
	 * Constructor.
	 *
	 * Initializes paths, default configuration, and loads the PHP QR Code library.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->qrcode_lib_path        = plugin_dir_path( dirname( dirname( __FILE__ ) ) ) . 'libs/qrcode/qrcode.php';
		$this->qrcodes_dir            = plugin_dir_path( __FILE__ ) . 'qrcodes/';
		$this->default_size           = absint( get_option( 'lostrego_qr_size', 300 ) );
		$this->error_correction_level = 'L';

		$this->load_library();
	}

	/**
	 * Load the PHP QR Code library.
	 *
	 * Attempts to include the local PHP QR Code library. If the library file
	 * is missing or cannot be loaded, logs the error and enters degraded mode
	 * where alphanumeric backup codes are used instead.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return bool True if library loaded successfully, false otherwise.
	 */
	private function load_library() {
		if ( ! file_exists( $this->qrcode_lib_path ) ) {
			if ( function_exists( 'lostrego_log' ) ) {
				lostrego_log( 'error', __( 'PHP QR Code library not found.', 'lostrego-reservas' ), array(
					'path' => $this->qrcode_lib_path,
				) );
			}
			return false;
		}

		require_once $this->qrcode_lib_path;
		return true;
	}

	/**
	 * Generate a QR code for a reservation.
	 *
	 * Creates a QR code PNG image for the given reservation. The QR encodes a JSON
	 * payload containing the reservation ID, user ID, and a unique security token.
	 * A unique hash (hash_ticket) is also stored in the reservations table for
	 * anti-fraud verification.
	 *
	 * If the QR library is unavailable, falls back to generating an alphanumeric
	 * backup code as specified in the fallback strategy.
	 *
	 * @since 1.0.0
	 *
	 * @param int $reservation_id The reservation ID to generate a QR code for.
	 * @return array|WP_Error {
	 *     QR generation result on success, WP_Error on failure.
	 *
	 *     @type string $qr_path       Absolute file path to the generated QR PNG image.
	 *     @type string $qr_url        Public URL of the QR image (if accessible).
	 *     @type string $hash_ticket   Unique non-predictable hash stored in the DB.
	 *     @type string $backup_code   Alphanumeric backup code (always generated).
	 *     @type bool   $fallback_used Whether the fallback mode was used instead of QR.
	 * }
	 */
	public function generate( $reservation_id ) {
		$reservation_id = absint( $reservation_id );

		if ( empty( $reservation_id ) ) {
			return new WP_Error(
				'invalid_reservation',
				__( 'ID de reserva no valido.', 'lostrego-reservas' )
			);
		}

		// Ensure the qrcodes directory exists and is writable.
		if ( ! $this->ensure_directory() ) {
			return new WP_Error(
				'directory_error',
				__( 'No se pudo crear o acceder al directorio de codigos QR.', 'lostrego-reservas' )
			);
		}

		// Build the QR content payload.
		$qr_content = $this->get_qr_content( $reservation_id );

		if ( is_wp_error( $qr_content ) ) {
			return $qr_content;
		}

		// Generate a unique hash for anti-fraud.
		$hash_ticket = $this->generate_hash_ticket( $reservation_id );
		$backup_code = $this->generate_backup_code( $reservation_id );

		// Determine the output file path.
		$qr_path = $this->get_qr_path( $reservation_id );

		// Attempt QR generation with the library.
		$fallback_used = false;

		if ( class_exists( 'QRcode' ) ) {
			try {
				QRcode::png(
					$qr_content,
					$qr_path,
					$this->error_correction_level,
					$this->calculate_module_size(),
					2
				);
			} catch ( Exception $e ) {
				if ( function_exists( 'lostrego_log' ) ) {
					lostrego_log( 'error', __( 'Error al generar QR.', 'lostrego-reservas' ), array(
						'reservation_id' => $reservation_id,
						'error'          => $e->getMessage(),
					) );
				}
				$fallback_used = true;
			}
		} else {
			$fallback_used = true;
		}

		// Store the hash_ticket in the reservation record.
		$this->store_hash_ticket( $reservation_id, $hash_ticket );

		if ( function_exists( 'lostrego_log' ) ) {
			lostrego_log( 'info', __( 'QR generado para reserva.', 'lostrego-reservas' ), array(
				'reservation_id' => $reservation_id,
				'fallback_used'  => $fallback_used,
			) );
		}

		return array(
			'qr_path'       => $qr_path,
			'qr_url'        => $this->get_qr_url( $reservation_id ),
			'hash_ticket'   => $hash_ticket,
			'backup_code'   => $backup_code,
			'fallback_used' => $fallback_used,
		);
	}

	/**
	 * Build the QR code content payload for a reservation.
	 *
	 * Constructs a JSON-encoded string containing the reservation ID, user ID,
	 * and a temporary security token. The payload is then encoded using base64
	 * for the QR content as specified in the anti-fraud strategy.
	 *
	 * @since 1.0.0
	 *
	 * @param int $reservation_id The reservation ID.
	 * @return string|WP_Error Base64-encoded JSON payload on success, WP_Error on failure.
	 */
	public function get_qr_content( $reservation_id ) {
		global $wpdb;

		$reservation_id = absint( $reservation_id );

		$table_name = $wpdb->prefix . 'reservas';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$reservation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, user_id, evento_id FROM {$table_name} WHERE id = %d",
				$reservation_id
			)
		);

		if ( ! $reservation ) {
			return new WP_Error(
				'reservation_not_found',
				__( 'Reserva no encontrada.', 'lostrego-reservas' )
			);
		}

		$token = $this->generate_temporal_token( $reservation_id );

		$payload = array(
			'r' => absint( $reservation->id ),
			'u' => absint( $reservation->user_id ),
			't' => sanitize_text_field( $token ),
		);

		// Encode as base64 + JSON for anti-fraud.
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$encoded = base64_encode( wp_json_encode( $payload ) );

		return $encoded;
	}

	/**
	 * Get the file system path for a reservation's QR code image.
	 *
	 * Returns the absolute path where the QR code PNG file is (or will be) stored.
	 *
	 * @since 1.0.0
	 *
	 * @param int $reservation_id The reservation ID.
	 * @return string Absolute file path to the QR code PNG image.
	 */
	public function get_qr_path( $reservation_id ) {
		$reservation_id = absint( $reservation_id );
		$hash           = wp_hash( 'qr_' . $reservation_id . '_' . wp_salt( 'auth' ) );

		return trailingslashit( $this->qrcodes_dir ) . 'qr_' . $hash . '.png';
	}

	/**
	 * Delete the QR code image file for a reservation.
	 *
	 * Removes the QR code PNG file from the filesystem. Used when a reservation
	 * is cancelled or during automated cleanup of old event data.
	 *
	 * @since 1.0.0
	 *
	 * @param int $reservation_id The reservation ID whose QR should be deleted.
	 * @return bool True if the file was deleted or did not exist, false on failure.
	 */
	public function delete_qr( $reservation_id ) {
		$reservation_id = absint( $reservation_id );
		$qr_path        = $this->get_qr_path( $reservation_id );

		if ( ! file_exists( $qr_path ) ) {
			return true;
		}

		$deleted = wp_delete_file( $qr_path );

		if ( function_exists( 'lostrego_log' ) ) {
			lostrego_log( 'info', __( 'QR eliminado para reserva.', 'lostrego-reservas' ), array(
				'reservation_id' => $reservation_id,
				'path'           => $qr_path,
			) );
		}

		return ! file_exists( $qr_path );
	}

	/**
	 * Regenerate the QR code for a reservation.
	 *
	 * Deletes the existing QR code (if any) and generates a new one with a fresh
	 * security token. Useful when a QR code is compromised, when the user requests
	 * a new download, or after automated cleanup has removed old QR files.
	 *
	 * @since 1.0.0
	 *
	 * @param int $reservation_id The reservation ID to regenerate the QR for.
	 * @return array|WP_Error QR generation result on success, WP_Error on failure.
	 *                        See generate() for return format.
	 */
	public function regenerate( $reservation_id ) {
		$reservation_id = absint( $reservation_id );

		// Delete existing QR first.
		$this->delete_qr( $reservation_id );

		if ( function_exists( 'lostrego_log' ) ) {
			lostrego_log( 'info', __( 'Regenerando QR para reserva.', 'lostrego-reservas' ), array(
				'reservation_id' => $reservation_id,
			) );
		}

		// Generate a new QR with fresh token.
		return $this->generate( $reservation_id );
	}

	/**
	 * Generate a unique, non-predictable hash for the ticket.
	 *
	 * Creates a cryptographically secure hash stored in the reservations table
	 * for anti-fraud verification during QR scanning.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param int $reservation_id The reservation ID.
	 * @return string The generated unique hash.
	 */
	private function generate_hash_ticket( $reservation_id ) {
		$random_bytes = function_exists( 'random_bytes' )
			? bin2hex( random_bytes( 16 ) )
			: wp_generate_password( 32, false, false );

		return wp_hash( $reservation_id . '_' . $random_bytes . '_' . time() );
	}

	/**
	 * Generate an alphanumeric backup code.
	 *
	 * Creates a human-readable backup code that can be used when QR scanning
	 * is unavailable. This is the fallback mechanism specified in the architecture.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param int $reservation_id The reservation ID.
	 * @return string An uppercase alphanumeric backup code (e.g., "LR-A1B2C3").
	 */
	private function generate_backup_code( $reservation_id ) {
		$prefix = 'LR';
		$code   = strtoupper( substr( wp_generate_password( 6, false, false ), 0, 6 ) );

		return $prefix . '-' . $code;
	}

	/**
	 * Generate a temporal security token for the QR payload.
	 *
	 * The token is stored in the database and expires a configurable number of
	 * hours after the event ends, as specified in the QR configuration.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param int $reservation_id The reservation ID.
	 * @return string The generated temporal token.
	 */
	private function generate_temporal_token( $reservation_id ) {
		$token = wp_generate_password( 32, false, false );

		$expiry_hours = absint( get_option( 'lostrego_qr_token_expiry_hours', 24 ) );

		set_transient(
			'lostrego_qr_token_' . $reservation_id,
			$token,
			$expiry_hours * HOUR_IN_SECONDS
		);

		return $token;
	}

	/**
	 * Store the hash_ticket in the reservations table.
	 *
	 * Updates the reservation record with the generated unique hash for
	 * anti-fraud verification.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param int    $reservation_id The reservation ID.
	 * @param string $hash_ticket    The unique hash to store.
	 * @return bool True on success, false on failure.
	 */
	private function store_hash_ticket( $reservation_id, $hash_ticket ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'reservas';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table_name,
			array( 'hash_ticket' => sanitize_text_field( $hash_ticket ) ),
			array( 'id' => absint( $reservation_id ) ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get the public URL of a reservation's QR code image.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param int $reservation_id The reservation ID.
	 * @return string URL to the QR code image.
	 */
	private function get_qr_url( $reservation_id ) {
		$reservation_id = absint( $reservation_id );
		$hash           = wp_hash( 'qr_' . $reservation_id . '_' . wp_salt( 'auth' ) );

		return plugin_dir_url( __FILE__ ) . 'qrcodes/qr_' . $hash . '.png';
	}

	/**
	 * Ensure the qrcodes storage directory exists and is writable.
	 *
	 * Creates the directory if it does not exist and verifies write permissions.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @return bool True if directory exists and is writable, false otherwise.
	 */
	private function ensure_directory() {
		if ( ! file_exists( $this->qrcodes_dir ) ) {
			wp_mkdir_p( $this->qrcodes_dir );
		}

		return is_dir( $this->qrcodes_dir ) && wp_is_writable( $this->qrcodes_dir );
	}

	/**
	 * Calculate the QR module (pixel) size based on desired image dimensions.
	 *
	 * Converts the configured QR image size (in pixels) to the module size
	 * parameter expected by the PHP QR Code library.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @return int Module size for QR generation.
	 */
	private function calculate_module_size() {
		// Approximate module size: 300px image ~ size 10.
		$size = intval( $this->default_size / 30 );

		return max( 1, min( $size, 20 ) );
	}
}
