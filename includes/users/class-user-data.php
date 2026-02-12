<?php
/**
 * User Data class.
 *
 * Handles demographic data calculations and derived fields.
 *
 * @package    Lostrego_Reservas
 * @subpackage Lostrego_Reservas/includes/users
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lostrego_User_Data
 *
 * Manages derived user data such as calculated age, age ranges,
 * city from postal code lookup, demographic aggregation,
 * and RGPD-compliant data anonymization.
 *
 * @since 1.0.0
 */
class Lostrego_User_Data {

	/**
	 * User meta prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $meta_prefix = 'lostrego_';

	/**
	 * Age range definitions.
	 *
	 * Used for demographic segmentation in statistics.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $age_ranges = array(
		'0-12'  => array( 'min' => 0, 'max' => 12 ),
		'13-17' => array( 'min' => 13, 'max' => 17 ),
		'18-25' => array( 'min' => 18, 'max' => 25 ),
		'26-40' => array( 'min' => 26, 'max' => 40 ),
		'41-60' => array( 'min' => 41, 'max' => 60 ),
		'60+'   => array( 'min' => 61, 'max' => 999 ),
	);

	/**
	 * Cache for postal code to city lookups.
	 *
	 * Prevents redundant lookups within the same request.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    array
	 */
	private $postal_code_cache = array();

	/**
	 * Initialize the user data handler.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Constructor - initialize hooks if needed.
	}

	/**
	 * Calculate the current age of a user based on their date of birth.
	 *
	 * Uses the fecha_nacimiento user meta field. The age is calculated
	 * relative to the current date in the WordPress timezone.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return int|WP_Error The age in years, or WP_Error if date of birth is missing/invalid.
	 */
	public function get_age( $user_id ) {

		$user_id = absint( $user_id );

		$fecha_nacimiento = get_user_meta( $user_id, $this->meta_prefix . 'fecha_nacimiento', true );

		if ( empty( $fecha_nacimiento ) ) {
			return new WP_Error(
				'missing_dob',
				__( 'No se ha registrado la fecha de nacimiento del usuario.', 'lostrego-reservas' )
			);
		}

		$birth_date = DateTime::createFromFormat( 'Y-m-d', $fecha_nacimiento );

		if ( false === $birth_date ) {
			return new WP_Error(
				'invalid_dob',
				__( 'La fecha de nacimiento no tiene un formato valido.', 'lostrego-reservas' )
			);
		}

		// Use WordPress timezone for consistent date handling.
		$timezone_string = wp_timezone_string();
		try {
			$timezone = new DateTimeZone( $timezone_string );
		} catch ( \Exception $e ) {
			$timezone = new DateTimeZone( 'UTC' );
		}

		$now = new DateTime( 'now', $timezone );

		$age = $now->diff( $birth_date )->y;

		/**
		 * Filters the calculated user age.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $age              The calculated age in years.
		 * @param int    $user_id          The user ID.
		 * @param string $fecha_nacimiento The date of birth string.
		 */
		return apply_filters( 'lostrego_user_age', $age, $user_id, $fecha_nacimiento );
	}

	/**
	 * Get the age range bracket for a user.
	 *
	 * Determines which predefined age range the user falls into:
	 * 0-12, 13-17, 18-25, 26-40, 41-60, 60+
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return string|WP_Error The age range label (e.g., '18-25'), or WP_Error on failure.
	 */
	public function get_age_range( $user_id ) {

		$age = $this->get_age( $user_id );

		if ( is_wp_error( $age ) ) {
			return $age;
		}

		foreach ( $this->age_ranges as $label => $range ) {
			if ( $age >= $range['min'] && $age <= $range['max'] ) {
				return $label;
			}
		}

		// Fallback — should not happen with the 60+ range covering up to 999.
		return '60+';
	}

	/**
	 * Get the city name from a Spanish postal code.
	 *
	 * Derives the province/city from the first two digits of the
	 * 5-digit Spanish postal code. Returns the province name as
	 * the city identifier.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string $postal_code A 5-digit Spanish postal code.
	 * @return string|WP_Error The province/city name, or WP_Error if the postal code is invalid.
	 */
	public function get_city_from_postal_code( $postal_code ) {

		$postal_code = sanitize_text_field( $postal_code );

		if ( ! preg_match( '/^\d{5}$/', $postal_code ) ) {
			return new WP_Error(
				'invalid_postal_code',
				__( 'El codigo postal debe tener 5 digitos.', 'lostrego-reservas' )
			);
		}

		// Check in-memory cache first.
		if ( isset( $this->postal_code_cache[ $postal_code ] ) ) {
			return $this->postal_code_cache[ $postal_code ];
		}

		// Check WordPress transient cache.
		$cache_key = 'lostrego_cp_' . $postal_code;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			$this->postal_code_cache[ $postal_code ] = $cached;
			return $cached;
		}

		// Spanish province codes (first 2 digits of postal code).
		$provinces = $this->get_spanish_provinces();

		$province_code = substr( $postal_code, 0, 2 );

		if ( ! isset( $provinces[ $province_code ] ) ) {
			return new WP_Error(
				'unknown_postal_code',
				sprintf(
					/* translators: %s: postal code */
					__( 'No se pudo determinar la ciudad para el codigo postal %s.', 'lostrego-reservas' ),
					$postal_code
				)
			);
		}

		$city = $provinces[ $province_code ];

		// Cache the result.
		$this->postal_code_cache[ $postal_code ] = $city;
		set_transient( $cache_key, $city, DAY_IN_SECONDS );

		return $city;
	}

	/**
	 * Get all demographic data for a user in a structured format.
	 *
	 * Assembles basic profile fields along with derived fields
	 * (age, age range, city) into a single array. Useful for
	 * statistics and reporting.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return array|WP_Error {
	 *     Structured demographic data, or WP_Error if user not found.
	 *
	 *     @type int    $user_id       The user ID.
	 *     @type string $genero        Gender identifier.
	 *     @type string $codigo_postal Postal code.
	 *     @type string $ciudad        City/province (derived from postal code).
	 *     @type int    $edad          Current age in years (derived from DOB).
	 *     @type string $rango_edad    Age range bracket (derived from age).
	 *     @type string $fecha_nacimiento Date of birth.
	 * }
	 */
	public function get_demographic_data( $user_id ) {

		$user_id = absint( $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'El usuario no existe.', 'lostrego-reservas' )
			);
		}

		$genero           = get_user_meta( $user_id, $this->meta_prefix . 'genero', true );
		$codigo_postal    = get_user_meta( $user_id, $this->meta_prefix . 'codigo_postal', true );
		$fecha_nacimiento = get_user_meta( $user_id, $this->meta_prefix . 'fecha_nacimiento', true );

		// Calculate derived fields.
		$edad       = $this->get_age( $user_id );
		$rango_edad = $this->get_age_range( $user_id );
		$ciudad     = ! empty( $codigo_postal ) ? $this->get_city_from_postal_code( $codigo_postal ) : '';

		// Handle WP_Error returns gracefully — use empty values.
		if ( is_wp_error( $edad ) ) {
			$edad = null;
		}
		if ( is_wp_error( $rango_edad ) ) {
			$rango_edad = '';
		}
		if ( is_wp_error( $ciudad ) ) {
			$ciudad = '';
		}

		$demographic = array(
			'user_id'          => $user_id,
			'genero'           => $genero,
			'codigo_postal'    => $codigo_postal,
			'ciudad'           => $ciudad,
			'edad'             => $edad,
			'rango_edad'       => $rango_edad,
			'fecha_nacimiento' => $fecha_nacimiento,
		);

		/**
		 * Filters the demographic data before returning.
		 *
		 * @since 1.0.0
		 *
		 * @param array $demographic The assembled demographic data.
		 * @param int   $user_id     The user ID.
		 */
		return apply_filters( 'lostrego_demographic_data', $demographic, $user_id );
	}

	/**
	 * Anonymize a user's personal data (RGPD compliance).
	 *
	 * Replaces all personally identifiable information with
	 * anonymized placeholders while preserving the user record
	 * for statistical integrity. The anonymized user can no longer
	 * be identified, but their reservation data remains for aggregate stats.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The WordPress user ID to anonymize.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function anonymize_user( $user_id ) {

		$user_id = absint( $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'El usuario no existe.', 'lostrego-reservas' )
			);
		}

		// Log before anonymization (audit trail).
		lostrego_log( 'info', __( 'Anonimizacion de usuario iniciada (RGPD)', 'lostrego-reservas' ), array(
			'user_id' => $user_id,
			'email'   => $user->user_email,
		) );

		// Generate an anonymous identifier.
		$anon_id = 'anon_' . wp_hash( $user_id . $user->user_email . time() );

		// Anonymize WordPress core user fields.
		wp_update_user( array(
			'ID'           => $user_id,
			'user_email'   => $anon_id . '@anonimizado.local',
			'user_login'   => $anon_id,
			'display_name' => __( 'Usuario anonimizado', 'lostrego-reservas' ),
			'first_name'   => '',
			'last_name'    => '',
		) );

		// Anonymize Lostrego-specific meta fields.
		$fields_to_anonymize = array(
			'nombre'           => '',
			'apellidos'        => '',
			'fecha_nacimiento' => '',
			'telefono'         => '',
		);

		foreach ( $fields_to_anonymize as $field => $anon_value ) {
			update_user_meta( $user_id, $this->meta_prefix . $field, $anon_value );
		}

		// Keep postal code prefix for geographic stats but anonymize full code.
		$codigo_postal = get_user_meta( $user_id, $this->meta_prefix . 'codigo_postal', true );
		if ( ! empty( $codigo_postal ) && strlen( $codigo_postal ) >= 2 ) {
			// Keep only the province code (first 2 digits) + '000'.
			$anonymized_postal = substr( $codigo_postal, 0, 2 ) . '000';
			update_user_meta( $user_id, $this->meta_prefix . 'codigo_postal', $anonymized_postal );
		}

		// Keep gender for stats (not PII).

		// Delete authentication credentials.
		$auth_meta_keys = array(
			'access_code',
			'access_code_expiry',
			'magic_link_token',
			'magic_link_expiry',
			'failed_code_attempts',
			'code_lockout_until',
		);

		foreach ( $auth_meta_keys as $key ) {
			delete_user_meta( $user_id, $this->meta_prefix . $key );
		}

		// Mark user as anonymized.
		update_user_meta( $user_id, $this->meta_prefix . 'anonymized', true );
		update_user_meta( $user_id, $this->meta_prefix . 'anonymized_at', current_time( 'mysql' ) );

		// Anonymize reservation records — remove emergency contacts.
		global $wpdb;

		$reservas_table = $wpdb->prefix . 'reservas';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$reservas_table,
			array(
				'contacto_emergencia_1_nombre'   => '',
				'contacto_emergencia_1_relacion' => '',
				'contacto_emergencia_1_telefono' => '',
				'contacto_emergencia_2_nombre'   => '',
				'contacto_emergencia_2_relacion' => '',
				'contacto_emergencia_2_telefono' => '',
				'alergias_condiciones'           => '',
			),
			array( 'user_id' => $user_id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		lostrego_log( 'info', __( 'Usuario anonimizado correctamente', 'lostrego-reservas' ), array(
			'user_id' => $user_id,
			'anon_id' => $anon_id,
		) );

		/**
		 * Fires after a user has been anonymized.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $user_id The user ID that was anonymized.
		 * @param string $anon_id The anonymous identifier assigned.
		 */
		do_action( 'lostrego_user_anonymized', $user_id, $anon_id );

		return true;
	}

	/**
	 * Calculate age from a date of birth string.
	 *
	 * Static utility method that can be used without a user context.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string $date_of_birth Date of birth in YYYY-MM-DD format.
	 * @return int|WP_Error The age in years, or WP_Error if the date is invalid.
	 */
	public function calculate_age_from_date( $date_of_birth ) {

		$birth_date = DateTime::createFromFormat( 'Y-m-d', $date_of_birth );

		if ( false === $birth_date ) {
			return new WP_Error(
				'invalid_date',
				__( 'La fecha no tiene un formato valido (YYYY-MM-DD).', 'lostrego-reservas' )
			);
		}

		$timezone_string = wp_timezone_string();
		try {
			$timezone = new DateTimeZone( $timezone_string );
		} catch ( \Exception $e ) {
			$timezone = new DateTimeZone( 'UTC' );
		}

		$now = new DateTime( 'now', $timezone );

		return $now->diff( $birth_date )->y;
	}

	/**
	 * Get the age range label for a given age.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $age The age in years.
	 * @return string The age range label (e.g., '18-25').
	 */
	public function get_age_range_from_age( $age ) {

		$age = absint( $age );

		foreach ( $this->age_ranges as $label => $range ) {
			if ( $age >= $range['min'] && $age <= $range['max'] ) {
				return $label;
			}
		}

		return '60+';
	}

	/**
	 * Check if a user is of legal age (18+).
	 *
	 * Used for events with age restrictions.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return bool|WP_Error True if 18+, false if under 18, WP_Error if age cannot be determined.
	 */
	public function is_adult( $user_id ) {

		$age = $this->get_age( $user_id );

		if ( is_wp_error( $age ) ) {
			return $age;
		}

		return $age >= 18;
	}

	/**
	 * Check if a user's data has been anonymized.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return bool True if the user's data has been anonymized.
	 */
	public function is_anonymized( $user_id ) {

		$user_id = absint( $user_id );

		return (bool) get_user_meta( $user_id, $this->meta_prefix . 'anonymized', true );
	}

	/**
	 * Get the full list of Spanish provinces indexed by postal code prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @return array Associative array of province code => province name.
	 */
	private function get_spanish_provinces() {

		return array(
			'01' => 'Araba/Alava',
			'02' => 'Albacete',
			'03' => 'Alicante/Alacant',
			'04' => 'Almeria',
			'05' => 'Avila',
			'06' => 'Badajoz',
			'07' => 'Illes Balears',
			'08' => 'Barcelona',
			'09' => 'Burgos',
			'10' => 'Caceres',
			'11' => 'Cadiz',
			'12' => 'Castellon/Castello',
			'13' => 'Ciudad Real',
			'14' => 'Cordoba',
			'15' => 'A Coruna',
			'16' => 'Cuenca',
			'17' => 'Girona',
			'18' => 'Granada',
			'19' => 'Guadalajara',
			'20' => 'Gipuzkoa',
			'21' => 'Huelva',
			'22' => 'Huesca',
			'23' => 'Jaen',
			'24' => 'Leon',
			'25' => 'Lleida',
			'26' => 'La Rioja',
			'27' => 'Lugo',
			'28' => 'Madrid',
			'29' => 'Malaga',
			'30' => 'Murcia',
			'31' => 'Navarra',
			'32' => 'Ourense',
			'33' => 'Asturias',
			'34' => 'Palencia',
			'35' => 'Las Palmas',
			'36' => 'Pontevedra',
			'37' => 'Salamanca',
			'38' => 'Santa Cruz de Tenerife',
			'39' => 'Cantabria',
			'40' => 'Segovia',
			'41' => 'Sevilla',
			'42' => 'Soria',
			'43' => 'Tarragona',
			'44' => 'Teruel',
			'45' => 'Toledo',
			'46' => 'Valencia',
			'47' => 'Valladolid',
			'48' => 'Bizkaia',
			'49' => 'Zamora',
			'50' => 'Zaragoza',
			'51' => 'Ceuta',
			'52' => 'Melilla',
		);
	}

	/**
	 * Get all defined age ranges with translatable labels.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @return array Associative array of age range key => translated label.
	 */
	public function get_age_range_labels() {

		return array(
			'0-12'  => __( '0-12 anos', 'lostrego-reservas' ),
			'13-17' => __( '13-17 anos', 'lostrego-reservas' ),
			'18-25' => __( '18-25 anos', 'lostrego-reservas' ),
			'26-40' => __( '26-40 anos', 'lostrego-reservas' ),
			'41-60' => __( '41-60 anos', 'lostrego-reservas' ),
			'60+'   => __( '60+ anos', 'lostrego-reservas' ),
		);
	}
}
