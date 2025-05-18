<?php
/**
 * Split Installer class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Split\Install;

use WC_Asaas\Split\Database\Split_History;

/**
 * Handles split feature installation
 */
class Split_Installer {

    /**
     * Instance of this class
     *
     * @var self
     */
    protected static $instance = null;

    /**
     * Return an instance of this class
     *
     * @return self A single instance of this class.
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Install split feature
     */
    public function install() {
        $this->create_tables();
    }

    /**
     * Create required database tables
     */
    private function create_tables() {
        Split_History::get_instance()->create_table();
    }

    /**
     * Check if installation is needed
     *
     * @return bool True if needs installation
     */
    public function needs_install() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'asaas_split_history';
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SHOW TABLES LIKE %s",
                $table_name
            )
        ) === $table_name;

        return !$table_exists;
    }
}
