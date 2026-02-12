<?php
/**
 * User Profile class.
 *
 * Handles extended user profile data including family information.
 *
 * @package    Lostrego_Reservas
 * @subpackage Lostrego_Reservas/includes/users
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lostrego_User_Profile
 *
 * Manages extended user profiles with demographic data,
 * family information (for children's events), and RGPD-compliant
 * data export capabilities.
 *
 * @since 1.0.0
 */
class Lostrego_User_Profile {

	/**
	 * User meta prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $meta_prefix = 'lostrego_';

	/**
	 * Family info database table name (without WP prefix).
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $family_table = 'reservas_info_familiar';

	/**
	 * Children database table name (without WP prefix).
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $children_table = 'reservas_hijos';

	/**
	 * Profile fields and their types for structured access.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $profile_fields = array(
		'nombre'           => 'text',
		'apellidos'        => 'text',
		'fecha_nacimiento' => 'date',
		'codigo_postal'    => 'text',
		'genero'           => 'select',
		'telefono'         => 'text',
	);

	/**
	 * Initialize the user profile handler.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Constructor - initialize hooks if needed.
	}

	/**
	 * Get the complete profile data for a user.
	 *
	 * Retrieves all Lostrego-specific user meta fields and assembles
	 * them into a structured array. Includes derived fields such as
	 * age and city.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return array|WP_Error {
	 *     Structured profile data, or WP_Error if user not found.
	 *
	 *     @type int    $user_id         The user ID.
	 *     @type string $email           The user's email.
	 *     @type string $nombre          First name.
	 *     @type string $apellidos       Last name(s).
	 *     @type string $fecha_nacimiento Date of birth (YYYY-MM-DD).
	 *     @type string $codigo_postal   Postal code.
	 *     @type string $genero          Gender.
	 *     @type string $telefono        Phone number.
	 *     @type string $created_at      Account creation timestamp.
	 *     @type string $updated_at      Last update timestamp.
	 *     @type bool   $profile_complete Whether all required fields are filled.
	 * }
	 */
	public function get_profile( $user_id ) {

		$user_id = absint( $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'El usuario no existe.', 'lostrego-reservas' )
			);
		}

		$profile = array(
			'user_id' => $user_id,
			'email'   => $user->user_email,
		);

		// Retrieve all profile meta fields.
		foreach ( $this->profile_fields as $field => $type ) {
			$profile[ $field ] = get_user_meta( $user_id, $this->meta_prefix . $field, true );
		}

		// Timestamps.
		$profile['created_at'] = get_user_meta( $user_id, $this->meta_prefix . 'created_at', true );
		$profile['updated_at'] = get_user_meta( $user_id, $this->meta_prefix . 'updated_at', true );

		// Check profile completeness.
		$profile['profile_complete'] = $this->is_profile_complete( $profile );

		/**
		 * Filters the user profile data before returning.
		 *
		 * @since 1.0.0
		 *
		 * @param array $profile The assembled profile data.
		 * @param int   $user_id The user ID.
		 */
		return apply_filters( 'lostrego_user_profile', $profile, $user_id );
	}

	/**
	 * Update the user's profile data.
	 *
	 * Validates and sanitizes the provided data, then updates
	 * the corresponding user meta fields. Only fields present
	 * in $data are updated; missing fields are left unchanged.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int   $user_id The WordPress user ID.
	 * @param array $data    {
	 *     Profile fields to update. All fields are optional.
	 *
	 *     @type string $nombre           First name.
	 *     @type string $apellidos        Last name(s).
	 *     @type string $fecha_nacimiento Date of birth (YYYY-MM-DD).
	 *     @type string $codigo_postal    5-digit postal code.
	 *     @type string $genero           Gender identifier.
	 *     @type string $telefono         Phone number.
	 * }
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_profile( $user_id, $data ) {

		$user_id = absint( $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'El usuario no existe.', 'lostrego-reservas' )
			);
		}

		// Validate date of birth if provided.
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

		// Validate postal code if provided.
		if ( ! empty( $data['codigo_postal'] ) ) {
			if ( ! preg_match( '/^\d{5}$/', $data['codigo_postal'] ) ) {
				return new WP_Error(
					'invalid_postal_code',
					__( 'El codigo postal debe tener 5 digitos.', 'lostrego-reservas' )
				);
			}
		}

		// Validate gender if provided.
		$valid_genders = array( 'hombre', 'mujer', 'otro', 'prefiero_no_decir' );
		if ( ! empty( $data['genero'] ) && ! in_array( $data['genero'], $valid_genders, true ) ) {
			return new WP_Error(
				'invalid_gender',
				__( 'El valor de genero no es valido.', 'lostrego-reservas' )
			);
		}

		// Sanitize and update each provided field.
		foreach ( $this->profile_fields as $field => $type ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized_value = sanitize_text_field( $data[ $field ] );
				update_user_meta( $user_id, $this->meta_prefix . $field, $sanitized_value );
			}
		}

		// Update WP core name fields if provided.
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

		// Update timestamp.
		update_user_meta( $user_id, $this->meta_prefix . 'updated_at', current_time( 'mysql' ) );

		lostrego_log( 'info', __( 'Perfil de usuario actualizado', 'lostrego-reservas' ), array(
			'user_id' => $user_id,
			'fields'  => array_keys( $data ),
		) );

		/**
		 * Fires after a user profile is updated.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $user_id The user ID.
		 * @param array $data    The updated data fields.
		 */
		do_action( 'lostrego_profile_updated', $user_id, $data );

		return true;
	}

	/**
	 * Get family information for a user.
	 *
	 * Retrieves data from the wp_reservas_info_familiar table.
	 * This data is only populated when a user reserves a children's
	 * or mixed event.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return array|null {
	 *     Family information, or null if no family data exists.
	 *
	 *     @type int  $user_id           The user ID.
	 *     @type bool $tiene_hijos       Whether the user has children.
	 *     @type int  $numero_hijos_total Total number of children.
	 * }
	 */
	public function get_family_info( $user_id ) {

		$user_id = absint( $user_id );

		global $wpdb;

		$table_name = $wpdb->prefix . $this->family_table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$family_info = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_id = %d",
				$user_id
			),
			ARRAY_A
		);

		if ( null === $family_info ) {
			return null;
		}

		// Cast types for consistency.
		$family_info['user_id']           = absint( $family_info['user_id'] );
		$family_info['tiene_hijos']       = (bool) $family_info['tiene_hijos'];
		$family_info['numero_hijos_total'] = absint( $family_info['numero_hijos_total'] );

		/**
		 * Filters the family info data before returning.
		 *
		 * @since 1.0.0
		 *
		 * @param array $family_info The family information.
		 * @param int   $user_id     The user ID.
		 */
		return apply_filters( 'lostrego_family_info', $family_info, $user_id );
	}

	/**
	 * Update or create family information for a user.
	 *
	 * Inserts or updates a row in the wp_reservas_info_familiar table.
	 * Used when a user makes a reservation for a children's or mixed event.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int   $user_id The WordPress user ID.
	 * @param array $data    {
	 *     Family data to store.
	 *
	 *     @type bool $tiene_hijos       Whether the user has children.
	 *     @type int  $numero_hijos_total Total number of children.
	 * }
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update_family_info( $user_id, $data ) {

		$user_id = absint( $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'El usuario no existe.', 'lostrego-reservas' )
			);
		}

		// Validate and sanitize.
		$tiene_hijos       = isset( $data['tiene_hijos'] ) ? (bool) $data['tiene_hijos'] : false;
		$numero_hijos_total = isset( $data['numero_hijos_total'] ) ? absint( $data['numero_hijos_total'] ) : 0;

		// Consistency check: if no children, count must be 0.
		if ( ! $tiene_hijos ) {
			$numero_hijos_total = 0;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . $this->family_table;

		// Check if record already exists.
		$existing = $this->get_family_info( $user_id );

		if ( null !== $existing ) {
			// Update existing record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table_name,
				array(
					'tiene_hijos'       => $tiene_hijos ? 1 : 0,
					'numero_hijos_total' => $numero_hijos_total,
				),
				array( 'user_id' => $user_id ),
				array( '%d', '%d' ),
				array( '%d' )
			);
		} else {
			// Insert new record.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->insert(
				$table_name,
				array(
					'user_id'           => $user_id,
					'tiene_hijos'       => $tiene_hijos ? 1 : 0,
					'numero_hijos_total' => $numero_hijos_total,
				),
				array( '%d', '%d', '%d' )
			);
		}

		if ( false === $result ) {
			lostrego_log( 'error', __( 'Error al actualizar informacion familiar', 'lostrego-reservas' ), array(
				'user_id' => $user_id,
				'error'   => $wpdb->last_error,
			) );

			return new WP_Error(
				'db_error',
				__( 'Error al guardar la informacion familiar.', 'lostrego-reservas' )
			);
		}

		lostrego_log( 'info', __( 'Informacion familiar actualizada', 'lostrego-reservas' ), array(
			'user_id'      => $user_id,
			'tiene_hijos'  => $tiene_hijos,
			'numero_hijos' => $numero_hijos_total,
		) );

		/**
		 * Fires after family info is updated.
		 *
		 * @since 1.0.0
		 *
		 * @param int   $user_id The user ID.
		 * @param array $data    The family data that was saved.
		 */
		do_action( 'lostrego_family_info_updated', $user_id, $data );

		return true;
	}

	/**
	 * Export all user data in a structured format (RGPD compliance).
	 *
	 * Collects all data stored about a user across all Lostrego tables
	 * and meta fields, and returns it as a structured associative array.
	 * This is used for the "Export my data" feature in the user panel.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return array|WP_Error {
	 *     Complete user data export, or WP_Error if user not found.
	 *
	 *     @type array  $profile      Profile fields (name, email, etc.).
	 *     @type array  $family_info  Family information (if any).
	 *     @type array  $reservations All reservations made by the user.
	 *     @type string $exported_at  ISO 8601 timestamp of export.
	 * }
	 */
	public function export_user_data( $user_id ) {

		$user_id = absint( $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'El usuario no existe.', 'lostrego-reservas' )
			);
		}

		$export = array(
			'exported_at' => current_time( 'c' ),
			'profile'     => array(),
			'family_info' => null,
			'reservations' => array(),
		);

		// Profile data.
		$export['profile'] = $this->get_profile( $user_id );

		// Family info.
		$export['family_info'] = $this->get_family_info( $user_id );

		// Reservations.
		global $wpdb;

		$reservas_table  = $wpdb->prefix . 'reservas';
		$children_table  = $wpdb->prefix . $this->children_table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$reservations = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$reservas_table} WHERE user_id = %d ORDER BY id DESC",
				$user_id
			),
			ARRAY_A
		);

		if ( ! empty( $reservations ) ) {
			foreach ( $reservations as $index => $reservation ) {
				$reserva_id = absint( $reservation['id'] );

				// Get children data for each reservation.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$children = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT edad_hijo, genero_hijo, orden FROM {$children_table} WHERE reserva_id = %d ORDER BY orden ASC",
						$reserva_id
					),
					ARRAY_A
				);

				$reservations[ $index ]['children'] = $children;
			}

			$export['reservations'] = $reservations;
		}

		// Log the data export (RGPD audit).
		lostrego_log( 'info', __( 'Exportacion de datos de usuario (RGPD)', 'lostrego-reservas' ), array(
			'user_id'      => $user_id,
			'exported_at'  => $export['exported_at'],
			'reservations' => count( $export['reservations'] ),
		) );

		/**
		 * Filters the user data export before returning.
		 *
		 * Allows modules to add their own data to the export.
		 *
		 * @since 1.0.0
		 *
		 * @param array $export  The complete data export.
		 * @param int   $user_id The user ID.
		 */
		return apply_filters( 'lostrego_user_data_export', $export, $user_id );
	}

	/**
	 * Check if profile has all required fields filled.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param array $profile The profile data array.
	 * @return bool True if all required fields are present and non-empty.
	 */
	private function is_profile_complete( $profile ) {

		$required = array( 'nombre', 'apellidos', 'fecha_nacimiento', 'codigo_postal', 'genero' );

		foreach ( $required as $field ) {
			if ( empty( $profile[ $field ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get the children data associated with a specific reservation.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $reserva_id The reservation ID.
	 * @return array Array of children data (edad_hijo, genero_hijo, orden).
	 */
	public function get_children_for_reservation( $reserva_id ) {

		$reserva_id = absint( $reserva_id );

		global $wpdb;

		$table_name = $wpdb->prefix . $this->children_table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$children = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT edad_hijo, genero_hijo, orden FROM {$table_name} WHERE reserva_id = %d ORDER BY orden ASC",
				$reserva_id
			),
			ARRAY_A
		);

		return is_array( $children ) ? $children : array();
	}

	/**
	 * Get the human-readable gender label for display.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string $gender_key The gender key stored in the database.
	 * @return string Translated gender label.
	 */
	public function get_gender_label( $gender_key ) {

		$labels = array(
			'hombre'           => __( 'Hombre', 'lostrego-reservas' ),
			'mujer'            => __( 'Mujer', 'lostrego-reservas' ),
			'otro'             => __( 'Otro', 'lostrego-reservas' ),
			'prefiero_no_decir' => __( 'Prefiero no decir', 'lostrego-reservas' ),
		);

		return isset( $labels[ $gender_key ] ) ? $labels[ $gender_key ] : $gender_key;
	}
}
