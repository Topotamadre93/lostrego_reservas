<?php
/**
 * User Manager class.
 *
 * Manages user creation and data without explicit registration.
 * Users are created automatically when they make their first reservation.
 *
 * @package    Lostrego_Reservas
 * @subpackage Lostrego_Reservas/includes/users
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lostrego_User_Manager
 *
 * Handles user lifecycle: creation, retrieval, update, and deletion.
 * Implements a hybrid system where users are created implicitly
 * during the reservation process (no explicit registration).
 *
 * @since 1.0.0
 */
class Lostrego_User_Manager {

	/**
	 * User meta prefix for all custom fields.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $meta_prefix = 'lostrego_';

	/**
	 * Required user fields for reservation.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $required_fields = array(
		'nombre',
		'apellidos',
		'email',
		'fecha_nacimiento',
		'codigo_postal',
		'genero',
	);

	/**
	 * Optional user fields.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $optional_fields = array(
		'telefono',
	);

	/**
	 * Valid gender options.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $valid_genders = array(
		'hombre',
		'mujer',
		'otro',
		'prefiero_no_decir',
	);

	/**
	 * Initialize the user manager.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Constructor - initialize hooks if needed.
	}

	/**
	 * Create a new WordPress user from reservation data.
	 *
	 * Creates a WP user account using the email as username.
	 * Stores additional profile data as user meta.
	 * Does NOT send the default WordPress new user notification.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string $email The user's email address (used as username).
	 * @param array  $data  {
	 *     User data fields.
	 *
	 *     @type string $nombre           Required. First name.
	 *     @type string $apellidos        Required. Last name(s).
	 *     @type string $fecha_nacimiento Required. Date of birth (YYYY-MM-DD).
	 *     @type string $codigo_postal    Required. 5-digit postal code.
	 *     @type string $genero           Required. One of: hombre, mujer, otro, prefiero_no_decir.
	 *     @type string $telefono         Optional. Phone number.
	 * }
	 * @return int|WP_Error The new user ID on success, WP_Error on failure.
	 */
	public function create_user( $email, $data ) {

		// Sanitize email.
		$email = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return new WP_Error(
				'invalid_email',
				__( 'La direccion de email no es valida.', 'lostrego-reservas' )
			);
		}

		// Check if user already exists.
		$existing_user = get_user_by( 'email', $email );
		if ( $existing_user ) {
			return new WP_Error(
				'user_exists',
				__( 'Ya existe un usuario con este email.', 'lostrego-reservas' )
			);
		}

		// Validate required fields.
		$validation = $this->validate_user_data( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Sanitize all data.
		$sanitized_data = $this->sanitize_user_data( $data );

		// Generate a random password (user will use code/magic link to access).
		$password = wp_generate_password( 24, true, true );

		// Create the WordPress user.
		$user_id = wp_insert_user( array(
			'user_login'   => $email,
			'user_email'   => $email,
			'user_pass'    => $password,
			'first_name'   => $sanitized_data['nombre'],
			'last_name'    => $sanitized_data['apellidos'],
			'display_name' => $sanitized_data['nombre'] . ' ' . $sanitized_data['apellidos'],
			'role'         => 'subscriber',
		) );

		if ( is_wp_error( $user_id ) ) {
			lostrego_log( 'error', __( 'Error al crear usuario', 'lostrego-reservas' ), array(
				'email' => $email,
				'error' => $user_id->get_error_message(),
			) );
			return $user_id;
		}

		// Store custom meta data.
		$this->update_user_meta_data( $user_id, $sanitized_data );

		// Store creation timestamp.
		update_user_meta( $user_id, $this->meta_prefix . 'created_at', current_time( 'mysql' ) );

		// Log the creation.
		lostrego_log( 'info', __( 'Usuario creado automaticamente', 'lostrego-reservas' ), array(
			'user_id' => $user_id,
			'email'   => $email,
		) );

		/**
		 * Fires after a new user is created via the reservation system.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $user_id The newly created user ID.
		 * @param string $email   The user's email address.
		 * @param array  $data    The sanitized user data.
		 */
		do_action( 'lostrego_user_created', $user_id, $email, $sanitized_data );

		return $user_id;
	}

	/**
	 * Get an existing user by email, or create a new one if not found.
	 *
	 * This is the primary entry point during the reservation flow.
	 * If the user exists, their meta data is updated with the new values.
	 * If not, a new user account is created.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string $email The user's email address.
	 * @param array  $data  User data fields. See create_user() for structure.
	 * @return int|WP_Error The user ID on success, WP_Error on failure.
	 */
	public function get_or_create_user( $email, $data ) {

		$email = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return new WP_Error(
				'invalid_email',
				__( 'La direccion de email no es valida.', 'lostrego-reservas' )
			);
		}

		$existing_user = $this->get_user_by_email( $email );

		if ( $existing_user ) {
			// Update meta data with new values.
			$sanitized_data = $this->sanitize_user_data( $data );
			$this->update_user_meta_data( $existing_user->ID, $sanitized_data );

			lostrego_log( 'info', __( 'Datos de usuario actualizados', 'lostrego-reservas' ), array(
				'user_id' => $existing_user->ID,
				'email'   => $email,
			) );

			/**
			 * Fires when a returning user's data is updated during reservation.
			 *
			 * @since 1.0.0
			 *
			 * @param int   $user_id The user ID.
			 * @param array $data    The updated user data.
			 */
			do_action( 'lostrego_user_data_updated', $existing_user->ID, $sanitized_data );

			return $existing_user->ID;
		}

		// User doesn't exist, create new.
		return $this->create_user( $email, $data );
	}

	/**
	 * Get a WordPress user object by email address.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string $email The email address to search for.
	 * @return WP_User|false The user object if found, false otherwise.
	 */
	public function get_user_by_email( $email ) {

		$email = sanitize_email( $email );

		if ( ! is_email( $email ) ) {
			return false;
		}

		$user = get_user_by( 'email', $email );

		return $user ? $user : false;
	}

	/**
	 * Update custom user meta data.
	 *
	 * Stores Lostrego-specific profile fields as user meta
	 * with the 'lostrego_' prefix.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int   $user_id The WordPress user ID.
	 * @param array $data    {
	 *     User data to store as meta.
	 *
	 *     @type string $nombre           First name.
	 *     @type string $apellidos        Last name(s).
	 *     @type string $fecha_nacimiento Date of birth (YYYY-MM-DD).
	 *     @type string $codigo_postal    5-digit postal code.
	 *     @type string $genero           Gender identifier.
	 *     @type string $telefono         Phone number.
	 * }
	 * @return bool True on success, false if user does not exist.
	 */
	public function update_user_meta_data( $user_id, $data ) {

		$user_id = absint( $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$allowed_meta_keys = array_merge( $this->required_fields, $this->optional_fields );

		foreach ( $data as $key => $value ) {
			// Skip email — it's stored in wp_users, not meta.
			if ( 'email' === $key ) {
				continue;
			}

			if ( in_array( $key, $allowed_meta_keys, true ) ) {
				update_user_meta( $user_id, $this->meta_prefix . $key, $value );
			}
		}

		// Also update WP core fields if provided.
		$update_args = array( 'ID' => $user_id );
		$needs_update = false;

		if ( ! empty( $data['nombre'] ) ) {
			$update_args['first_name'] = sanitize_text_field( $data['nombre'] );
			$needs_update = true;
		}

		if ( ! empty( $data['apellidos'] ) ) {
			$update_args['last_name'] = sanitize_text_field( $data['apellidos'] );
			$needs_update = true;
		}

		if ( ! empty( $data['nombre'] ) && ! empty( $data['apellidos'] ) ) {
			$update_args['display_name'] = sanitize_text_field( $data['nombre'] ) . ' ' . sanitize_text_field( $data['apellidos'] );
			$needs_update = true;
		}

		if ( $needs_update ) {
			wp_update_user( $update_args );
		}

		// Store last updated timestamp.
		update_user_meta( $user_id, $this->meta_prefix . 'updated_at', current_time( 'mysql' ) );

		return true;
	}

	/**
	 * Delete all Lostrego-related user data and optionally the WP account.
	 *
	 * Implements RGPD right to erasure. Removes all custom meta,
	 * family info, children records, reservations, and QR/PDF files.
	 * Optionally deletes the WordPress user account entirely.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int  $user_id        The WordPress user ID.
	 * @param bool $delete_account Optional. Whether to also delete the WP user account. Default true.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_user_data( $user_id ) {

		$user_id = absint( $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'El usuario no existe.', 'lostrego-reservas' )
			);
		}

		// Log before deletion (for audit trail).
		lostrego_log( 'info', __( 'Eliminacion de datos de usuario solicitada (RGPD)', 'lostrego-reservas' ), array(
			'user_id' => $user_id,
			'email'   => $user->user_email,
		) );

		// Delete all lostrego_ prefixed meta.
		$all_meta_keys = array_merge( $this->required_fields, $this->optional_fields );
		foreach ( $all_meta_keys as $key ) {
			delete_user_meta( $user_id, $this->meta_prefix . $key );
		}

		// Delete additional lostrego meta keys.
		$extra_meta_keys = array(
			'created_at',
			'updated_at',
			'access_code',
			'access_code_expiry',
			'magic_link_token',
			'magic_link_expiry',
		);

		foreach ( $extra_meta_keys as $key ) {
			delete_user_meta( $user_id, $this->meta_prefix . $key );
		}

		// Delete family info and children records (delegated to database layer).
		global $wpdb;

		$family_table = $wpdb->prefix . 'reservas_info_familiar';
		$hijos_table  = $wpdb->prefix . 'reservas_hijos';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $family_table, array( 'user_id' => $user_id ), array( '%d' ) );

		// Delete children records via reservations.
		$reservas_table = $wpdb->prefix . 'reservas';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$reserva_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$reservas_table} WHERE user_id = %d",
				$user_id
			)
		);

		if ( ! empty( $reserva_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $reserva_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$hijos_table} WHERE reserva_id IN ({$placeholders})",
					$reserva_ids
				)
			);
		}

		/**
		 * Fires after all user data has been deleted (RGPD).
		 *
		 * Allows other modules to clean up related data (QR files, PDFs, etc.).
		 *
		 * @since 1.0.0
		 *
		 * @param int   $user_id     The user ID being deleted.
		 * @param array $reserva_ids The reservation IDs that belonged to this user.
		 */
		do_action( 'lostrego_user_data_deleted', $user_id, $reserva_ids );

		// Delete the WordPress user account.
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $user_id );

		lostrego_log( 'info', __( 'Datos de usuario eliminados completamente', 'lostrego-reservas' ), array(
			'user_id' => $user_id,
		) );

		return true;
	}

	/**
	 * Validate user data before creation or update.
	 *
	 * Checks that all required fields are present and contain
	 * valid values (date format, postal code format, gender option, etc.).
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param array $data The user data to validate.
	 * @return true|WP_Error True if valid, WP_Error with details if not.
	 */
	private function validate_user_data( $data ) {

		// Check required fields presence.
		foreach ( $this->required_fields as $field ) {
			if ( 'email' === $field ) {
				continue; // Email is validated separately.
			}

			if ( empty( $data[ $field ] ) ) {
				return new WP_Error(
					'missing_field',
					sprintf(
						/* translators: %s: field name */
						__( 'El campo %s es obligatorio.', 'lostrego-reservas' ),
						$field
					)
				);
			}
		}

		// Validate date of birth format (YYYY-MM-DD).
		if ( ! empty( $data['fecha_nacimiento'] ) ) {
			$date_parts = explode( '-', $data['fecha_nacimiento'] );
			if (
				count( $date_parts ) !== 3
				|| ! checkdate( intval( $date_parts[1] ), intval( $date_parts[2] ), intval( $date_parts[0] ) )
			) {
				return new WP_Error(
					'invalid_date',
					__( 'La fecha de nacimiento no tiene un formato valido (YYYY-MM-DD).', 'lostrego-reservas' )
				);
			}
		}

		// Validate postal code (5 digits).
		if ( ! empty( $data['codigo_postal'] ) ) {
			if ( ! preg_match( '/^\d{5}$/', $data['codigo_postal'] ) ) {
				return new WP_Error(
					'invalid_postal_code',
					__( 'El codigo postal debe tener 5 digitos.', 'lostrego-reservas' )
				);
			}
		}

		// Validate gender.
		if ( ! empty( $data['genero'] ) ) {
			if ( ! in_array( $data['genero'], $this->valid_genders, true ) ) {
				return new WP_Error(
					'invalid_gender',
					__( 'El valor de genero no es valido.', 'lostrego-reservas' )
				);
			}
		}

		// Validate phone if provided.
		if ( ! empty( $data['telefono'] ) ) {
			$phone_clean = preg_replace( '/[\s\-\(\)\+]/', '', $data['telefono'] );
			if ( ! preg_match( '/^\d{9,15}$/', $phone_clean ) ) {
				return new WP_Error(
					'invalid_phone',
					__( 'El numero de telefono no es valido.', 'lostrego-reservas' )
				);
			}
		}

		return true;
	}

	/**
	 * Sanitize user data fields.
	 *
	 * Applies appropriate sanitization to each field based on its type.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param array $data Raw user data.
	 * @return array Sanitized user data.
	 */
	private function sanitize_user_data( $data ) {

		$sanitized = array();

		if ( isset( $data['nombre'] ) ) {
			$sanitized['nombre'] = sanitize_text_field( $data['nombre'] );
		}

		if ( isset( $data['apellidos'] ) ) {
			$sanitized['apellidos'] = sanitize_text_field( $data['apellidos'] );
		}

		if ( isset( $data['email'] ) ) {
			$sanitized['email'] = sanitize_email( $data['email'] );
		}

		if ( isset( $data['fecha_nacimiento'] ) ) {
			$sanitized['fecha_nacimiento'] = sanitize_text_field( $data['fecha_nacimiento'] );
		}

		if ( isset( $data['codigo_postal'] ) ) {
			$sanitized['codigo_postal'] = sanitize_text_field( $data['codigo_postal'] );
		}

		if ( isset( $data['genero'] ) ) {
			$sanitized['genero'] = sanitize_text_field( $data['genero'] );
		}

		if ( isset( $data['telefono'] ) ) {
			$sanitized['telefono'] = sanitize_text_field( $data['telefono'] );
		}

		return $sanitized;
	}

	/**
	 * Check if a user has completed all required profile fields.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return bool True if all required fields are populated.
	 */
	public function is_profile_complete( $user_id ) {

		$user_id = absint( $user_id );

		foreach ( $this->required_fields as $field ) {
			if ( 'email' === $field ) {
				continue;
			}

			$value = get_user_meta( $user_id, $this->meta_prefix . $field, true );
			if ( empty( $value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Detect potential duplicate accounts for the same person.
	 *
	 * Searches for users with the same name and date of birth
	 * but different email addresses. Returns matches for admin review.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The user ID to check against.
	 * @return array Array of potentially duplicate WP_User objects.
	 */
	public function detect_duplicate_accounts( $user_id ) {

		$user_id = absint( $user_id );

		$nombre    = get_user_meta( $user_id, $this->meta_prefix . 'nombre', true );
		$apellidos = get_user_meta( $user_id, $this->meta_prefix . 'apellidos', true );
		$fecha_nac = get_user_meta( $user_id, $this->meta_prefix . 'fecha_nacimiento', true );

		if ( empty( $nombre ) || empty( $apellidos ) || empty( $fecha_nac ) ) {
			return array();
		}

		$args = array(
			'meta_query'  => array(
				'relation' => 'AND',
				array(
					'key'   => $this->meta_prefix . 'nombre',
					'value' => $nombre,
				),
				array(
					'key'   => $this->meta_prefix . 'apellidos',
					'value' => $apellidos,
				),
				array(
					'key'   => $this->meta_prefix . 'fecha_nacimiento',
					'value' => $fecha_nac,
				),
			),
			'exclude'     => array( $user_id ),
			'number'      => 10,
			'count_total' => false,
		);

		$user_query = new WP_User_Query( $args );

		return $user_query->get_results();
	}
}
