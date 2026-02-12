<?php
/**
 * Custom Post Type para Eventos del festival.
 *
 * Registra el CPT 'lostrego_evento' con todas sus etiquetas,
 * capacidades y soporte de features de WordPress.
 *
 * @package    Lostrego_Reservas
 * @subpackage Lostrego_Reservas/includes/events
 * @since      1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Clase Lostrego_Events_CPT
 *
 * Gestiona el registro del Custom Post Type 'lostrego_evento'
 * y sus meta fields asociados.
 *
 * @since 1.0.0
 */
class Lostrego_Events_CPT {

	/**
	 * Slug del Custom Post Type.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $post_type = 'lostrego_evento';

	/**
	 * Constructor.
	 *
	 * Registra los hooks necesarios para el CPT.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_meta_fields' ) );
	}

	/**
	 * Registra el Custom Post Type 'lostrego_evento'.
	 *
	 * Define las etiquetas, argumentos y capacidades del CPT.
	 * Todas las etiquetas son traducibles mediante __().
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'                  => __( 'Eventos', 'lostrego-reservas' ),
			'singular_name'         => __( 'Evento', 'lostrego-reservas' ),
			'menu_name'             => __( 'Eventos', 'lostrego-reservas' ),
			'name_admin_bar'        => __( 'Evento', 'lostrego-reservas' ),
			'add_new'               => __( 'Añadir nuevo', 'lostrego-reservas' ),
			'add_new_item'          => __( 'Añadir nuevo evento', 'lostrego-reservas' ),
			'new_item'              => __( 'Nuevo evento', 'lostrego-reservas' ),
			'edit_item'             => __( 'Editar evento', 'lostrego-reservas' ),
			'view_item'             => __( 'Ver evento', 'lostrego-reservas' ),
			'all_items'             => __( 'Todos los eventos', 'lostrego-reservas' ),
			'search_items'          => __( 'Buscar eventos', 'lostrego-reservas' ),
			'parent_item_colon'     => __( 'Evento padre:', 'lostrego-reservas' ),
			'not_found'             => __( 'No se encontraron eventos.', 'lostrego-reservas' ),
			'not_found_in_trash'    => __( 'No se encontraron eventos en la papelera.', 'lostrego-reservas' ),
			'featured_image'        => __( 'Imagen del evento', 'lostrego-reservas' ),
			'set_featured_image'    => __( 'Establecer imagen del evento', 'lostrego-reservas' ),
			'remove_featured_image' => __( 'Eliminar imagen del evento', 'lostrego-reservas' ),
			'use_featured_image'    => __( 'Usar como imagen del evento', 'lostrego-reservas' ),
			'archives'              => __( 'Archivo de eventos', 'lostrego-reservas' ),
			'insert_into_item'      => __( 'Insertar en evento', 'lostrego-reservas' ),
			'uploaded_to_this_item' => __( 'Subido a este evento', 'lostrego-reservas' ),
			'filter_items_list'     => __( 'Filtrar lista de eventos', 'lostrego-reservas' ),
			'items_list_navigation' => __( 'Navegación de lista de eventos', 'lostrego-reservas' ),
			'items_list'            => __( 'Lista de eventos', 'lostrego-reservas' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'evento' ),
			'capability_type'    => 'post',
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-tickets-alt',
			'show_in_rest'       => true,
			'supports'           => array(
				'title',
				'editor',
				'thumbnail',
				'excerpt',
				'custom-fields',
				'revisions',
			),
		);

		register_post_type( $this->post_type, $args );
	}

	/**
	 * Registra los meta fields asociados al CPT.
	 *
	 * Define los campos meta globales que aplican a todos los eventos:
	 * fecha/hora, aforo, ubicacion, precio, lista de espera, etc.
	 *
	 * @since  1.0.0
	 * @return void
	 */
	public function register_meta_fields() {
		$meta_fields = array(
			'_lostrego_fecha_hora'              => array(
				'type'        => 'string',
				'description' => __( 'Fecha y hora del evento', 'lostrego-reservas' ),
			),
			'_lostrego_aforo_total'             => array(
				'type'        => 'integer',
				'description' => __( 'Aforo total del evento', 'lostrego-reservas' ),
			),
			'_lostrego_aforo_reservable'        => array(
				'type'        => 'integer',
				'description' => __( 'Aforo disponible para reservar', 'lostrego-reservas' ),
			),
			'_lostrego_total_reservado'         => array(
				'type'        => 'integer',
				'description' => __( 'Total de plazas reservadas', 'lostrego-reservas' ),
			),
			'_lostrego_limite_plazas_min'       => array(
				'type'        => 'integer',
				'description' => __( 'Minimo de plazas por usuario', 'lostrego-reservas' ),
			),
			'_lostrego_limite_plazas_max'       => array(
				'type'        => 'integer',
				'description' => __( 'Maximo de plazas por usuario', 'lostrego-reservas' ),
			),
			'_lostrego_ubicacion'               => array(
				'type'        => 'string',
				'description' => __( 'Ubicacion del evento', 'lostrego-reservas' ),
			),
			'_lostrego_precio'                  => array(
				'type'        => 'number',
				'description' => __( 'Precio del evento', 'lostrego-reservas' ),
			),
			'_lostrego_lista_espera'            => array(
				'type'        => 'boolean',
				'description' => __( 'Habilitar lista de espera', 'lostrego-reservas' ),
			),
			'_lostrego_habilitar_cancelaciones' => array(
				'type'        => 'boolean',
				'description' => __( 'Habilitar cancelaciones', 'lostrego-reservas' ),
			),
			'_lostrego_plazo_cancelacion'       => array(
				'type'        => 'integer',
				'description' => __( 'Plazo de cancelacion en horas', 'lostrego-reservas' ),
			),
			'_lostrego_confirmacion_manual'     => array(
				'type'        => 'boolean',
				'description' => __( 'Requiere confirmacion manual del admin', 'lostrego-reservas' ),
			),
			'_lostrego_notificar_cambio'        => array(
				'type'        => 'boolean',
				'description' => __( 'Notificar cambios a usuarios con reserva', 'lostrego-reservas' ),
			),
			'_lostrego_notas_privadas'          => array(
				'type'        => 'string',
				'description' => __( 'Notas privadas para organizadores', 'lostrego-reservas' ),
			),
		);

		foreach ( $meta_fields as $meta_key => $meta_args ) {
			register_post_meta(
				$this->post_type,
				$meta_key,
				array(
					'type'              => $meta_args['type'],
					'description'       => $meta_args['description'],
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => array( $this, 'sanitize_meta_field' ),
					'auth_callback'     => array( $this, 'auth_meta_field' ),
				)
			);
		}
	}

	/**
	 * Sanitiza un campo meta del evento.
	 *
	 * @since  1.0.0
	 * @param  mixed  $value    Valor del campo a sanitizar.
	 * @param  string $meta_key Clave del campo meta.
	 * @param  string $type     Tipo de objeto (post_type).
	 * @return mixed Valor sanitizado.
	 */
	public function sanitize_meta_field( $value, $meta_key = '', $type = '' ) {
		// TODO: Implementar sanitizacion especifica segun tipo de campo.
		return $value;
	}

	/**
	 * Verifica permisos para editar un campo meta.
	 *
	 * @since  1.0.0
	 * @param  bool   $allowed  Si la operacion esta permitida.
	 * @param  string $meta_key Clave del campo meta.
	 * @param  int    $post_id  ID del post.
	 * @param  int    $user_id  ID del usuario.
	 * @return bool True si el usuario tiene permisos.
	 */
	public function auth_meta_field( $allowed, $meta_key = '', $post_id = 0, $user_id = 0 ) {
		// TODO: Verificar capacidades del usuario.
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Devuelve el slug del Custom Post Type.
	 *
	 * @since  1.0.0
	 * @return string Slug del CPT.
	 */
	public function get_post_type() {
		return $this->post_type;
	}
}
