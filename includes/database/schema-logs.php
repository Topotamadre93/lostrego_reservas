<?php
/**
 * Esquema de la tabla de logs.
 *
 * @package Lostrego_Reservas
 * @subpackage Database
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Crea la tabla de logs.
 *
 * @param string $charset_collate Charset de la BD.
 * @return void
 */
function lostrego_create_schema_logs( $charset_collate ) {
    global $wpdb;

    $table_name = $wpdb->prefix . LOSTREGO_TABLE_PREFIX . 'logs';

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        tipo_accion VARCHAR(50) NOT NULL,
        nivel VARCHAR(20) NOT NULL DEFAULT 'info',
        usuario BIGINT(20) UNSIGNED DEFAULT NULL,
        evento BIGINT(20) UNSIGNED DEFAULT NULL,
        mensaje TEXT NOT NULL,
        detalles LONGTEXT DEFAULT NULL,
        ip VARCHAR(45) DEFAULT NULL,
        php_version VARCHAR(20) DEFAULT NULL,
        wp_version VARCHAR(20) DEFAULT NULL,
        plugin_version VARCHAR(20) DEFAULT NULL,
        fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_tipo_accion (tipo_accion),
        KEY idx_nivel (nivel),
        KEY idx_usuario (usuario),
        KEY idx_evento (evento),
        KEY idx_fecha (fecha)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
