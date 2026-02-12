<?php
/**
 * Cargador de hooks (actions y filters).
 *
 * @package Lostrego_Reservas
 * @subpackage Core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clase que gestiona el registro de actions y filters de WordPress.
 */
class Lostrego_Loader {

    /**
     * Acciones registradas.
     *
     * @var array
     */
    protected $actions;

    /**
     * Filtros registrados.
     *
     * @var array
     */
    protected $filters;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->actions = array();
        $this->filters = array();
    }

    /**
     * Añade una acción.
     *
     * @param string $hook          Hook de WordPress.
     * @param object $component     Objeto que contiene el método.
     * @param string $callback      Método a ejecutar.
     * @param int    $priority      Prioridad.
     * @param int    $accepted_args Número de argumentos.
     * @return void
     */
    public function add_action( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->actions = $this->add( $this->actions, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * Añade un filtro.
     *
     * @param string $hook          Hook de WordPress.
     * @param object $component     Objeto que contiene el método.
     * @param string $callback      Método a ejecutar.
     * @param int    $priority      Prioridad.
     * @param int    $accepted_args Número de argumentos.
     * @return void
     */
    public function add_filter( $hook, $component, $callback, $priority = 10, $accepted_args = 1 ) {
        $this->filters = $this->add( $this->filters, $hook, $component, $callback, $priority, $accepted_args );
    }

    /**
     * Añade un hook a la colección.
     *
     * @param array  $hooks         Colección existente.
     * @param string $hook          Hook de WordPress.
     * @param object $component     Objeto que contiene el método.
     * @param string $callback      Método a ejecutar.
     * @param int    $priority      Prioridad.
     * @param int    $accepted_args Número de argumentos.
     * @return array
     */
    private function add( $hooks, $hook, $component, $callback, $priority, $accepted_args ) {
        $hooks[] = array(
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args,
        );

        return $hooks;
    }

    /**
     * Registra todos los hooks con WordPress.
     *
     * @return void
     */
    public function run() {
        foreach ( $this->filters as $hook ) {
            add_filter(
                $hook['hook'],
                array( $hook['component'], $hook['callback'] ),
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ( $this->actions as $hook ) {
            add_action(
                $hook['hook'],
                array( $hook['component'], $hook['callback'] ),
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}
