<?php
/**
 * Gestor de migraciones de base de datos.
 *
 * @package Lostrego_Reservas
 * @subpackage Database
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ejecuta migraciones de BD versionadas.
 */
class Lostrego_Migrator {

    /**
     * Directorio de migraciones.
     *
     * @var string
     */
    private $migrations_dir;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->migrations_dir = LOSTREGO_PLUGIN_DIR . 'includes/database/migrations/';
    }

    /**
     * Ejecuta todas las migraciones pendientes desde la versión indicada.
     *
     * @param string $from_version Versión actual de la BD.
     * @return void
     */
    public function run( $from_version ) {
        $migrations = $this->get_pending_migrations( $from_version );

        foreach ( $migrations as $version => $file ) {
            $this->backup_before_migration( $version );
            require_once $file;

            $function_name = 'lostrego_migration_' . str_replace( '.', '_', $version );
            if ( function_exists( $function_name ) ) {
                call_user_func( $function_name );
            }

            update_option( 'lostrego_db_version', $version );
        }
    }

    /**
     * Obtiene las migraciones pendientes.
     *
     * @param string $from_version Versión desde la que buscar.
     * @return array
     */
    private function get_pending_migrations( $from_version ) {
        $migrations = array();
        $files      = glob( $this->migrations_dir . 'migration-*.php' );

        if ( empty( $files ) ) {
            return $migrations;
        }

        foreach ( $files as $file ) {
            $filename = basename( $file, '.php' );
            $version  = str_replace( 'migration-', '', $filename );

            if ( version_compare( $version, $from_version, '>' ) ) {
                $migrations[ $version ] = $file;
            }
        }

        uksort( $migrations, 'version_compare' );

        return $migrations;
    }

    /**
     * Realiza backup antes de una migración.
     *
     * @param string $version Versión de la migración.
     * @return void
     */
    private function backup_before_migration( $version ) {
        if ( function_exists( 'lostrego_log' ) ) {
            lostrego_log( 'info', sprintf( 'Iniciando migración a versión %s', $version ), array(
                'tipo_accion' => 'migracion_bd',
            ) );
        }
    }
}
