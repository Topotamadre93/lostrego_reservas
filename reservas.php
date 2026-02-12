<?php
/**
 * Plugin Name:       Lostrego Reservas
 * Plugin URI:        https://lostregofestival.com/
 * Description:       Plugin de reservas para el Lostrego Festival de Cine. Gestiona eventos, reservas, QR, estadísticas y más.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Lostrego Festival de Cine
 * Author URI:        https://lostregofestival.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       lostrego-reservas
 * Domain Path:       /languages
 *
 * @package Lostrego_Reservas
 */

// Si se accede directamente, salir.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Versión actual del plugin.
 */
define( 'LOSTREGO_VERSION', '1.0.0' );

/**
 * Versión de la base de datos.
 */
define( 'LOSTREGO_DB_VERSION', '1.0' );

/**
 * Ruta absoluta al archivo principal del plugin.
 */
define( 'LOSTREGO_PLUGIN_FILE', __FILE__ );

/**
 * Ruta absoluta al directorio del plugin (con trailing slash).
 */
define( 'LOSTREGO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL al directorio del plugin (con trailing slash).
 */
define( 'LOSTREGO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Nombre base del plugin (ej: lostrego-reservas/reservas.php).
 */
define( 'LOSTREGO_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Prefijo para tablas de la base de datos.
 */
define( 'LOSTREGO_TABLE_PREFIX', 'reservas_' );

/**
 * Verificación de versión de PHP.
 */
function lostrego_check_php_version() {
    if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
        add_action( 'admin_notices', 'lostrego_php_version_error' );
        return false;
    }
    if ( version_compare( PHP_VERSION, '8.3', '>' ) ) {
        add_action( 'admin_notices', 'lostrego_php_version_warning' );
    }
    return true;
}

/**
 * Error: PHP < 7.4.
 */
function lostrego_php_version_error() {
    $message = sprintf(
        /* translators: %s: PHP version */
        esc_html__( 'Lostrego Reservas requiere PHP 7.4 o superior. Tu versión actual es %s. El plugin ha sido desactivado.', 'lostrego-reservas' ),
        PHP_VERSION
    );
    printf( '<div class="notice notice-error"><p>%s</p></div>', $message );
    deactivate_plugins( LOSTREGO_PLUGIN_BASENAME );
}

/**
 * Warning: PHP > 8.3.
 */
function lostrego_php_version_warning() {
    $message = sprintf(
        /* translators: %s: PHP version */
        esc_html__( 'Lostrego Reservas no ha sido testeado con PHP %s. Algunas funcionalidades podrían no funcionar correctamente.', 'lostrego-reservas' ),
        PHP_VERSION
    );
    printf( '<div class="notice notice-warning"><p>%s</p></div>', $message );
}

/**
 * Verificación de versión de WordPress.
 */
function lostrego_check_wp_version() {
    global $wp_version;
    if ( version_compare( $wp_version, '5.8', '<' ) ) {
        add_action( 'admin_notices', 'lostrego_wp_version_error' );
        return false;
    }
    return true;
}

/**
 * Error: WP < 5.8.
 */
function lostrego_wp_version_error() {
    $message = esc_html__( 'Lostrego Reservas requiere WordPress 5.8 o superior. Por favor, actualiza WordPress.', 'lostrego-reservas' );
    printf( '<div class="notice notice-error"><p>%s</p></div>', $message );
}

/**
 * Verificación de extensiones PHP requeridas.
 */
function lostrego_check_php_extensions() {
    $required = array( 'gd', 'mbstring', 'zip', 'curl' );
    $missing  = array();

    foreach ( $required as $ext ) {
        if ( ! extension_loaded( $ext ) ) {
            $missing[] = $ext;
        }
    }

    if ( ! empty( $missing ) ) {
        add_action(
            'admin_notices',
            function () use ( $missing ) {
                $message = sprintf(
                    /* translators: %s: list of extensions */
                    esc_html__( 'Lostrego Reservas requiere las siguientes extensiones PHP: %s', 'lostrego-reservas' ),
                    implode( ', ', $missing )
                );
                printf( '<div class="notice notice-error"><p>%s</p></div>', $message );
            }
        );
        return false;
    }

    return true;
}

// Verificaciones antes de cargar.
if ( ! lostrego_check_php_version() ) {
    return;
}

// Autoload de Composer.
if ( file_exists( LOSTREGO_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once LOSTREGO_PLUGIN_DIR . 'vendor/autoload.php';
}

// Cargar archivos del core.
require_once LOSTREGO_PLUGIN_DIR . 'core/class-plugin.php';
require_once LOSTREGO_PLUGIN_DIR . 'core/class-loader.php';
require_once LOSTREGO_PLUGIN_DIR . 'core/class-activator.php';
require_once LOSTREGO_PLUGIN_DIR . 'core/class-deactivator.php';
require_once LOSTREGO_PLUGIN_DIR . 'core/class-i18n.php';

// Cargar helpers.
require_once LOSTREGO_PLUGIN_DIR . 'includes/helpers/functions-general.php';
require_once LOSTREGO_PLUGIN_DIR . 'includes/helpers/functions-date.php';
require_once LOSTREGO_PLUGIN_DIR . 'includes/helpers/functions-format.php';
require_once LOSTREGO_PLUGIN_DIR . 'includes/helpers/functions-ajax.php';

/**
 * Hook de activación.
 */
register_activation_hook( __FILE__, array( 'Lostrego_Activator', 'activate' ) );

/**
 * Hook de desactivación.
 */
register_deactivation_hook( __FILE__, array( 'Lostrego_Deactivator', 'deactivate' ) );

/**
 * Inicia la ejecución del plugin.
 *
 * @return void
 */
function lostrego_run() {
    // Verificaciones adicionales que necesitan WP cargado.
    if ( ! lostrego_check_wp_version() ) {
        return;
    }
    if ( ! lostrego_check_php_extensions() ) {
        return;
    }

    $plugin = new Lostrego_Plugin();
    $plugin->run();
}

lostrego_run();
