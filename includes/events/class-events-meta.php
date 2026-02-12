<?php
/**
 * Meta Boxes para el CPT de Eventos.
 *
 * Gestiona los meta boxes del backend para la edicion
 * de eventos: datos generales, aforo, configuracion, etc.
 *
 * @package    Lostrego_Reservas
 * @subpackage Lostrego_Reservas/includes/events
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase Lostrego_Events_Meta
 *
 * Registra y renderiza los meta boxes para la edicion
 * de eventos en el panel de administracion.
 *
 * @since 1.0.0
 */
class Lostrego_Events_Meta {

	/**
	 * Slug del Custom Post Type al que se asocian los meta boxes.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $post_type = 'lostrego_evento';

	/**
	 * Prefijo para los campos meta.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $meta_prefix = '_lostrego_';

	/**
	 * Constructor.
	 *
	 * Registra los hooks para meta boxes.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post_' . $this->post_type, array( $this, 'save_meta_boxes' ), 10, 1 );
	}

	/**
	 * Registra todos los meta boxes del evento.
	 *
	 * Agrega los meta boxes al CPT 'lostrego_evento':
	 * - Informacion general (fecha, ubicacion, precio)
	 * - Aforo y plazas
	 * - Configuracion de reservas (cancelaciones, lista espera)
	 * - Notas privadas para organizadores
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function add_meta_boxes() {
		add_meta_box(
			'lostrego_meta_general',
			__( 'Informacion general del evento', 'lostrego-reservas' ),
			array( $this, 'render_meta_box_general' ),
			$this->post_type,
			'normal',
			'high'
		);

		add_meta_box(
			'lostrego_meta_aforo',
			__( 'Aforo y plazas', 'lostrego-reservas' ),
			array( $this, 'render_meta_box_aforo' ),
			$this->post_type,
			'normal',
			'high'
		);

		add_meta_box(
			'lostrego_meta_reservas_config',
			__( 'Configuracion de reservas', 'lostrego-reservas' ),
			array( $this, 'render_meta_box_reservas_config' ),
			$this->post_type,
			'side',
			'default'
		);

		add_meta_box(
			'lostrego_meta_notas',
			__( 'Notas privadas', 'lostrego-reservas' ),
			array( $this, 'render_meta_box_notas' ),
			$this->post_type,
			'normal',
			'low'
		);
	}

	/**
	 * Guarda los datos de todos los meta boxes al guardar el evento.
	 *
	 * Verifica nonce, permisos y autosave antes de guardar.
	 * Sanitiza todos los valores antes de almacenarlos.
	 *
	 * @since  1.0.0
	 * @param  int $post_id ID del post que se esta guardando.
	 * @return void
	 */
	public function save_meta_boxes( $post_id ) {
		// Verificar que no es un autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Verificar nonce.
		if ( ! isset( $_POST['lostrego_meta_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lostrego_meta_nonce'] ) ), 'lostrego_save_meta' ) ) {
			return;
		}

		// Verificar permisos.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// TODO: Guardar campos generales.
		$this->save_general_fields( $post_id );

		// TODO: Guardar campos de aforo.
		$this->save_aforo_fields( $post_id );

		// TODO: Guardar configuracion de reservas.
		$this->save_reservas_config_fields( $post_id );

		// TODO: Guardar notas privadas.
		$this->save_notas_fields( $post_id );
	}

	/**
	 * Renderiza el meta box de informacion general.
	 *
	 * Muestra los campos: fecha/hora, ubicacion, precio,
	 * galeria de imagenes y enlace a mapa.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post Objeto del post actual.
	 * @return void
	 */
	public function render_meta_box_general( $post ) {
		// Nonce para verificacion.
		wp_nonce_field( 'lostrego_save_meta', 'lostrego_meta_nonce' );

		// Obtener valores actuales.
		$fecha_hora = get_post_meta( $post->ID, $this->meta_prefix . 'fecha_hora', true );
		$ubicacion  = get_post_meta( $post->ID, $this->meta_prefix . 'ubicacion', true );
		$precio     = get_post_meta( $post->ID, $this->meta_prefix . 'precio', true );

		// TODO: Renderizar formulario con campos de informacion general.
		?>
		<table class="form-table lostrego-meta-table">
			<tr>
				<th>
					<label for="lostrego_fecha_hora">
						<?php esc_html_e( 'Fecha y hora', 'lostrego-reservas' ); ?>
					</label>
				</th>
				<td>
					<input
						type="datetime-local"
						id="lostrego_fecha_hora"
						name="lostrego_fecha_hora"
						value="<?php echo esc_attr( $fecha_hora ); ?>"
						class="regular-text"
					/>
					<p class="description">
						<?php esc_html_e( 'Fecha y hora de inicio del evento.', 'lostrego-reservas' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="lostrego_ubicacion">
						<?php esc_html_e( 'Ubicacion', 'lostrego-reservas' ); ?>
					</label>
				</th>
				<td>
					<input
						type="text"
						id="lostrego_ubicacion"
						name="lostrego_ubicacion"
						value="<?php echo esc_attr( $ubicacion ); ?>"
						class="regular-text"
					/>
					<p class="description">
						<?php esc_html_e( 'Direccion o sala del evento.', 'lostrego-reservas' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="lostrego_precio">
						<?php esc_html_e( 'Precio', 'lostrego-reservas' ); ?>
					</label>
				</th>
				<td>
					<input
						type="number"
						id="lostrego_precio"
						name="lostrego_precio"
						value="<?php echo esc_attr( $precio ); ?>"
						class="small-text"
						min="0"
						step="0.01"
					/>
					<span>&euro;</span>
					<p class="description">
						<?php esc_html_e( 'Introduce 0 para eventos gratuitos.', 'lostrego-reservas' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Renderiza el meta box de aforo y plazas.
	 *
	 * Muestra los campos: aforo total, aforo reservable,
	 * total reservado (solo lectura), limite de plazas por usuario.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post Objeto del post actual.
	 * @return void
	 */
	public function render_meta_box_aforo( $post ) {
		// Obtener valores actuales.
		$aforo_total      = get_post_meta( $post->ID, $this->meta_prefix . 'aforo_total', true );
		$aforo_reservable = get_post_meta( $post->ID, $this->meta_prefix . 'aforo_reservable', true );
		$total_reservado  = get_post_meta( $post->ID, $this->meta_prefix . 'total_reservado', true );
		$limite_min       = get_post_meta( $post->ID, $this->meta_prefix . 'limite_plazas_min', true );
		$limite_max       = get_post_meta( $post->ID, $this->meta_prefix . 'limite_plazas_max', true );

		// TODO: Renderizar formulario con campos de aforo.
		?>
		<table class="form-table lostrego-meta-table">
			<tr>
				<th>
					<label for="lostrego_aforo_total">
						<?php esc_html_e( 'Aforo total', 'lostrego-reservas' ); ?>
					</label>
				</th>
				<td>
					<input
						type="number"
						id="lostrego_aforo_total"
						name="lostrego_aforo_total"
						value="<?php echo esc_attr( $aforo_total ); ?>"
						class="small-text"
						min="0"
					/>
					<p class="description">
						<?php esc_html_e( 'Capacidad maxima total del evento.', 'lostrego-reservas' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="lostrego_aforo_reservable">
						<?php esc_html_e( 'Aforo disponible para reservar', 'lostrego-reservas' ); ?>
					</label>
				</th>
				<td>
					<input
						type="number"
						id="lostrego_aforo_reservable"
						name="lostrego_aforo_reservable"
						value="<?php echo esc_attr( $aforo_reservable ); ?>"
						class="small-text"
						min="0"
					/>
					<p class="description">
						<?php esc_html_e( 'Puede ser menor que el aforo total.', 'lostrego-reservas' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<label>
						<?php esc_html_e( 'Total reservado', 'lostrego-reservas' ); ?>
					</label>
				</th>
				<td>
					<strong><?php echo absint( $total_reservado ); ?></strong>
					<p class="description">
						<?php esc_html_e( 'Plazas reservadas actualmente (solo lectura).', 'lostrego-reservas' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th>
					<label for="lostrego_limite_plazas_min">
						<?php esc_html_e( 'Plazas por usuario', 'lostrego-reservas' ); ?>
					</label>
				</th>
				<td>
					<label>
						<?php esc_html_e( 'Min:', 'lostrego-reservas' ); ?>
						<input
							type="number"
							id="lostrego_limite_plazas_min"
							name="lostrego_limite_plazas_min"
							value="<?php echo esc_attr( $limite_min ); ?>"
							class="small-text"
							min="1"
						/>
					</label>
					<label>
						<?php esc_html_e( 'Max:', 'lostrego-reservas' ); ?>
						<input
							type="number"
							id="lostrego_limite_plazas_max"
							name="lostrego_limite_plazas_max"
							value="<?php echo esc_attr( $limite_max ); ?>"
							class="small-text"
							min="1"
						/>
					</label>
					<p class="description">
						<?php esc_html_e( 'Limite de plazas que un usuario puede reservar.', 'lostrego-reservas' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Renderiza el meta box de configuracion de reservas.
	 *
	 * Muestra: lista de espera, cancelaciones, plazo cancelacion,
	 * confirmacion manual, notificar cambios.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post Objeto del post actual.
	 * @return void
	 */
	public function render_meta_box_reservas_config( $post ) {
		// Obtener valores actuales.
		$lista_espera        = get_post_meta( $post->ID, $this->meta_prefix . 'lista_espera', true );
		$cancelaciones       = get_post_meta( $post->ID, $this->meta_prefix . 'habilitar_cancelaciones', true );
		$plazo_cancelacion   = get_post_meta( $post->ID, $this->meta_prefix . 'plazo_cancelacion', true );
		$confirmacion_manual = get_post_meta( $post->ID, $this->meta_prefix . 'confirmacion_manual', true );
		$notificar_cambio    = get_post_meta( $post->ID, $this->meta_prefix . 'notificar_cambio', true );

		// TODO: Renderizar checkboxes y campos de configuracion.
		?>
		<p>
			<label>
				<input
					type="checkbox"
					name="lostrego_lista_espera"
					value="1"
					<?php checked( $lista_espera, '1' ); ?>
				/>
				<?php esc_html_e( 'Habilitar lista de espera', 'lostrego-reservas' ); ?>
			</label>
		</p>
		<p>
			<label>
				<input
					type="checkbox"
					name="lostrego_habilitar_cancelaciones"
					value="1"
					<?php checked( $cancelaciones, '1' ); ?>
				/>
				<?php esc_html_e( 'Permitir cancelaciones', 'lostrego-reservas' ); ?>
			</label>
		</p>
		<p>
			<label for="lostrego_plazo_cancelacion">
				<?php esc_html_e( 'Plazo cancelacion (horas):', 'lostrego-reservas' ); ?>
			</label>
			<input
				type="number"
				id="lostrego_plazo_cancelacion"
				name="lostrego_plazo_cancelacion"
				value="<?php echo esc_attr( $plazo_cancelacion ); ?>"
				class="small-text"
				min="0"
			/>
		</p>
		<p>
			<label>
				<input
					type="checkbox"
					name="lostrego_confirmacion_manual"
					value="1"
					<?php checked( $confirmacion_manual, '1' ); ?>
				/>
				<?php esc_html_e( 'Requiere confirmacion manual', 'lostrego-reservas' ); ?>
			</label>
		</p>
		<hr />
		<p>
			<label>
				<input
					type="checkbox"
					name="lostrego_notificar_cambio"
					value="1"
					<?php checked( $notificar_cambio, '1' ); ?>
				/>
				<strong><?php esc_html_e( 'Notificar cambios a reservas', 'lostrego-reservas' ); ?></strong>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'Si se activa, se enviara un email a todos los usuarios con reserva informando de los cambios.', 'lostrego-reservas' ); ?>
		</p>
		<?php
	}

	/**
	 * Renderiza el meta box de notas privadas.
	 *
	 * Muestra un area de texto para notas internas solo visibles
	 * por los organizadores en el backend.
	 *
	 * @since  1.0.0
	 * @param  WP_Post $post Objeto del post actual.
	 * @return void
	 */
	public function render_meta_box_notas( $post ) {
		$notas = get_post_meta( $post->ID, $this->meta_prefix . 'notas_privadas', true );

		?>
		<p>
			<label for="lostrego_notas_privadas">
				<?php esc_html_e( 'Notas internas para el equipo organizador:', 'lostrego-reservas' ); ?>
			</label>
		</p>
		<textarea
			id="lostrego_notas_privadas"
			name="lostrego_notas_privadas"
			rows="5"
			class="large-text"
		><?php echo esc_textarea( $notas ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Estas notas solo son visibles en el panel de administracion.', 'lostrego-reservas' ); ?>
		</p>
		<?php
	}

	/**
	 * Guarda los campos de informacion general.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int $post_id ID del post.
	 * @return void
	 */
	private function save_general_fields( $post_id ) {
		// TODO: Implementar guardado y sanitizacion de campos generales.
		if ( isset( $_POST['lostrego_fecha_hora'] ) ) {
			update_post_meta(
				$post_id,
				$this->meta_prefix . 'fecha_hora',
				sanitize_text_field( wp_unslash( $_POST['lostrego_fecha_hora'] ) )
			);
		}

		if ( isset( $_POST['lostrego_ubicacion'] ) ) {
			update_post_meta(
				$post_id,
				$this->meta_prefix . 'ubicacion',
				sanitize_text_field( wp_unslash( $_POST['lostrego_ubicacion'] ) )
			);
		}

		if ( isset( $_POST['lostrego_precio'] ) ) {
			update_post_meta(
				$post_id,
				$this->meta_prefix . 'precio',
				floatval( $_POST['lostrego_precio'] )
			);
		}
	}

	/**
	 * Guarda los campos de aforo.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int $post_id ID del post.
	 * @return void
	 */
	private function save_aforo_fields( $post_id ) {
		// TODO: Implementar guardado y sanitizacion de campos de aforo.
		if ( isset( $_POST['lostrego_aforo_total'] ) ) {
			update_post_meta(
				$post_id,
				$this->meta_prefix . 'aforo_total',
				absint( $_POST['lostrego_aforo_total'] )
			);
		}

		if ( isset( $_POST['lostrego_aforo_reservable'] ) ) {
			update_post_meta(
				$post_id,
				$this->meta_prefix . 'aforo_reservable',
				absint( $_POST['lostrego_aforo_reservable'] )
			);
		}

		if ( isset( $_POST['lostrego_limite_plazas_min'] ) ) {
			update_post_meta(
				$post_id,
				$this->meta_prefix . 'limite_plazas_min',
				absint( $_POST['lostrego_limite_plazas_min'] )
			);
		}

		if ( isset( $_POST['lostrego_limite_plazas_max'] ) ) {
			update_post_meta(
				$post_id,
				$this->meta_prefix . 'limite_plazas_max',
				absint( $_POST['lostrego_limite_plazas_max'] )
			);
		}
	}

	/**
	 * Guarda los campos de configuracion de reservas.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int $post_id ID del post.
	 * @return void
	 */
	private function save_reservas_config_fields( $post_id ) {
		// TODO: Implementar guardado de configuracion.
		$checkboxes = array(
			'lista_espera',
			'habilitar_cancelaciones',
			'confirmacion_manual',
			'notificar_cambio',
		);

		foreach ( $checkboxes as $field ) {
			$value = isset( $_POST[ 'lostrego_' . $field ] ) ? '1' : '0';
			update_post_meta( $post_id, $this->meta_prefix . $field, $value );
		}

		if ( isset( $_POST['lostrego_plazo_cancelacion'] ) ) {
			update_post_meta(
				$post_id,
				$this->meta_prefix . 'plazo_cancelacion',
				absint( $_POST['lostrego_plazo_cancelacion'] )
			);
		}
	}

	/**
	 * Guarda las notas privadas.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  int $post_id ID del post.
	 * @return void
	 */
	private function save_notas_fields( $post_id ) {
		if ( isset( $_POST['lostrego_notas_privadas'] ) ) {
			update_post_meta(
				$post_id,
				$this->meta_prefix . 'notas_privadas',
				sanitize_textarea_field( wp_unslash( $_POST['lostrego_notas_privadas'] ) )
			);
		}
	}
}
