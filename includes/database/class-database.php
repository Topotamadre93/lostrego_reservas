<?php
/**
 * Clase principal de la base de datos.
 *
 * @package Lostrego_Reservas
 * @subpackage Database
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Gestiona operaciones de base de datos del plugin.
 */
class Lostrego_Database {

    /**
     * Prefijo de tablas del plugin.
     *
     * @var string
     */
    private $prefix;

    /**
     * Constructor.
     */
    public function __construct() {
        global $wpdb;
        $this->prefix = $wpdb->prefix . LOSTREGO_TABLE_PREFIX;
    }

    /**
     * Obtiene el nombre completo de una tabla.
     *
     * @param string $table Nombre base de la tabla.
     * @return string
     */
    public function get_table_name( $table ) {
        return $this->prefix . $table;
    }

    /**
     * Verifica si todas las tablas existen.
     *
     * @return array Lista de tablas faltantes.
     */
    public function check_tables() {
        global $wpdb;

        $required_tables = array(
            $this->get_table_name( '' ),
            $this->get_table_name( 'info_familiar' ),
            $this->get_table_name( 'hijos' ),
            $this->get_table_name( 'logs' ),
            $this->get_table_name( 'stats_diarias' ),
        );

        $missing = array();

        foreach ( $required_tables as $table ) {
            $result = $wpdb->get_var(
                $wpdb->prepare( 'SHOW TABLES LIKE %s', $table )
            );
            if ( $result !== $table ) {
                $missing[] = $table;
            }
        }

        return $missing;
    }

    /**
     * Verifica y ejecuta migraciones pendientes.
     *
     * @return void
     */
    public function check_migrations() {
        $current_version = get_option( 'lostrego_db_version', '0' );

        if ( version_compare( $current_version, LOSTREGO_DB_VERSION, '<' ) ) {
            $migrator = new Lostrego_Migrator();
            $migrator->run( $current_version );
            update_option( 'lostrego_db_version', LOSTREGO_DB_VERSION );
        }
    }
}
