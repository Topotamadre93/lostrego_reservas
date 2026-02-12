<?php
/**
 * Esquema de la tabla de reservas.
 *
 * @package Lostrego_Reservas
 * @subpackage Database
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Crea la tabla de reservas.
 *
 * @param string $charset_collate Charset de la BD.
 * @return void
 */
function lostrego_create_schema_reservas( $charset_collate ) {
    global $wpdb;

    $table_name = $wpdb->prefix . LOSTREGO_TABLE_PREFIX;

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL,
        evento_id BIGINT(20) UNSIGNED NOT NULL,
        plazas INT(11) NOT NULL DEFAULT 1,
        estado VARCHAR(20) NOT NULL DEFAULT 'confirmada',
        hash_ticket VARCHAR(64) NOT NULL,
        acompanante_tipo VARCHAR(50) DEFAULT NULL,
        ninos_sin_adulto TINYINT(1) NOT NULL DEFAULT 0,
        contacto_emergencia_1_nombre VARCHAR(100) DEFAULT NULL,
        contacto_emergencia_1_relacion VARCHAR(50) DEFAULT NULL,
        contacto_emergencia_1_telefono VARCHAR(20) DEFAULT NULL,
        contacto_emergencia_2_nombre VARCHAR(100) DEFAULT NULL,
        contacto_emergencia_2_relacion VARCHAR(50) DEFAULT NULL,
        contacto_emergencia_2_telefono VARCHAR(20) DEFAULT NULL,
        autorizacion_fotos TINYINT(1) NOT NULL DEFAULT 0,
        autorizacion_primeros_auxilios TINYINT(1) NOT NULL DEFAULT 0,
        alergias_condiciones TEXT DEFAULT NULL,
        consentimiento_rgpd TINYINT(1) NOT NULL DEFAULT 0,
        consentimiento_estadisticas TINYINT(1) NOT NULL DEFAULT 0,
        consentimiento_comunicaciones TINYINT(1) NOT NULL DEFAULT 0,
        qr_path VARCHAR(255) DEFAULT NULL,
        pdf_path VARCHAR(255) DEFAULT NULL,
        fecha_reserva DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_cancelacion DATETIME DEFAULT NULL,
        fecha_escaneado DATETIME DEFAULT NULL,
        ip_reserva VARCHAR(45) DEFAULT NULL,
        notas_admin TEXT DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY hash_ticket (hash_ticket),
        KEY idx_user_evento (user_id, evento_id),
        KEY idx_evento (evento_id),
        KEY idx_estado (estado),
        KEY idx_fecha (fecha_reserva)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/**
 * Crea la tabla de estadísticas diarias.
 *
 * @param string $charset_collate Charset de la BD.
 * @return void
 */
function lostrego_create_schema_stats_diarias( $charset_collate ) {
    global $wpdb;

    $table_name = $wpdb->prefix . LOSTREGO_TABLE_PREFIX . 'stats_diarias';

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        evento_id BIGINT(20) UNSIGNED NOT NULL,
        fecha_calculo DATE NOT NULL,
        total_reservado INT(11) NOT NULL DEFAULT 0,
        total_asistido INT(11) NOT NULL DEFAULT 0,
        total_no_show INT(11) NOT NULL DEFAULT 0,
        total_cancelado INT(11) NOT NULL DEFAULT 0,
        aforo_disponible INT(11) NOT NULL DEFAULT 0,
        ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY evento_fecha (evento_id, fecha_calculo),
        KEY idx_evento (evento_id),
        KEY idx_fecha (fecha_calculo)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}
