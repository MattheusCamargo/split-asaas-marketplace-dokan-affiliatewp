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

// Carrega as traduções dos plugins na ordem correta
function woo_asaas_load_plugin_textdomains() {
    // WooCommerce - deixa o próprio plugin carregar suas traduções
    
    // Dokan - permite que o próprio plugin carregue suas traduções
    if (defined('DOKAN_PLUGIN_VERSION')) {
        do_action('dokan_load_textdomain');
    }
    
    // AffiliateWP - permite que o próprio plugin carregue suas traduções
    if (defined('AFFILIATEWP_VERSION')) {
        do_action('affwp_load_textdomain');
    }
    
    // Carrega as traduções do nosso plugin
    load_plugin_textdomain('woo-asaas', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('init', 'woo_asaas_load_plugin_textdomains', 0);

// Inicializa o plugin após carregar as traduções
add_action('plugins_loaded', array(\WC_Asaas\WC_Asaas::class, 'get_instance'));

// Hook de ativação para instalar tabelas do split
register_activation_hook(__FILE__, function() {
    $installer = Split_Installer::get_instance();
    if ($installer->needs_install()) {
        $installer->install();
    }
});
