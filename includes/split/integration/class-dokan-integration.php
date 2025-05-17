<?php
/**
 * Dokan Integration class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Split\Integration;

use Exception;

/**
 * Handle Dokan marketplace integration for split payments
 */
class Dokan_Integration {

    /**
     * Instance of this class
     *
     * @var self
     */
    protected static $instance = null;

    /**
     * Is not allowed to call from outside to prevent from creating multiple instances.
     */
    private function __construct() {
        // Se o Dokan está ativo, adiciona os hooks necessários
        if ($this->is_dokan_active()) {
            add_action('dokan_settings_form_bottom', array($this, 'add_asaas_wallet_field'), 10);
            add_action('dokan_store_profile_saved', array($this, 'save_asaas_wallet_id'), 10);
            add_action('admin_notices', array($this, 'show_wallet_missing_notice'));
            add_action('dokan_dashboard_content_inside_before', array($this, 'show_vendor_wallet_missing_notice'));
        }
    }

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
     * Check if Dokan is active
     * 
     * @return bool True if Dokan is active, false otherwise
     */
    private function is_dokan_active() {
        return class_exists('WeDevs_Dokan') || class_exists('Dokan_Pro');
    }

    /**
     * Add Asaas Wallet ID field to vendor settings
     *
     * @param int $user_id Current user ID
     */
    public function add_asaas_wallet_field($user_id) {
        if (!dokan_is_user_seller($user_id)) {
            return;
        }

        $wallet_id = get_user_meta($user_id, 'asaas_wallet_id', true);
        ?>
        <div class="dokan-form-group">
            <label class="dokan-w3 dokan-control-label" for="asaas_wallet_id">
                <?php esc_html_e('Asaas Wallet ID', 'woo-asaas'); ?>
            </label>
            <div class="dokan-w5">
                <input type="text" class="dokan-form-control" name="asaas_wallet_id" id="asaas_wallet_id" 
                    value="<?php echo esc_attr($wallet_id); ?>" />
                <p class="help-block">
                    <?php esc_html_e('Informe seu ID de carteira Asaas para receber os pagamentos do marketplace.', 'woo-asaas'); ?>
                </p>
            </div>
        </div>
        <?php
    }

    /**
     * Save Asaas Wallet ID when vendor settings are saved
     *
     * @param int $user_id User ID
     */
    public function save_asaas_wallet_id($user_id) {
        if (isset($_POST['asaas_wallet_id']) && current_user_can('dokan_manage_store_settings')) {
            update_user_meta(
                $user_id,
                'asaas_wallet_id',
                sanitize_text_field($_POST['asaas_wallet_id'])
            );
        }
    }

    /**
     * Show notice to admin if vendors are missing Asaas Wallet ID
     */
    public function show_wallet_missing_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Verifica se o split dinâmico está ativo
        $gateway_settings = get_option('woocommerce_asaas-credit-card_settings'); // Poderia ser qualquer gateway Asaas
        if (!isset($gateway_settings['dynamic_split_enabled']) || $gateway_settings['dynamic_split_enabled'] !== 'yes') {
            return;
        }

        // Conta quantos vendedores estão sem wallet ID
        $vendor_query = new \WP_User_Query(array(
            'role' => 'seller',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'asaas_wallet_id',
                    'value' => '',
                    'compare' => '='
                ),
                array(
                    'key' => 'asaas_wallet_id',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));

        $vendors_without_wallet = $vendor_query->get_results();

        if (!empty($vendors_without_wallet)) {
            $count = count($vendors_without_wallet);
            $message = sprintf(
                /* translators: %d: number of vendors without wallet */
                _n(
                    '%d vendedor não configurou seu ID de carteira Asaas e não poderá receber pagamentos via split.',
                    '%d vendedores não configuraram seu ID de carteira Asaas e não poderão receber pagamentos via split.',
                    $count,
                    'woo-asaas'
                ),
                $count
            );

            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html($message)
            );
        }
    }

    /**
     * Show notice to vendor in their dashboard if Asaas Wallet ID is missing
     */
    public function show_vendor_wallet_missing_notice() {
        if (!dokan_is_user_seller(get_current_user_id())) {
            return;
        }

        // Verifica se o split dinâmico está ativo
        $gateway_settings = get_option('woocommerce_asaas-credit-card_settings');
        if (!isset($gateway_settings['dynamic_split_enabled']) || $gateway_settings['dynamic_split_enabled'] !== 'yes') {
            return;
        }

        $wallet_id = get_user_meta(get_current_user_id(), 'asaas_wallet_id', true);
        if (empty($wallet_id)) {
            echo '<div class="dokan-alert dokan-alert-warning">';
            esc_html_e('Você precisa configurar seu ID de carteira Asaas para receber pagamentos via split. Configure nas configurações da sua loja.', 'woo-asaas');
            echo '</div>';
        }
    }

    /**
     * Get seller wallet ID
     *
     * @param int $seller_id Seller user ID
     * @return string|false Wallet ID or false if not found
     */
    public function get_seller_wallet_id($seller_id) {
        if (!$seller_id) {
            return false;
        }

        $wallet_id = get_user_meta($seller_id, 'asaas_wallet_id', true);
        return !empty($wallet_id) ? $wallet_id : false;
    }

    /**
     * Get seller ID from order item
     * 
     * @param \WC_Order_Item $item Order item
     * @return int|false Seller ID or false if not found
     */
    public function get_seller_id_from_item($item) {
        if (!$item) {
            return false;
        }

        $product = $item->get_product();
        if (!$product) {
            return false;
        }

        if (function_exists('dokan_get_vendor_by_product')) {
            $vendor = dokan_get_vendor_by_product($product->get_id());
            if ($vendor && $vendor->get_id() > 0) {
                return $vendor->get_id();
            }
        }

        return false;
    }
}
