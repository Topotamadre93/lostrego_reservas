<?php
/**
 * User Authentication class.
 *
 * Handles authentication via 6-digit access codes and magic links.
 * No passwords are used — users receive access credentials by email.
 *
 * @package    Lostrego_Reservas
 * @subpackage Lostrego_Reservas/includes/users
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Lostrego_User_Auth
 *
 * Provides passwordless authentication for festival attendees.
 * Two methods are supported:
 * - 6-digit numeric code (easy to type on mobile).
 * - Magic link (one-click access from email).
 *
 * Both methods have configurable expiration times and are single-use.
 *
 * @since 1.0.0
 */
class Lostrego_User_Auth {

	/**
	 * User meta prefix.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $meta_prefix = 'lostrego_';

	/**
	 * Access code length (number of digits).
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    int
	 */
	private $code_length = 6;

	/**
	 * Default access code validity in seconds (24 hours).
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    int
	 */
	private $code_expiry_seconds = 86400;

	/**
	 * Default magic link validity in seconds (48 hours).
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    int
	 */
	private $magic_link_expiry_seconds = 172800;

	/**
	 * Maximum failed verification attempts before lockout.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    int
	 */
	private $max_failed_attempts = 5;

	/**
	 * Lockout duration in seconds after max failed attempts (15 minutes).
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    int
	 */
	private $lockout_duration = 900;

	/**
	 * Initialize the authentication handler.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Constructor - initialize hooks if needed.
	}

	/**
	 * Generate a unique 6-digit numeric access code for a user.
	 *
	 * Creates a cryptographically random 6-digit code, stores it
	 * as user meta along with an expiration timestamp. Any previously
	 * existing code is overwritten.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return string|WP_Error The generated 6-digit code, or WP_Error on failure.
	 */
	public function generate_access_code( $user_id ) {

		$user_id = absint( $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'El usuario no existe.', 'lostrego-reservas' )
			);
		}

		// Generate a cryptographically secure random 6-digit code.
		// Range: 100000 - 999999 to ensure exactly 6 digits.
		try {
			$code = str_pad(
				(string) random_int( 0, 999999 ),
				$this->code_length,
				'0',
				STR_PAD_LEFT
			);
		} catch ( \Exception $e ) {
			// Fallback for systems where random_int might fail.
			$code = str_pad(
				(string) wp_rand( 0, 999999 ),
				$this->code_length,
				'0',
				STR_PAD_LEFT
			);
		}

		// Hash the code before storing (security best practice).
		$hashed_code = wp_hash_password( $code );

		// Calculate expiry timestamp.
		$expiry = time() + $this->code_expiry_seconds;

		// Store the hashed code and expiry as user meta.
		update_user_meta( $user_id, $this->meta_prefix . 'access_code', $hashed_code );
		update_user_meta( $user_id, $this->meta_prefix . 'access_code_expiry', $expiry );

		// Reset failed attempts counter.
		delete_user_meta( $user_id, $this->meta_prefix . 'failed_code_attempts' );
		delete_user_meta( $user_id, $this->meta_prefix . 'code_lockout_until' );

		lostrego_log( 'info', __( 'Codigo de acceso generado', 'lostrego-reservas' ), array(
			'user_id' => $user_id,
			'expiry'  => date_i18n( 'Y-m-d H:i:s', $expiry ),
		) );

		/**
		 * Fires after an access code is generated for a user.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $user_id The user ID.
		 * @param string $code    The plain-text access code (before hashing).
		 * @param int    $expiry  The expiration timestamp.
		 */
		do_action( 'lostrego_access_code_generated', $user_id, $code, $expiry );

		// Return the plain-text code (to be sent via email).
		return $code;
	}

	/**
	 * Generate a unique magic link token for a user.
	 *
	 * Creates a cryptographically random URL-safe token, stores it
	 * as user meta with an expiration timestamp. The magic link URL
	 * is constructed using the site's base URL.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return string|WP_Error The full magic link URL, or WP_Error on failure.
	 */
	public function generate_magic_link( $user_id ) {

		$user_id = absint( $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'El usuario no existe.', 'lostrego-reservas' )
			);
		}

		// Generate a secure random token.
		$token = wp_generate_password( 64, false, false );

		// Hash the token before storing.
		$hashed_token = hash( 'sha256', $token );

		// Calculate expiry timestamp.
		$expiry = time() + $this->magic_link_expiry_seconds;

		// Store the hashed token and expiry.
		update_user_meta( $user_id, $this->meta_prefix . 'magic_link_token', $hashed_token );
		update_user_meta( $user_id, $this->meta_prefix . 'magic_link_expiry', $expiry );

		// Build the magic link URL.
		$magic_link = add_query_arg(
			array(
				'lostrego_auth'  => 'magic_link',
				'token'          => $token,
				'uid'            => $user_id,
			),
			home_url( '/' )
		);

		lostrego_log( 'info', __( 'Enlace magico generado', 'lostrego-reservas' ), array(
			'user_id' => $user_id,
			'expiry'  => date_i18n( 'Y-m-d H:i:s', $expiry ),
		) );

		/**
		 * Fires after a magic link is generated for a user.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $user_id    The user ID.
		 * @param string $magic_link The full magic link URL.
		 * @param int    $expiry     The expiration timestamp.
		 */
		do_action( 'lostrego_magic_link_generated', $user_id, $magic_link, $expiry );

		return $magic_link;
	}

	/**
	 * Verify a 6-digit access code for a given email.
	 *
	 * Checks the code against the stored hash, verifies it has not
	 * expired, and enforces rate limiting. On successful verification,
	 * the code is invalidated (single-use) and the user is logged in.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string $email The user's email address.
	 * @param string $code  The 6-digit access code to verify.
	 * @return int|WP_Error The user ID on success, WP_Error on failure.
	 */
	public function verify_access_code( $email, $code ) {

		$email = sanitize_email( $email );
		$code  = sanitize_text_field( $code );

		if ( ! is_email( $email ) ) {
			return new WP_Error(
				'invalid_email',
				__( 'La direccion de email no es valida.', 'lostrego-reservas' )
			);
		}

		// Validate code format.
		if ( ! preg_match( '/^\d{' . $this->code_length . '}$/', $code ) ) {
			return new WP_Error(
				'invalid_code_format',
				sprintf(
					/* translators: %d: number of digits */
					__( 'El codigo debe tener %d digitos.', 'lostrego-reservas' ),
					$this->code_length
				)
			);
		}

		// Find the user.
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'No se encontro un usuario con ese email.', 'lostrego-reservas' )
			);
		}

		$user_id = $user->ID;

		// Check for lockout.
		$lockout_until = get_user_meta( $user_id, $this->meta_prefix . 'code_lockout_until', true );
		if ( ! empty( $lockout_until ) && time() < intval( $lockout_until ) ) {
			lostrego_log( 'warning', __( 'Intento de acceso durante bloqueo', 'lostrego-reservas' ), array(
				'user_id'      => $user_id,
				'lockout_until' => date_i18n( 'Y-m-d H:i:s', intval( $lockout_until ) ),
			) );

			return new WP_Error(
				'account_locked',
				__( 'Demasiados intentos fallidos. Intentalo de nuevo mas tarde.', 'lostrego-reservas' )
			);
		}

		// Get stored code and expiry.
		$stored_hash = get_user_meta( $user_id, $this->meta_prefix . 'access_code', true );
		$expiry      = get_user_meta( $user_id, $this->meta_prefix . 'access_code_expiry', true );

		// Check if code exists.
		if ( empty( $stored_hash ) ) {
			return new WP_Error(
				'no_code',
				__( 'No hay un codigo de acceso activo. Solicita uno nuevo.', 'lostrego-reservas' )
			);
		}

		// Check expiry.
		if ( ! empty( $expiry ) && time() > intval( $expiry ) ) {
			// Clean up expired code.
			delete_user_meta( $user_id, $this->meta_prefix . 'access_code' );
			delete_user_meta( $user_id, $this->meta_prefix . 'access_code_expiry' );

			return new WP_Error(
				'code_expired',
				__( 'El codigo de acceso ha expirado. Solicita uno nuevo.', 'lostrego-reservas' )
			);
		}

		// Verify the code against the stored hash.
		if ( ! wp_check_password( $code, $stored_hash ) ) {
			// Increment failed attempts.
			$failed_attempts = absint( get_user_meta( $user_id, $this->meta_prefix . 'failed_code_attempts', true ) );
			$failed_attempts++;
			update_user_meta( $user_id, $this->meta_prefix . 'failed_code_attempts', $failed_attempts );

			// Lockout if too many failures.
			if ( $failed_attempts >= $this->max_failed_attempts ) {
				$lockout_until = time() + $this->lockout_duration;
				update_user_meta( $user_id, $this->meta_prefix . 'code_lockout_until', $lockout_until );

				lostrego_log( 'warning', __( 'Cuenta bloqueada por intentos fallidos', 'lostrego-reservas' ), array(
					'user_id'        => $user_id,
					'failed_attempts' => $failed_attempts,
					'lockout_until'  => date_i18n( 'Y-m-d H:i:s', $lockout_until ),
				) );
			}

			lostrego_log( 'warning', __( 'Codigo de acceso incorrecto', 'lostrego-reservas' ), array(
				'user_id'        => $user_id,
				'failed_attempts' => $failed_attempts,
			) );

			return new WP_Error(
				'invalid_code',
				__( 'El codigo de acceso no es correcto.', 'lostrego-reservas' )
			);
		}

		// Code is valid — invalidate it (single-use).
		delete_user_meta( $user_id, $this->meta_prefix . 'access_code' );
		delete_user_meta( $user_id, $this->meta_prefix . 'access_code_expiry' );
		delete_user_meta( $user_id, $this->meta_prefix . 'failed_code_attempts' );
		delete_user_meta( $user_id, $this->meta_prefix . 'code_lockout_until' );

		// Log the user in.
		$this->login_user( $user_id );

		lostrego_log( 'info', __( 'Acceso con codigo verificado correctamente', 'lostrego-reservas' ), array(
			'user_id' => $user_id,
		) );

		/**
		 * Fires after a successful access code verification and login.
		 *
		 * @since 1.0.0
		 *
		 * @param int $user_id The user ID that was authenticated.
		 */
		do_action( 'lostrego_access_code_verified', $user_id );

		return $user_id;
	}

	/**
	 * Verify a magic link token.
	 *
	 * Validates the token against the stored hash, checks expiration,
	 * and logs the user in on success. The token is invalidated after use.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param string $token   The magic link token from the URL.
	 * @param int    $user_id Optional. The user ID from the URL. Default 0.
	 * @return int|WP_Error The user ID on success, WP_Error on failure.
	 */
	public function verify_magic_link( $token, $user_id = 0 ) {

		$token   = sanitize_text_field( $token );
		$user_id = absint( $user_id );

		if ( empty( $token ) ) {
			return new WP_Error(
				'empty_token',
				__( 'El enlace de acceso no es valido.', 'lostrego-reservas' )
			);
		}

		if ( empty( $user_id ) ) {
			return new WP_Error(
				'missing_user_id',
				__( 'El enlace de acceso no es valido.', 'lostrego-reservas' )
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'El enlace de acceso no es valido.', 'lostrego-reservas' )
			);
		}

		// Get stored token hash and expiry.
		$stored_hash = get_user_meta( $user_id, $this->meta_prefix . 'magic_link_token', true );
		$expiry      = get_user_meta( $user_id, $this->meta_prefix . 'magic_link_expiry', true );

		// Check if token exists.
		if ( empty( $stored_hash ) ) {
			return new WP_Error(
				'no_token',
				__( 'El enlace de acceso ya no es valido. Solicita uno nuevo.', 'lostrego-reservas' )
			);
		}

		// Check expiry.
		if ( ! empty( $expiry ) && time() > intval( $expiry ) ) {
			// Clean up expired token.
			delete_user_meta( $user_id, $this->meta_prefix . 'magic_link_token' );
			delete_user_meta( $user_id, $this->meta_prefix . 'magic_link_expiry' );

			return new WP_Error(
				'token_expired',
				__( 'El enlace de acceso ha expirado. Solicita uno nuevo.', 'lostrego-reservas' )
			);
		}

		// Hash the provided token and compare.
		$token_hash = hash( 'sha256', $token );

		if ( ! hash_equals( $stored_hash, $token_hash ) ) {
			lostrego_log( 'warning', __( 'Enlace magico invalido', 'lostrego-reservas' ), array(
				'user_id' => $user_id,
			) );

			return new WP_Error(
				'invalid_token',
				__( 'El enlace de acceso no es valido.', 'lostrego-reservas' )
			);
		}

		// Token is valid — invalidate it (single-use).
		delete_user_meta( $user_id, $this->meta_prefix . 'magic_link_token' );
		delete_user_meta( $user_id, $this->meta_prefix . 'magic_link_expiry' );

		// Log the user in.
		$this->login_user( $user_id );

		lostrego_log( 'info', __( 'Acceso con enlace magico verificado correctamente', 'lostrego-reservas' ), array(
			'user_id' => $user_id,
		) );

		/**
		 * Fires after a successful magic link verification and login.
		 *
		 * @since 1.0.0
		 *
		 * @param int $user_id The user ID that was authenticated.
		 */
		do_action( 'lostrego_magic_link_verified', $user_id );

		return $user_id;
	}

	/**
	 * Send access credentials (code + magic link) to a user by email.
	 *
	 * Generates both a 6-digit code and a magic link, then sends
	 * them to the user's email address using wp_mail().
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return bool|WP_Error True if email was sent successfully, WP_Error on failure.
	 */
	public function send_access_credentials( $user_id ) {

		$user_id = absint( $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error(
				'user_not_found',
				__( 'El usuario no existe.', 'lostrego-reservas' )
			);
		}

		// Generate both access methods.
		$code = $this->generate_access_code( $user_id );
		if ( is_wp_error( $code ) ) {
			return $code;
		}

		$magic_link = $this->generate_magic_link( $user_id );
		if ( is_wp_error( $magic_link ) ) {
			return $magic_link;
		}

		// Get the user's name for personalization.
		$nombre = get_user_meta( $user_id, $this->meta_prefix . 'nombre', true );
		if ( empty( $nombre ) ) {
			$nombre = $user->display_name;
		}

		// Build the email.
		$festival_name = get_option( 'lostrego_festival_name', __( 'Lostrego Festival de Cine', 'lostrego-reservas' ) );

		$subject = sprintf(
			/* translators: %s: festival name */
			__( 'Tu acceso a %s', 'lostrego-reservas' ),
			$festival_name
		);

		/**
		 * Filters the access credentials email subject.
		 *
		 * @since 1.0.0
		 *
		 * @param string $subject The email subject.
		 * @param int    $user_id The user ID.
		 */
		$subject = apply_filters( 'lostrego_access_email_subject', $subject, $user_id );

		// Build HTML email body.
		$message = $this->build_access_email_body( $nombre, $code, $magic_link );

		/**
		 * Filters the access credentials email body.
		 *
		 * @since 1.0.0
		 *
		 * @param string $message    The email body.
		 * @param int    $user_id    The user ID.
		 * @param string $code       The access code.
		 * @param string $magic_link The magic link URL.
		 */
		$message = apply_filters( 'lostrego_access_email_body', $message, $user_id, $code, $magic_link );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		/**
		 * Filters the access credentials email headers.
		 *
		 * @since 1.0.0
		 *
		 * @param array $headers The email headers.
		 * @param int   $user_id The user ID.
		 */
		$headers = apply_filters( 'lostrego_access_email_headers', $headers, $user_id );

		$sent = wp_mail( $user->user_email, $subject, $message, $headers );

		if ( ! $sent ) {
			lostrego_log( 'error', __( 'Error al enviar credenciales de acceso', 'lostrego-reservas' ), array(
				'user_id' => $user_id,
				'email'   => $user->user_email,
			) );

			return new WP_Error(
				'email_failed',
				__( 'No se pudo enviar el email con las credenciales de acceso.', 'lostrego-reservas' )
			);
		}

		lostrego_log( 'info', __( 'Credenciales de acceso enviadas por email', 'lostrego-reservas' ), array(
			'user_id' => $user_id,
			'email'   => $user->user_email,
		) );

		/**
		 * Fires after access credentials are sent to the user.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $user_id    The user ID.
		 * @param string $code       The access code sent.
		 * @param string $magic_link The magic link sent.
		 */
		do_action( 'lostrego_access_credentials_sent', $user_id, $code, $magic_link );

		return true;
	}

	/**
	 * Build the HTML body for the access credentials email.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param string $nombre     The user's first name.
	 * @param string $code       The 6-digit access code.
	 * @param string $magic_link The magic link URL.
	 * @return string The HTML email body.
	 */
	private function build_access_email_body( $nombre, $code, $magic_link ) {

		$festival_name = get_option( 'lostrego_festival_name', __( 'Lostrego Festival de Cine', 'lostrego-reservas' ) );

		$html = '<div style="max-width: 600px; margin: 0 auto; font-family: Arial, sans-serif;">';

		$html .= '<h2>' . sprintf(
			/* translators: %s: user name */
			esc_html__( 'Hola, %s', 'lostrego-reservas' ),
			esc_html( $nombre )
		) . '</h2>';

		$html .= '<p>' . esc_html__( 'Puedes acceder a tus reservas de dos maneras:', 'lostrego-reservas' ) . '</p>';

		// Access code section.
		$html .= '<h3>' . esc_html__( 'Opcion 1: Codigo de acceso', 'lostrego-reservas' ) . '</h3>';
		$html .= '<p>' . esc_html__( 'Introduce este codigo en la pagina de acceso:', 'lostrego-reservas' ) . '</p>';
		$html .= '<p style="font-size: 32px; font-weight: bold; letter-spacing: 8px; text-align: center; padding: 20px; background: #f5f5f5; border-radius: 8px;">';
		$html .= esc_html( $code );
		$html .= '</p>';

		// Magic link section.
		$html .= '<h3>' . esc_html__( 'Opcion 2: Enlace directo', 'lostrego-reservas' ) . '</h3>';
		$html .= '<p>' . esc_html__( 'O simplemente haz clic en este enlace:', 'lostrego-reservas' ) . '</p>';
		$html .= '<p style="text-align: center;">';
		$html .= '<a href="' . esc_url( $magic_link ) . '" style="display: inline-block; padding: 12px 30px; background-color: #2271b1; color: #ffffff; text-decoration: none; border-radius: 4px; font-size: 16px;">';
		$html .= esc_html__( 'Acceder a mis reservas', 'lostrego-reservas' );
		$html .= '</a></p>';

		// Footer.
		$html .= '<hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">';
		$html .= '<p style="font-size: 12px; color: #666;">';
		$html .= esc_html__( 'Si no has solicitado este acceso, puedes ignorar este email.', 'lostrego-reservas' );
		$html .= '</p>';
		$html .= '<p style="font-size: 12px; color: #666;">' . esc_html( $festival_name ) . '</p>';

		$html .= '</div>';

		return $html;
	}

	/**
	 * Log a user into WordPress programmatically.
	 *
	 * Sets authentication cookies and fires the wp_login action.
	 * This is called after successful code or magic link verification.
	 *
	 * @since  1.0.0
	 * @access private
	 *
	 * @param int $user_id The WordPress user ID to log in.
	 * @return void
	 */
	private function login_user( $user_id ) {

		$user_id = absint( $user_id );

		// Clear any existing auth cookies.
		wp_clear_auth_cookie();

		// Set the auth cookie for the user.
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		$user = get_userdata( $user_id );

		/**
		 * Fires the standard WordPress login action.
		 *
		 * @param string  $user_login The user's login name.
		 * @param WP_User $user       The WP_User object.
		 */
		do_action( 'wp_login', $user->user_login, $user );
	}

	/**
	 * Check if a user is currently locked out from code verification.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return bool True if the user is locked out, false otherwise.
	 */
	public function is_locked_out( $user_id ) {

		$user_id = absint( $user_id );

		$lockout_until = get_user_meta( $user_id, $this->meta_prefix . 'code_lockout_until', true );

		if ( empty( $lockout_until ) ) {
			return false;
		}

		return time() < intval( $lockout_until );
	}

	/**
	 * Invalidate all active access credentials for a user.
	 *
	 * Removes both the access code and magic link token.
	 * Used during account deletion or manual revocation.
	 *
	 * @since  1.0.0
	 * @access public
	 *
	 * @param int $user_id The WordPress user ID.
	 * @return void
	 */
	public function invalidate_credentials( $user_id ) {

		$user_id = absint( $user_id );

		delete_user_meta( $user_id, $this->meta_prefix . 'access_code' );
		delete_user_meta( $user_id, $this->meta_prefix . 'access_code_expiry' );
		delete_user_meta( $user_id, $this->meta_prefix . 'magic_link_token' );
		delete_user_meta( $user_id, $this->meta_prefix . 'magic_link_expiry' );
		delete_user_meta( $user_id, $this->meta_prefix . 'failed_code_attempts' );
		delete_user_meta( $user_id, $this->meta_prefix . 'code_lockout_until' );

		lostrego_log( 'info', __( 'Credenciales de acceso invalidadas', 'lostrego-reservas' ), array(
			'user_id' => $user_id,
		) );
	}
}
