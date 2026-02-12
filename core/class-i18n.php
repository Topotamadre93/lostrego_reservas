<?php
/**
 * Internacionalización del plugin.
 *
 * @package Lostrego_Reservas
 * @subpackage Core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase que gestiona la carga de traducciones.
 */
class Lostrego_I18n {

    /**
     * Carga el text domain del plugin.
     *
     * @return void
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'lostrego-reservas',
            false,
            dirname( LOSTREGO_PLUGIN_BASENAME ) . '/languages'
        );
    }
}
