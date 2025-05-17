<?php
/**
 * AffiliateWP Integration class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Split\Integration;

use Exception;

/**
 * Handle AffiliateWP integration for split payments
 */
class AffiliateWP_Integration {

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
        // Se o AffiliateWP está ativo, adiciona os hooks necessários
        if ($this->is_affwp_active()) {
            add_action('affwp_edit_affiliate_end', array($this, 'add_asaas_wallet_field'));
            add_action('affwp_update_affiliate', array($this, 'save_asaas_wallet_id'));
            add_action('admin_notices', array($this, 'show_wallet_missing_notice'));
            add_action('affwp_affiliate_dashboard_notices', array($this, 'show_affiliate_wallet_missing_notice'));
            
            // Hooks para salvar o ID da carteira quando o afiliado se registra
            add_action('affwp_register_user', array($this, 'save_wallet_id_on_registration'), 10, 3);
            
            // Hook para mostrar o campo no formulário de registro
            add_action('affwp_register_fields_before_submit', array($this, 'add_wallet_field_to_registration'));
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
     * Check if AffiliateWP is active
     * 
     * @return bool True if AffiliateWP is active
     */
    private function is_affwp_active() {
        return class_exists('Affiliate_WP');
    }

    /**
     * Add Asaas Wallet ID field to affiliate edit screen
     *
     * @param \AffWP\Affiliate $affiliate Current affiliate object
     */
    public function add_asaas_wallet_field($affiliate) {
        $user_id = affwp_get_affiliate_user_id($affiliate->affiliate_id);
        $wallet_id = get_user_meta($user_id, 'asaas_wallet_id', true);
        ?>
        <tr class="form-row form-required">
            <th scope="row">
                <label for="asaas_wallet_id"><?php esc_html_e('Asaas Wallet ID', 'woo-asaas'); ?></label>
            </th>
            <td>
                <input type="text" name="asaas_wallet_id" id="asaas_wallet_id" 
                       value="<?php echo esc_attr($wallet_id); ?>" class="regular-text" />
                <p class="description">
                    <?php esc_html_e('ID da carteira Asaas para recebimento das comissões via split.', 'woo-asaas'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Add Asaas Wallet ID field to affiliate registration form
     */
    public function add_wallet_field_to_registration() {
        ?>
        <p>
            <label for="affwp-asaas-wallet-id"><?php esc_html_e('Asaas Wallet ID', 'woo-asaas'); ?></label>
            <input type="text" name="asaas_wallet_id" id="affwp-asaas-wallet-id" class="required" />
            <span class="description">
                <?php esc_html_e('Seu ID de carteira Asaas para receber comissões via split de pagamento.', 'woo-asaas'); ?>
            </span>
        </p>
        <?php
    }

    /**
     * Save Asaas Wallet ID when affiliate is updated
     *
     * @param int $affiliate_id Affiliate ID
     */
    public function save_asaas_wallet_id($affiliate_id) {
        if (!current_user_can('manage_affiliates') && !current_user_can('manage_affiliate')) {
            return;
        }

        if (isset($_POST['asaas_wallet_id'])) {
            $user_id = affwp_get_affiliate_user_id($affiliate_id);
            update_user_meta(
                $user_id,
                'asaas_wallet_id',
                sanitize_text_field($_POST['asaas_wallet_id'])
            );
        }
    }

    /**
     * Save Wallet ID when affiliate registers
     *
     * @param int   $affiliate_id The affiliate ID
     * @param array $args Registration arguments
     * @param int   $user_id The user ID
     */
    public function save_wallet_id_on_registration($affiliate_id, $args, $user_id) {
        if (isset($_POST['asaas_wallet_id'])) {
            update_user_meta(
                $user_id,
                'asaas_wallet_id',
                sanitize_text_field($_POST['asaas_wallet_id'])
            );
        }
    }

    /**
     * Show notice to admin if affiliates are missing Asaas Wallet ID
     */
    public function show_wallet_missing_notice() {
        if (!current_user_can('manage_affiliates')) {
            return;
        }

        // Verifica se o split dinâmico está ativo
        $gateway_settings = get_option('woocommerce_asaas-credit-card_settings');
        if (!isset($gateway_settings['dynamic_split_enabled']) || $gateway_settings['dynamic_split_enabled'] !== 'yes') {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'affiliate_wp_affiliates';
        
        // Busca afiliados ativos
        $affiliates = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'active'");
        $without_wallet = 0;

        foreach ($affiliates as $affiliate) {
            $user_id = affwp_get_affiliate_user_id($affiliate->affiliate_id);
            $wallet_id = get_user_meta($user_id, 'asaas_wallet_id', true);
            
            if (empty($wallet_id)) {
                $without_wallet++;
            }
        }

        if ($without_wallet > 0) {
            $message = sprintf(
                /* translators: %d: number of affiliates without wallet */
                _n(
                    '%d afiliado ativo não possui ID de carteira Asaas configurado e não poderá receber comissões via split.',
                    '%d afiliados ativos não possuem ID de carteira Asaas configurado e não poderão receber comissões via split.',
                    $without_wallet,
                    'woo-asaas'
                ),
                $without_wallet
            );

            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                esc_html($message)
            );
        }
    }

    /**
     * Show notice to affiliate in their dashboard if Asaas Wallet ID is missing
     */
    public function show_affiliate_wallet_missing_notice() {
        $affiliate_id = affwp_get_affiliate_id();
        if (!$affiliate_id) {
            return;
        }

        // Verifica se o split dinâmico está ativo
        $gateway_settings = get_option('woocommerce_asaas-credit-card_settings');
        if (!isset($gateway_settings['dynamic_split_enabled']) || $gateway_settings['dynamic_split_enabled'] !== 'yes') {
            return;
        }

        $user_id = affwp_get_affiliate_user_id($affiliate_id);
        $wallet_id = get_user_meta($user_id, 'asaas_wallet_id', true);

        if (empty($wallet_id)) {
            affwp_print_notice('warning', 
                __('Você precisa configurar seu ID de carteira Asaas para receber comissões via split. Configure em seu perfil de afiliado.', 'woo-asaas')
            );
        }
    }

    /**
     * Get affiliate commission data for an order
     *
     * @param int $order_id WooCommerce order ID
     * @return array|false Commission data or false if not applicable
     */
    public function get_affiliate_commission($order_id) {
        if (!function_exists('affwp_get_referral_by')) {
            return false;
        }

        $referral = affwp_get_referral_by('reference', $order_id);
        if (!$referral || $referral->status !== 'unpaid') {
            return false;
        }

        $affiliate_id = $referral->affiliate_id;
        $user_id = affwp_get_affiliate_user_id($affiliate_id);
        $wallet_id = get_user_meta($user_id, 'asaas_wallet_id', true);

        if (empty($wallet_id)) {
            return false;
        }

        return array(
            'wallet_id' => $wallet_id,
            'user_id' => $user_id,
            'amount' => (float) $referral->amount
        );
    }

    /**
     * Get affiliate wallet ID
     *
     * @param int $affiliate_id Affiliate ID
     * @return string|false Wallet ID or false if not found
     */
    public function get_affiliate_wallet_id($affiliate_id) {
        if (!$affiliate_id) {
            return false;
        }

        $user_id = affwp_get_affiliate_user_id($affiliate_id);
        if (!$user_id) {
            return false;
        }

        $wallet_id = get_user_meta($user_id, 'asaas_wallet_id', true);
        return !empty($wallet_id) ? $wallet_id : false;
    }
}
