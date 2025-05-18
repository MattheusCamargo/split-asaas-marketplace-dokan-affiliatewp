<?php
/**
 * Plugin Name:     Asaas Gateway for WooCommerce
 * Plugin URI:      https://www.asaas.com
 * Description:     Take transparent credit card and bank ticket payment checkouts on your store using Asaas.
 * Author:          Asaas
 * Author URI:      https://www.asaas.com
 * License:         GPL v2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:     woo-asaas
 * Domain Path:     /languages
 * Version:         2.6.6
 *
 * @package         WooAsaas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once 'autoload.php';

use WC_Asaas\Split\Install\Split_Installer;

add_action( 'plugins_loaded', array( \WC_Asaas\WC_Asaas::class, 'get_instance' ) );

// Hook de ativação para instalar tabelas do split
register_activation_hook(__FILE__, function() {
    $installer = Split_Installer::get_instance();
    if ($installer->needs_install()) {
        $installer->install();
    }
});
