<?php
/**
 * AffiliateWP I    private function __construct() {
        // Se o AffiliateWP está ativo, adiciona os hooks necessários
        if ($this->is_affwp_active()) {
            add_action('init', function() {
                add_action('affwp_edit_affiliate_end', array($this, 'add_asaas_wallet_field'));
                add_action('affwp_update_affiliate', array($this, 'save_asaas_wallet_id'));
                add_action('admin_notices', array($this, 'show_wallet_missing_notice'));
                add_action('affwp_affiliate_dashboard_notices', array($this, 'show_affiliate_wallet_missing_notice'));
            });
        }
    }n class
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
        if ($this->is_affiliate_wp_active()) {
            add_action('affwp_edit_affiliate_end', array($this, 'add_asaas_wallet_field'));
            add_action('affwp_update_affiliate', array($this, 'save_asaas_wallet_id'));
            add_action('admin_notices', array($this, 'show_wallet_missing_notice'));
            add_action('affwp_affiliate_dashboard_notices', array($this, 'show_affiliate_wallet_missing_notice'));
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
     * @return bool True if AffiliateWP is active, false otherwise
     */
    private function is_affiliate_wp_active() {
        return class_exists('Affiliate_WP');
    }

    /**
     * Add Asaas Wallet ID field to affiliate settings
     *
     * @param \AffWP\Affiliate $affiliate Current affiliate object
     */
    public function add_asaas_wallet_field($affiliate) {
        $user_id = affwp_get_affiliate_user_id($affiliate->affiliate_id);
        $wallet_id = get_user_meta($user_id, 'asaas_wallet_id', true);
        ?>
        <tr class="form-row">
            <th scope="row">
                <label for="asaas_wallet_id"><?php esc_html_e('Asaas Wallet ID', 'woo-asaas'); ?></label>
            </th>
            <td>
                <input type="text" name="asaas_wallet_id" id="asaas_wallet_id" 
                    value="<?php echo esc_attr($wallet_id); ?>" class="regular-text" />
                <p class="description">
                    <?php esc_html_e('Informe o ID da carteira Asaas para receber os pagamentos de comissão via split.', 'woo-asaas'); ?>
                </p>
            </td>
        </tr>
        <?php
    }

    /**
     * Save Asaas Wallet ID when affiliate settings are saved
     *
     * @param int $affiliate_id Affiliate ID
     */
    public function save_asaas_wallet_id($affiliate_id) {
        if (!isset($_POST['asaas_wallet_id']) || !current_user_can('manage_affiliates')) {
            return;
        }

        $user_id = affwp_get_affiliate_user_id($affiliate_id);
        if ($user_id) {
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

        // Conta afiliados ativos sem wallet ID
        $affiliates = affiliate_wp()->affiliates->get_affiliates(array(
            'status' => 'active',
            'number' => -1
        ));

        $count = 0;
        if ($affiliates) {
            foreach ($affiliates as $affiliate) {
                $user_id = affwp_get_affiliate_user_id($affiliate->ID);
                if ($user_id) {
                    $wallet_id = get_user_meta($user_id, 'asaas_wallet_id', true);
                    if (empty($wallet_id)) {
                        $count++;
                    }
                }
            }
        }

        if ($count > 0) {
            $message = sprintf(
                /* translators: %d: number of affiliates without wallet */
                _n(
                    '%d afiliado ativo não configurou seu ID de carteira Asaas e não poderá receber comissões via split.',
                    '%d afiliados ativos não configuraram seu ID de carteira Asaas e não poderão receber comissões via split.',
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
     * Show notice to affiliate in their dashboard if Asaas Wallet ID is missing
     */
    public function show_affiliate_wallet_missing_notice() {
        if (!affwp_is_affiliate()) {
            return;
        }

        // Verifica se o split dinâmico está ativo
        $gateway_settings = get_option('woocommerce_asaas-credit-card_settings');
        if (!isset($gateway_settings['dynamic_split_enabled']) || $gateway_settings['dynamic_split_enabled'] !== 'yes') {
            return;
        }

        $affiliate_id = affwp_get_affiliate_id();
        $user_id = affwp_get_affiliate_user_id($affiliate_id);
        
        if ($user_id) {
            $wallet_id = get_user_meta($user_id, 'asaas_wallet_id', true);
            if (empty($wallet_id)) {
                affwp_show_message(
                    __('Você precisa configurar seu ID de carteira Asaas para receber comissões via split. Entre em contato com o administrador.', 'woo-asaas'),
                    'error'
                );
            }
        }
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

    /**
     * Get affiliate commission amount for order
     *
     * @param int $order_id Order ID
     * @return array|false Array with affiliate info or false if not found
     */
    public function get_affiliate_commission($order_id) {
        if (!function_exists('affwp_get_referral_by')) {
            return false;
        }

        $referral = affwp_get_referral_by('reference', $order_id);
        if (!$referral || $referral->status !== 'unpaid') {
            return false;
        }

        $wallet_id = $this->get_affiliate_wallet_id($referral->affiliate_id);
        if (!$wallet_id) {
            return false;
        }

        return array(
            'affiliate_id' => $referral->affiliate_id,
            'amount' => $referral->amount,
            'wallet_id' => $wallet_id
        );
    }
}
