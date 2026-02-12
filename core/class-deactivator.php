<?php
/**
 * Hook de desactivación del plugin.
 *
 * @package Lostrego_Reservas
 * @subpackage Core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase ejecutada al desactivar el plugin.
 */
class Lostrego_Deactivator {

    /**
     * Ejecuta las tareas de desactivación.
     *
     * No elimina datos ni tablas para preservar la información.
     *
     * @return void
     */
    public static function deactivate() {
        self::clear_cron_events();
        flush_rewrite_rules();
    }

    /**
     * Elimina los eventos WP Cron del plugin.
     *
     * @return void
     */
    private static function clear_cron_events() {
        $cron_hooks = array(
            'lostrego_cron_stats_update',
            'lostrego_cron_cleanup',
            'lostrego_cron_email_check',
            'lostrego_cron_backup',
            'lostrego_cron_noshow_check',
            'lostrego_cron_reminder_24h',
            'lostrego_cron_reminder_1h',
        );

        foreach ( $cron_hooks as $hook ) {
            $timestamp = wp_next_scheduled( $hook );
            if ( $timestamp ) {
                wp_unschedule_event( $timestamp, $hook );
            }
        }
    }
}
