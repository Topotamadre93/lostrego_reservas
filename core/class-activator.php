<?php
/**
 * Hook de activación del plugin.
 *
 * @package Lostrego_Reservas
 * @subpackage Core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase ejecutada al activar el plugin.
 */
class Lostrego_Activator {

    /**
     * Ejecuta las tareas de activación.
     *
     * @return void
     */
    public static function activate() {
        self::check_requirements();
        self::create_tables();
        self::create_roles();
        self::create_directories();
        self::set_default_options();
        self::schedule_cron_events();

        // Guardar versión de BD.
        update_option( 'lostrego_db_version', LOSTREGO_DB_VERSION );

        // Flush rewrite rules para el CPT.
        flush_rewrite_rules();
    }

    /**
     * Verifica requisitos mínimos.
     *
     * @return void
     */
    private static function check_requirements() {
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            deactivate_plugins( LOSTREGO_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'Lostrego Reservas requiere PHP 7.4 o superior.', 'lostrego-reservas' ),
                esc_html__( 'Error de activación', 'lostrego-reservas' ),
                array( 'back_link' => true )
            );
        }

        global $wp_version;
        if ( version_compare( $wp_version, '5.8', '<' ) ) {
            deactivate_plugins( LOSTREGO_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'Lostrego Reservas requiere WordPress 5.8 o superior.', 'lostrego-reservas' ),
                esc_html__( 'Error de activación', 'lostrego-reservas' ),
                array( 'back_link' => true )
            );
        }
    }

    /**
     * Crea las tablas de la base de datos.
     *
     * @return void
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/database/schema-reservas.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/database/schema-logs.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/database/schema-info-familiar.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/database/schema-hijos.php';

        lostrego_create_schema_reservas( $charset_collate );
        lostrego_create_schema_logs( $charset_collate );
        lostrego_create_schema_info_familiar( $charset_collate );
        lostrego_create_schema_hijos( $charset_collate );
        lostrego_create_schema_stats_diarias( $charset_collate );
    }

    /**
     * Crea los roles personalizados.
     *
     * @return void
     */
    private static function create_roles() {
        require_once LOSTREGO_PLUGIN_DIR . 'includes/roles/role-definitions.php';
        lostrego_create_roles();
    }

    /**
     * Crea los directorios necesarios.
     *
     * @return void
     */
    private static function create_directories() {
        $dirs = array(
            LOSTREGO_PLUGIN_DIR . 'includes/qr/qrcodes',
        );

        foreach ( $dirs as $dir ) {
            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );
            }
        }
    }

    /**
     * Establece opciones por defecto.
     *
     * @return void
     */
    private static function set_default_options() {
        $defaults = array(
            'lostrego_festival_name'       => 'Lostrego Festival de Cine',
            'lostrego_festival_email'      => get_option( 'admin_email' ),
            'lostrego_max_reservas_dia'    => 10,
            'lostrego_tiempo_cancelacion'  => 24,
            'lostrego_qr_size'             => 300,
            'lostrego_qr_reentry'          => true,
            'lostrego_qr_token_expiry'     => 48,
            'lostrego_waitlist_enabled'    => true,
            'lostrego_noshow_time'         => 30,
            'lostrego_rgpd_retention_days' => 365,
            'lostrego_active_theme'        => 'default',
            'lostrego_emergency_mode'      => false,
            'lostrego_disk_warning_mb'     => 500,
            'lostrego_cleanup_days'        => 30,
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }

    /**
     * Programa eventos WP Cron.
     *
     * @return void
     */
    private static function schedule_cron_events() {
        if ( ! wp_next_scheduled( 'lostrego_cron_stats_update' ) ) {
            wp_schedule_event( time(), 'hourly', 'lostrego_cron_stats_update' );
        }

        if ( ! wp_next_scheduled( 'lostrego_cron_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'lostrego_cron_cleanup' );
        }

        if ( ! wp_next_scheduled( 'lostrego_cron_email_check' ) ) {
            wp_schedule_event( time(), 'weekly', 'lostrego_cron_email_check' );
        }

        if ( ! wp_next_scheduled( 'lostrego_cron_backup' ) ) {
            wp_schedule_event( time(), 'daily', 'lostrego_cron_backup' );
        }
    }
}
