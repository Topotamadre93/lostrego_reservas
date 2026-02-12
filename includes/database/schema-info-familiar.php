<?php
/**
 * Esquema de la tabla de información familiar.
 *
 * @package Lostrego_Reservas
 * @subpackage Database
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Crea la tabla de información familiar.
 *
 * @param string $charset_collate Charset de la BD.
 * @return void
 */
function lostrego_create_schema_info_familiar( $charset_collate ) {
    global $wpdb;

    $table_name = $wpdb->prefix . LOSTREGO_TABLE_PREFIX . 'info_familiar';

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        tiene_hijos TINYINT(1) NOT NULL DEFAULT 0,
        numero_hijos_total INT(11) NOT NULL DEFAULT 0,
        fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
