<?php
/**
 * Split Integrations Manager class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Split\Integration;

use Exception;

/**
 * Manages marketplace and affiliate integrations for split payments
 */
class Split_Integration_Manager {

    /**
     * Instance of this class
     *
     * @var self
     */
    protected static $instance = null;

    /**
     * Dokan integration instance
     *
     * @var Dokan_Integration
     */
    private $dokan;

    /**
     * AffiliateWP integration instance
     *
     * @var AffiliateWP_Integration
     */
    private $affiliate;

    /**
     * Is not allowed to call from outside to prevent from creating multiple instances.
     */
    private function __construct() {
        $this->init_integrations();
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
     * Initialize integrations
     */
    private function init_integrations() {
        $this->dokan = Dokan_Integration::get_instance();
        $this->affiliate = AffiliateWP_Integration::get_instance();
    }

    /**
     * Get Dokan integration instance
     *
     * @return Dokan_Integration
     */
    public function dokan() {
        return $this->dokan;
    }

    /**
     * Get AffiliateWP integration instance
     *
     * @return AffiliateWP_Integration
     */
    public function affiliate() {
        return $this->affiliate;
    }

    /**
     * Check if any integration is active
     *
     * @return bool True if any integration is active
     */
    public function has_active_integrations() {
        return class_exists('WeDevs_Dokan') || 
               class_exists('Dokan_Pro') || 
               class_exists('Affiliate_WP');
    }

    /**
     * Check if dynamic split is enabled in settings
     *
     * @return bool True if dynamic split is enabled
     */
    public function is_dynamic_split_enabled() {
        $gateway_settings = get_option('woocommerce_asaas-credit-card_settings');
        return isset($gateway_settings['dynamic_split_enabled']) && 
               $gateway_settings['dynamic_split_enabled'] === 'yes';
    }

    /**
     * Check if dynamic split is enabled and properly configured
     *
     * @return bool True if dynamic split is enabled and configured
     */
    public function is_dynamic_split_enabled_and_configured() {
        if (!$this->is_dynamic_split_enabled()) {
            return false;
        }

        $marketplace_wallet = $this->get_marketplace_wallet_id();
        if (empty($marketplace_wallet)) {
            return false;
        }

        return true;
    }

    /**
     * Validate if an order can use dynamic split
     *
     * @param int $order_id WooCommerce order ID
     * @return bool|string True if can use split, error message if cannot
     */
    public function can_use_dynamic_split($order_id) {
        if (!$this->is_dynamic_split_enabled_and_configured()) {
            return __('Split dinâmico não está ativo ou configurado corretamente.', 'woo-asaas');
        }

        // Verifica se é uma assinatura
        if (function_exists('wcs_get_subscription')) {
            $subscription = wcs_get_subscription($order_id);
            if ($subscription) {
                return __('Split dinâmico não é suportado em assinaturas.', 'woo-asaas');
            }
        }

        return true;
    }

    /**
     * Get marketplace wallet ID from settings
     *
     * @return string|false Marketplace wallet ID or false if not set
     */
    public function get_marketplace_wallet_id() {
        $gateway_settings = get_option('woocommerce_asaas-credit-card_settings');
        return !empty($gateway_settings['marketplace_asaas_wallet_id']) ? 
               $gateway_settings['marketplace_asaas_wallet_id'] : 
               false;
    }

    /**
     * Get marketplace commission percentage from settings
     *
     * @return float Marketplace commission percentage
     */
    public function get_marketplace_commission_percentage() {
        $gateway_settings = get_option('woocommerce_asaas-credit-card_settings');
        return isset($gateway_settings['marketplace_commission_percentage']) ? 
               (float) $gateway_settings['marketplace_commission_percentage'] : 
               0;
    }

    /**
     * Get affiliate commission calculation method
     *
     * @return string affiliatewp_value or percentage_after_marketplace
     */
    public function get_affiliate_commission_type() {
        $gateway_settings = get_option('woocommerce_asaas-credit-card_settings');
        return isset($gateway_settings['affiliate_commission_priority']) ? 
               $gateway_settings['affiliate_commission_priority'] : 
               'affiliatewp_value';
    }

    /**
     * Get default affiliate commission percentage
     *
     * @return float Default affiliate commission percentage
     */
    public function get_default_affiliate_commission_percentage() {
        $gateway_settings = get_option('woocommerce_asaas-credit-card_settings');
        return isset($gateway_settings['default_affiliate_commission_percentage']) ? 
               (float) $gateway_settings['default_affiliate_commission_percentage'] : 
               0;
    }

    /**
     * Calculate affiliate commission for an order
     *
     * @param float $order_total Order total
     * @param float $marketplace_commission Marketplace commission amount
     * @return float Commission amount
     */
    public function calculate_affiliate_commission($order_total, $marketplace_commission) {
        $commission_type = $this->get_affiliate_commission_type();
        
        if ($commission_type === 'percentage_after_marketplace') {
            $base_amount = $order_total - $marketplace_commission;
            $percentage = $this->get_default_affiliate_commission_percentage();
            return ($base_amount * $percentage) / 100;
        }
        
        return 0; // Caso AffiliateWP_value, o valor vem direto do AffiliateWP
    }
}
