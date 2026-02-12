<?php
/**
 * Clase principal del plugin.
 *
 * @package Lostrego_Reservas
 * @subpackage Core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase principal que orquesta el plugin.
 */
class Lostrego_Plugin {

    /**
     * Cargador de hooks.
     *
     * @var Lostrego_Loader
     */
    protected $loader;

    /**
     * Versión del plugin.
     *
     * @var string
     */
    protected $version;

    /**
     * Slug del plugin.
     *
     * @var string
     */
    protected $plugin_name;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->version     = LOSTREGO_VERSION;
        $this->plugin_name = 'lostrego-reservas';
        $this->loader      = new Lostrego_Loader();

        $this->set_locale();
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Carga la internacionalización.
     *
     * @return void
     */
    private function set_locale() {
        $i18n = new Lostrego_I18n();
        $this->loader->add_action( 'plugins_loaded', $i18n, 'load_plugin_textdomain' );
    }

    /**
     * Carga las dependencias del plugin.
     *
     * @return void
     */
    private function load_dependencies() {
        // Database.
        require_once LOSTREGO_PLUGIN_DIR . 'includes/database/class-database.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/database/class-migrator.php';

        // Events.
        require_once LOSTREGO_PLUGIN_DIR . 'includes/events/class-events-cpt.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/events/class-events-meta.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/events/class-events-taxonomy.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/events/class-custom-fields.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/events/class-events-query.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/events/class-events-closure.php';

        // Reservations.
        require_once LOSTREGO_PLUGIN_DIR . 'includes/reservations/class-reservations.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/reservations/class-reservation-form.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/reservations/class-reservation-validator.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/reservations/class-reservation-processor.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/reservations/class-waitlist.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/reservations/class-cancellations.php';

        // Users.
        require_once LOSTREGO_PLUGIN_DIR . 'includes/users/class-user-manager.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/users/class-user-profile.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/users/class-user-auth.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/users/class-user-data.php';

        // QR.
        require_once LOSTREGO_PLUGIN_DIR . 'includes/qr/class-qr-generator.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/qr/class-qr-validator.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/qr/class-qr-scanner.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/qr/class-qr-emergency.php';

        // PDF.
        require_once LOSTREGO_PLUGIN_DIR . 'includes/pdf/class-pdf-generator.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/pdf/class-pdf-ticket.php';

        // Notifications.
        require_once LOSTREGO_PLUGIN_DIR . 'includes/notifications/class-notifications.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/notifications/class-email-sender.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/notifications/class-email-templates.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/notifications/class-placeholders.php';

        // Logs.
        require_once LOSTREGO_PLUGIN_DIR . 'includes/logs/class-logger.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/logs/class-log-viewer.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/logs/class-log-exporter.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/logs/class-log-filters.php';

        // Statistics.
        require_once LOSTREGO_PLUGIN_DIR . 'includes/statistics/class-statistics.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/statistics/class-stats-calculator.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/statistics/class-stats-filters.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/statistics/class-stats-charts.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/statistics/class-stats-exporter.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/statistics/class-stats-reports.php';

        // Roles.
        require_once LOSTREGO_PLUGIN_DIR . 'includes/roles/class-capabilities.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/roles/class-roles-manager.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/roles/role-definitions.php';

        // Themes.
        require_once LOSTREGO_PLUGIN_DIR . 'includes/themes/class-theme-engine.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/themes/class-theme-loader.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/themes/class-theme-validator.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/themes/class-theme-customizer.php';

        // Security.
        require_once LOSTREGO_PLUGIN_DIR . 'includes/security/class-security.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/security/class-sanitizer.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/security/class-rgpd.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/security/class-anti-fraud.php';

        // Health.
        require_once LOSTREGO_PLUGIN_DIR . 'includes/health/class-health-check.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/health/class-system-info.php';
        require_once LOSTREGO_PLUGIN_DIR . 'includes/health/class-diagnostics.php';

        // Backup.
        require_once LOSTREGO_PLUGIN_DIR . 'includes/backup/class-backup.php';

        // Admin.
        require_once LOSTREGO_PLUGIN_DIR . 'admin/class-admin.php';
        require_once LOSTREGO_PLUGIN_DIR . 'admin/class-menu.php';
        require_once LOSTREGO_PLUGIN_DIR . 'admin/class-settings.php';

        // Public.
        require_once LOSTREGO_PLUGIN_DIR . 'public/class-public.php';
        require_once LOSTREGO_PLUGIN_DIR . 'public/class-shortcodes.php';
    }

    /**
     * Registra hooks del admin.
     *
     * @return void
     */
    private function define_admin_hooks() {
        $admin = new Lostrego_Admin( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );

        // Menu.
        $menu = new Lostrego_Menu();
        $this->loader->add_action( 'admin_menu', $menu, 'register_menus' );
    }

    /**
     * Registra hooks del frontend.
     *
     * @return void
     */
    private function define_public_hooks() {
        $public = new Lostrego_Public( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_scripts' );

        // Shortcodes.
        $shortcodes = new Lostrego_Shortcodes();
        $this->loader->add_action( 'init', $shortcodes, 'register_shortcodes' );
    }

    /**
     * Ejecuta el plugin.
     *
     * @return void
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * Retorna el nombre del plugin.
     *
     * @return string
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Retorna el cargador de hooks.
     *
     * @return Lostrego_Loader
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retorna la versión del plugin.
     *
     * @return string
     */
    public function get_version() {
        return $this->version;
    }
}
