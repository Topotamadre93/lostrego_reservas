<?php
/**
 * Generador principal de PDFs.
 *
 * Gestiona la generacion, almacenamiento y eliminacion de PDFs de entradas
 * para las reservas del festival. Utiliza TCPDF como motor de renderizado
 * con fallback a ticket HTML si TCPDF no esta disponible o falla.
 *
 * @package    Lostrego_Reservas
 * @subpackage Lostrego_Reservas/includes/pdf
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase Lostrego_PDF_Generator
 *
 * Clase principal para la generacion de PDFs de entradas/tickets.
 * Implementa el patron de fallback: si TCPDF falla, genera un ticket HTML
 * descargable como alternativa.
 *
 * @since 1.0.0
 */
class Lostrego_PDF_Generator {

	/**
	 * Directorio donde se almacenan los PDFs generados.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $pdf_dir;

	/**
	 * URL base del directorio de PDFs.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $pdf_url;

	/**
	 * Indica si TCPDF esta disponible y funcional.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    bool
	 */
	private $tcpdf_available;

	/**
	 * Instancia de Lostrego_PDF_Ticket para la creacion de tickets.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    Lostrego_PDF_Ticket|null
	 */
	private $ticket_builder;

	/**
	 * Constructor.
	 *
	 * Inicializa los directorios de almacenamiento y verifica
	 * la disponibilidad de TCPDF.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$upload_dir          = wp_upload_dir();
		$this->pdf_dir       = trailingslashit( $upload_dir['basedir'] ) . 'lostrego-reservas/pdf/';
		$this->pdf_url       = trailingslashit( $upload_dir['baseurl'] ) . 'lostrego-reservas/pdf/';
		$this->tcpdf_available = $this->check_tcpdf_available();
		$this->ticket_builder  = null;
	}

	/**
	 * Genera el PDF de entrada para una reserva.
	 *
	 * Proceso principal de generacion:
	 * 1. Obtiene datos de la reserva, evento y usuario.
	 * 2. Determina la plantilla segun el tipo de evento.
	 * 3. Genera el PDF con TCPDF.
	 * 4. Si TCPDF falla, genera un ticket HTML como fallback.
	 * 5. Almacena el archivo y registra la accion en logs.
	 *
	 * @since  1.0.0
	 * @param  int $reservation_id ID de la reserva.
	 * @return string|false Ruta al archivo generado (PDF o HTML), o false si falla.
	 */
	public function generate( $reservation_id ) {
		$reservation_id = absint( $reservation_id );

		if ( 0 === $reservation_id ) {
			if ( function_exists( 'lostrego_log' ) ) {
				lostrego_log( 'error', __( 'ID de reserva no valido para generar PDF.', 'lostrego-reservas' ), array(
					'reserva_id' => $reservation_id,
				) );
			}
			return false;
		}

		// Asegurar que el directorio de almacenamiento existe.
		if ( ! $this->ensure_directory() ) {
			return false;
		}

		// Obtener datos de la reserva.
		$reservation_data = $this->get_reservation_data( $reservation_id );

		if ( empty( $reservation_data ) ) {
			if ( function_exists( 'lostrego_log' ) ) {
				lostrego_log( 'error', __( 'No se encontraron datos de reserva para generar PDF.', 'lostrego-reservas' ), array(
					'reserva_id' => $reservation_id,
				) );
			}
			return false;
		}

		// Intentar generar con TCPDF.
		if ( $this->tcpdf_available ) {
			$pdf_path = $this->generate_with_tcpdf( $reservation_data );

			if ( false !== $pdf_path ) {
				if ( function_exists( 'lostrego_log' ) ) {
					lostrego_log( 'info', __( 'PDF de entrada generado correctamente.', 'lostrego-reservas' ), array(
						'reserva_id' => $reservation_id,
						'archivo'    => basename( $pdf_path ),
					) );
				}
				return $pdf_path;
			}
		}

		// Fallback: generar ticket HTML.
		$html_path = $this->generate_html_fallback( $reservation_data );

		if ( false !== $html_path ) {
			if ( function_exists( 'lostrego_log' ) ) {
				lostrego_log( 'warning', __( 'PDF no disponible. Se genero ticket HTML como alternativa.', 'lostrego-reservas' ), array(
					'reserva_id' => $reservation_id,
					'archivo'    => basename( $html_path ),
				) );
			}
		}

		return $html_path;
	}

	/**
	 * Obtiene la ruta del archivo PDF (o HTML) de una reserva.
	 *
	 * Busca primero el PDF, y si no existe, busca el HTML de fallback.
	 *
	 * @since  1.0.0
	 * @param  int $reservation_id ID de la reserva.
	 * @return string|false Ruta al archivo si existe, o false.
	 */
	public function get_pdf_path( $reservation_id ) {
		$reservation_id = absint( $reservation_id );

		if ( 0 === $reservation_id ) {
			return false;
		}

		$pdf_file  = $this->pdf_dir . 'ticket-' . $reservation_id . '.pdf';
		$html_file = $this->pdf_dir . 'ticket-' . $reservation_id . '.html';

		if ( file_exists( $pdf_file ) ) {
			return $pdf_file;
		}

		if ( file_exists( $html_file ) ) {
			return $html_file;
		}

		return false;
	}

	/**
	 * Elimina el archivo PDF (y HTML de fallback) de una reserva.
	 *
	 * Se utiliza al cancelar una reserva o al regenerar el ticket.
	 * Registra la accion en logs.
	 *
	 * @since  1.0.0
	 * @param  int $reservation_id ID de la reserva.
	 * @return bool True si se elimino algun archivo, false si no existia.
	 */
	public function delete_pdf( $reservation_id ) {
		$reservation_id = absint( $reservation_id );

		if ( 0 === $reservation_id ) {
			return false;
		}

		$deleted   = false;
		$pdf_file  = $this->pdf_dir . 'ticket-' . $reservation_id . '.pdf';
		$html_file = $this->pdf_dir . 'ticket-' . $reservation_id . '.html';

		if ( file_exists( $pdf_file ) ) {
			wp_delete_file( $pdf_file );
			$deleted = true;
		}

		if ( file_exists( $html_file ) ) {
			wp_delete_file( $html_file );
			$deleted = true;
		}

		if ( $deleted && function_exists( 'lostrego_log' ) ) {
			lostrego_log( 'info', __( 'Archivos de ticket eliminados.', 'lostrego-reservas' ), array(
				'reserva_id' => $reservation_id,
			) );
		}

		return $deleted;
	}

	/**
	 * Regenera el PDF de una reserva existente.
	 *
	 * Elimina el archivo anterior (si existe) y genera uno nuevo.
	 * Util cuando se actualizan datos del evento o del usuario.
	 *
	 * @since  1.0.0
	 * @param  int $reservation_id ID de la reserva.
	 * @return string|false Ruta al nuevo archivo generado, o false si falla.
	 */
	public function regenerate( $reservation_id ) {
		$reservation_id = absint( $reservation_id );

		if ( 0 === $reservation_id ) {
			return false;
		}

		// Eliminar archivo anterior si existe.
		$this->delete_pdf( $reservation_id );

		// Generar nuevo ticket.
		$result = $this->generate( $reservation_id );

		if ( false !== $result && function_exists( 'lostrego_log' ) ) {
			lostrego_log( 'info', __( 'Ticket regenerado correctamente.', 'lostrego-reservas' ), array(
				'reserva_id' => $reservation_id,
				'archivo'    => basename( $result ),
			) );
		}

		return $result;
	}

	/**
	 * Determina la plantilla de ticket a usar segun el tipo de evento.
	 *
	 * Busca la plantilla en el tema activo del plugin primero; si no existe,
	 * usa la plantilla del directorio includes/pdf/templates/.
	 * Orden de busqueda:
	 *   1. Tema activo del plugin: themes/{tema}/templates/ticket-{tipo}.php
	 *   2. Plantilla por defecto: includes/pdf/templates/ticket-{tipo}.php
	 *   3. Fallback final: includes/pdf/templates/ticket-default.php
	 *
	 * @since  1.0.0
	 * @param  int $event_id ID del evento.
	 * @return string Ruta absoluta a la plantilla PHP.
	 */
	public function get_template_for_event( $event_id ) {
		$event_id = absint( $event_id );
		$type     = $this->get_event_type( $event_id );

		// Determinar nombre de plantilla segun tipo.
		$template_name = 'ticket-default.php';

		if ( ! empty( $type ) ) {
			$type_map = array(
				'infantil' => 'ticket-infantil.php',
				'adulto'   => 'ticket-adulto.php',
			);

			/**
			 * Filtra el mapa de tipos de evento a plantillas de ticket.
			 *
			 * @since 1.0.0
			 * @param array $type_map Mapa tipo => archivo plantilla.
			 * @param int   $event_id ID del evento.
			 */
			$type_map = apply_filters( 'lostrego_pdf_template_type_map', $type_map, $event_id );

			if ( isset( $type_map[ $type ] ) ) {
				$template_name = $type_map[ $type ];
			}
		}

		// Buscar en tema activo del plugin.
		$theme_template = $this->get_theme_template_path( $template_name );

		if ( ! empty( $theme_template ) && file_exists( $theme_template ) ) {
			return $theme_template;
		}

		// Buscar plantilla especifica del tipo en includes/pdf/templates/.
		$plugin_template = LOSTREGO_PLUGIN_DIR . 'includes/pdf/templates/' . $template_name;

		if ( file_exists( $plugin_template ) ) {
			return $plugin_template;
		}

		// Fallback final: plantilla por defecto.
		return LOSTREGO_PLUGIN_DIR . 'includes/pdf/templates/ticket-default.php';
	}

	/**
	 * Verifica si TCPDF esta disponible y funcional.
	 *
	 * Comprueba que la clase TCPDF existe (cargada via Composer autoload).
	 *
	 * @since  1.0.0
	 * @access private
	 * @return bool True si TCPDF esta disponible.
	 */
	private function check_tcpdf_available() {
		return class_exists( 'TCPDF' );
	}

	/**
	 * Asegura que el directorio de almacenamiento de PDFs existe.
	 *
	 * Crea el directorio si no existe e incluye un archivo .htaccess
	 * para proteger el acceso directo, y un index.php vacio.
	 *
	 * @since  1.0.0
	 * @access private
	 * @return bool True si el directorio existe o fue creado correctamente.
	 */
	private function ensure_directory() {
		if ( is_dir( $this->pdf_dir ) ) {
			return true;
		}

		$created = wp_mkdir_p( $this->pdf_dir );

		if ( ! $created ) {
			if ( function_exists( 'lostrego_log' ) ) {
				lostrego_log( 'error', __( 'No se pudo crear el directorio de PDFs.', 'lostrego-reservas' ), array(
					'directorio' => $this->pdf_dir,
				) );
			}
			return false;
		}

		// Crear .htaccess de proteccion.
		$htaccess_content = "Order deny,allow\nDeny from all\n";
		$htaccess_path    = $this->pdf_dir . '.htaccess';

		if ( ! file_exists( $htaccess_path ) ) {
			file_put_contents( $htaccess_path, $htaccess_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		}

		// Crear index.php vacio.
		$index_path = $this->pdf_dir . 'index.php';

		if ( ! file_exists( $index_path ) ) {
			file_put_contents( $index_path, '<?php // Silence is golden.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		}

		return true;
	}

	/**
	 * Obtiene los datos completos de una reserva para la generacion del ticket.
	 *
	 * Recupera datos de la reserva, del evento asociado y del usuario.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int $reservation_id ID de la reserva.
	 * @return array|false Array con datos de la reserva, o false si no se encuentra.
	 */
	private function get_reservation_data( $reservation_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . LOSTREGO_TABLE_PREFIX;

		$reservation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				$reservation_id
			),
			ARRAY_A
		);

		if ( empty( $reservation ) ) {
			return false;
		}

		return $reservation;
	}

	/**
	 * Genera el PDF usando TCPDF.
	 *
	 * Crea una instancia de Lostrego_PDF_Ticket y delega la construccion
	 * del contenido del ticket.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $reservation_data Datos de la reserva.
	 * @return string|false Ruta al PDF generado, o false si falla.
	 */
	private function generate_with_tcpdf( $reservation_data ) {
		try {
			if ( null === $this->ticket_builder ) {
				$this->ticket_builder = new Lostrego_PDF_Ticket();
			}

			$reservation_id = isset( $reservation_data['id'] ) ? absint( $reservation_data['id'] ) : 0;
			$pdf_path       = $this->pdf_dir . 'ticket-' . $reservation_id . '.pdf';

			$result = $this->ticket_builder->create_ticket( $reservation_data );

			if ( false === $result ) {
				return false;
			}

			return $pdf_path;

		} catch ( \Exception $e ) {
			if ( function_exists( 'lostrego_log' ) ) {
				lostrego_log( 'error', __( 'Error al generar PDF con TCPDF.', 'lostrego-reservas' ), array(
					'reserva_id' => isset( $reservation_data['id'] ) ? $reservation_data['id'] : 0,
					'error'      => $e->getMessage(),
				) );
			}
			return false;
		}
	}

	/**
	 * Genera un ticket HTML como fallback cuando TCPDF no esta disponible.
	 *
	 * Crea un archivo HTML autocontenido con los datos de la entrada
	 * que el usuario puede imprimir desde el navegador.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  array $reservation_data Datos de la reserva.
	 * @return string|false Ruta al archivo HTML generado, o false si falla.
	 */
	private function generate_html_fallback( $reservation_data ) {
		$reservation_id = isset( $reservation_data['id'] ) ? absint( $reservation_data['id'] ) : 0;

		if ( 0 === $reservation_id ) {
			return false;
		}

		$html_path = $this->pdf_dir . 'ticket-' . $reservation_id . '.html';

		// Obtener datos del evento.
		$event_id = isset( $reservation_data['evento_id'] ) ? absint( $reservation_data['evento_id'] ) : 0;
		$event    = get_post( $event_id );

		// Obtener datos del usuario.
		$user_id = isset( $reservation_data['usuario_id'] ) ? absint( $reservation_data['usuario_id'] ) : 0;
		$user    = get_userdata( $user_id );

		// Codigo de backup.
		$backup_code = isset( $reservation_data['hash_ticket'] ) ? sanitize_text_field( $reservation_data['hash_ticket'] ) : '';

		ob_start();
		?>
		<!DOCTYPE html>
		<html lang="<?php echo esc_attr( get_locale() ); ?>">
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( sprintf(
				/* translators: %d: reservation ID */
				__( 'Entrada - Reserva #%d', 'lostrego-reservas' ),
				$reservation_id
			) ); ?></title>
			<style>
				body { font-family: Arial, Helvetica, sans-serif; max-width: 600px; margin: 20px auto; padding: 20px; }
				.ticket-header { text-align: center; border-bottom: 2px solid #333; padding-bottom: 15px; margin-bottom: 20px; }
				.ticket-header h1 { margin: 0; font-size: 24px; }
				.ticket-section { margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 5px; }
				.ticket-section h2 { margin: 0 0 10px 0; font-size: 16px; color: #333; }
				.ticket-section p { margin: 5px 0; font-size: 14px; }
				.ticket-code { text-align: center; font-size: 20px; font-weight: bold; letter-spacing: 3px; padding: 15px; background: #eee; border: 1px dashed #999; margin: 15px 0; }
				.ticket-footer { text-align: center; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 15px; margin-top: 20px; }
				@media print { body { margin: 0; } .no-print { display: none; } }
			</style>
		</head>
		<body>
			<div class="ticket-header">
				<h1><?php esc_html_e( 'Lostrego Festival de Cine', 'lostrego-reservas' ); ?></h1>
				<p><?php esc_html_e( 'Entrada de evento', 'lostrego-reservas' ); ?></p>
			</div>

			<div class="ticket-section">
				<h2><?php esc_html_e( 'Datos del evento', 'lostrego-reservas' ); ?></h2>
				<p><strong><?php esc_html_e( 'Evento:', 'lostrego-reservas' ); ?></strong>
					<?php echo $event ? esc_html( $event->post_title ) : esc_html__( 'No disponible', 'lostrego-reservas' ); ?>
				</p>
			</div>

			<div class="ticket-section">
				<h2><?php esc_html_e( 'Datos del asistente', 'lostrego-reservas' ); ?></h2>
				<p><strong><?php esc_html_e( 'Nombre:', 'lostrego-reservas' ); ?></strong>
					<?php echo $user ? esc_html( $user->display_name ) : esc_html__( 'No disponible', 'lostrego-reservas' ); ?>
				</p>
			</div>

			<?php if ( ! empty( $backup_code ) ) : ?>
			<div class="ticket-code">
				<?php echo esc_html( $backup_code ); ?>
			</div>
			<p style="text-align:center; font-size: 12px;">
				<?php esc_html_e( 'Codigo de verificacion. Presente este codigo si el QR no funciona.', 'lostrego-reservas' ); ?>
			</p>
			<?php endif; ?>

			<div class="ticket-footer">
				<p><?php esc_html_e( 'Este ticket es personal e intransferible.', 'lostrego-reservas' ); ?></p>
				<p><?php echo esc_html( sprintf(
					/* translators: %s: generation date */
					__( 'Generado el %s', 'lostrego-reservas' ),
					date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) )
				) ); ?></p>
			</div>

			<div class="no-print" style="text-align: center; margin-top: 20px;">
				<button onclick="window.print();" style="padding: 10px 30px; font-size: 16px; cursor: pointer;">
					<?php esc_html_e( 'Imprimir entrada', 'lostrego-reservas' ); ?>
				</button>
			</div>
		</body>
		</html>
		<?php
		$html_content = ob_get_clean();

		$written = file_put_contents( $html_path, $html_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents

		if ( false === $written ) {
			if ( function_exists( 'lostrego_log' ) ) {
				lostrego_log( 'error', __( 'No se pudo escribir el archivo HTML de ticket.', 'lostrego-reservas' ), array(
					'archivo' => $html_path,
				) );
			}
			return false;
		}

		return $html_path;
	}

	/**
	 * Obtiene el tipo de evento a partir de su ID.
	 *
	 * Consulta la taxonomia del evento para determinar su tipo
	 * (infantil, adulto, etc.).
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int $event_id ID del evento (post).
	 * @return string Tipo del evento (slug de la taxonomia), o cadena vacia.
	 */
	private function get_event_type( $event_id ) {
		$event_id = absint( $event_id );

		if ( 0 === $event_id ) {
			return '';
		}

		$terms = wp_get_post_terms( $event_id, 'tipo_evento', array( 'fields' => 'slugs' ) );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}

		return $terms[0];
	}

	/**
	 * Busca una plantilla de ticket en el tema activo del plugin.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $template_name Nombre del archivo de plantilla.
	 * @return string Ruta completa a la plantilla en el tema, o cadena vacia.
	 */
	private function get_theme_template_path( $template_name ) {
		$active_theme = get_option( 'lostrego_active_theme', 'default' );
		$theme_path   = LOSTREGO_PLUGIN_DIR . 'themes/' . sanitize_file_name( $active_theme ) . '/templates/' . $template_name;

		if ( file_exists( $theme_path ) ) {
			return $theme_path;
		}

		return '';
	}

	/**
	 * Obtiene la URL publica de descarga del ticket.
	 *
	 * La URL pasa por un endpoint de WordPress que verifica permisos
	 * antes de servir el archivo.
	 *
	 * @since  1.0.0
	 * @param  int $reservation_id ID de la reserva.
	 * @return string|false URL de descarga, o false si no existe el archivo.
	 */
	public function get_download_url( $reservation_id ) {
		$reservation_id = absint( $reservation_id );

		if ( 0 === $reservation_id ) {
			return false;
		}

		$path = $this->get_pdf_path( $reservation_id );

		if ( false === $path ) {
			return false;
		}

		return add_query_arg( array(
			'lostrego_action' => 'download_ticket',
			'reserva_id'      => $reservation_id,
			'nonce'           => wp_create_nonce( 'lostrego_download_ticket_' . $reservation_id ),
		), home_url( '/' ) );
	}

	/**
	 * Obtiene el directorio de almacenamiento de PDFs.
	 *
	 * @since  1.0.0
	 * @return string Ruta absoluta al directorio.
	 */
	public function get_pdf_dir() {
		return $this->pdf_dir;
	}

	/**
	 * Verifica si TCPDF esta disponible.
	 *
	 * @since  1.0.0
	 * @return bool True si TCPDF esta disponible.
	 */
	public function is_tcpdf_available() {
		return $this->tcpdf_available;
	}
}
