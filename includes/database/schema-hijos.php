<?php
/**
 * Esquema de la tabla de hijos.
 *
 * @package Lostrego_Reservas
 * @subpackage Database
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Crea la tabla de hijos.
 *
 * @param string $charset_collate Charset de la BD.
 * @return void
 */
function lostrego_create_schema_hijos( $charset_collate ) {
    global $wpdb;

    $table_name = $wpdb->prefix . LOSTREGO_TABLE_PREFIX . 'hijos';

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        reserva_id BIGINT(20) UNSIGNED NOT NULL,
        edad_hijo INT(11) NOT NULL,
        genero_hijo VARCHAR(30) NOT NULL DEFAULT 'prefiero_no_decir',
        orden INT(11) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        KEY idx_reserva (reserva_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
