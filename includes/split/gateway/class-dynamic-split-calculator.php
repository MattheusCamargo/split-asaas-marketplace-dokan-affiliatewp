<?php
/**
 * Dynamic Split Calculator class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Split\Gateway;

use WC_Order;
use WC_Order_Item;
use Exception;
use WC_Asaas\Gateway\Gateway;
use WC_Asaas\Split\Integration\Split_Integration_Manager;
use WC_Asaas\Split\Helper\Split_Calculator_Helper;

/**
 * Handles dynamic split calculation for marketplace and affiliates
 */
class Dynamic_Split_Calculator {

    /**
     * Instance of this class
     *
     * @var self
     */
    protected static $instance = null;

    /**
     * Split Integration Manager instance
     *
     * @var Split_Integration_Manager
     */
    private $integration_manager;

    /**
     * Gateway instance
     *
     * @var Gateway
     */
    private $gateway;

    /**
     * Logger instance
     *
     * @var \WC_Logger
     */
    private $logger;

    /**
     * Is not allowed to call from outside to prevent from creating multiple instances.
     */
    private function __construct() {
        $this->integration_manager = Split_Integration_Manager::get_instance();
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
     * Initialize calculator with gateway
     *
     * @param Gateway $gateway Gateway instance
     */
    public function init(Gateway $gateway) {
        $this->gateway = $gateway;
        $this->logger = $gateway->get_logger();
    }

    /**
     * Calculate splits for an order
     *
     * @param WC_Order $order Order object
     * @return array Array of split data
     */
    public function calculate_order_splits(WC_Order $order) {
        $order_id = $order->get_id();
        
        // Valida se pode usar split dinâmico
        $can_use_split = $this->integration_manager->can_use_dynamic_split($order_id);
        if ($can_use_split !== true) {
            $this->logger->log("Pedido {$order_id}: " . $can_use_split);
            return array();
        }
        
        $splits = array();
        $marketplace_wallet_id = $this->integration_manager->get_marketplace_wallet_id();
        
        // Inicializa acumuladores
        $total_marketplace_commission = 0;
        $producer_amounts = array();
        $total_products_value = 0;
        $total_shipping = (float) $order->get_shipping_total();

        // 1. Processa itens do pedido
        foreach ($order->get_items() as $item) {
            $item_total = (float) $item->get_total();
            $total_products_value += $item_total;

            // Calcula comissão do marketplace para este item
            // Marketplace
            $marketplace_commission = Split_Calculator_Helper::calculate_marketplace_commission(
                $item_total, 
                $this->integration_manager->get_marketplace_commission_percentage()
            );

            // Afiliado - Prioridade de cálculo 
            if ($commission_type === 'percentage_after_marketplace') {
                $base_amount = $order_total - $marketplace_commission;
                $commission = ($base_amount * $percentage) / 100;
            } else {
                // Usa valor do AffiliateWP
                $commission = $referral->amount;
            }

            // Identifica o vendedor e seu valor
            $seller_data = $this->get_seller_data($item);
            if ($seller_data) {
                $value_after_commission = $item_total - $marketplace_commission;
                $wallet_id = $seller_data['wallet_id'];

                if (!isset($producer_amounts[$wallet_id])) {
                    $producer_amounts[$wallet_id] = array(
                        'amount' => 0,
                        'user_id' => $seller_data['user_id']
                    );
                }
                $producer_amounts[$wallet_id]['amount'] += $value_after_commission;
            } else {
                // Se não tem vendedor, valor vai para o marketplace
                $total_marketplace_commission += ($item_total - $marketplace_commission);
            }
        }

        // 2. Processa comissão do afiliado
        $affiliate_data = $this->get_affiliate_data($order);
        if ($affiliate_data) {
            // Deduz a comissão do afiliado proporcionalmente dos produtores
            $producer_amounts = Split_Calculator_Helper::calculate_proportional_distribution(
                $producer_amounts,
                $affiliate_data['amount']
            );
            
            $splits[] = array(
                'walletId' => $affiliate_data['wallet_id'],
                'fixedValue' => $affiliate_data['amount']
            );
        }

        // 3. Adiciona split do marketplace
        if ($total_marketplace_commission > 0) {
            $splits[] = array(
                'walletId' => $marketplace_wallet_id,
                'fixedValue' => round($total_marketplace_commission, 2)
            );
        }

        // 4. Processa frete
        if ($total_shipping > 0) {
            $shipping_recipient = $this->get_shipping_recipient($order);
            if ($shipping_recipient) {
                if (isset($producer_amounts[$shipping_recipient['wallet_id']])) {
                    $producer_amounts[$shipping_recipient['wallet_id']]['amount'] += $total_shipping;
                } else {
                    $producer_amounts[$shipping_recipient['wallet_id']] = array(
                        'amount' => $total_shipping,
                        'user_id' => $shipping_recipient['user_id']
                    );
                }
            } else {
                // Se não tem destinatário específico, frete vai para o marketplace
                $marketplace_split = &$splits[array_search($marketplace_wallet_id, array_column($splits, 'walletId'))];
                if ($marketplace_split) {
                    $marketplace_split['fixedValue'] += $total_shipping;
                } else {
                    $splits[] = array(
                        'walletId' => $marketplace_wallet_id,
                        'fixedValue' => $total_shipping
                    );
                }
            }
        }

        // 5. Adiciona splits dos produtores
        foreach ($producer_amounts as $wallet_id => $data) {
            if ($data['amount'] > 0) {
                $splits[] = array(
                    'walletId' => $wallet_id,
                    'fixedValue' => round($data['amount'], 2)
                );
            }
        }

        // 6. Normaliza e valida os splits
        $order_total = $total_products_value + $total_shipping;
        $splits = Split_Calculator_Helper::round_split_amounts($splits);
        $splits = Split_Calculator_Helper::normalize_split_amounts($splits, $order_total);
        
        $validation = Split_Calculator_Helper::validate_split_amounts($splits, $order_total);
        if ($validation !== true) {
            $this->logger->log("Pedido {$order_id}: Erro na validação dos splits - " . $validation);
            return array();
        }

        $this->logger->log("Pedido {$order_id}: Splits calculados com sucesso: " . print_r($splits, true));
        return $splits;
    }

    /**
     * Get seller data for an order item
     *
     * @param WC_Order_Item $item Order item
     * @return array|false Seller data array or false if not found
     */
    private function get_seller_data($item) {
        $seller_id = $this->integration_manager->dokan()->get_seller_id_from_item($item);
        if (!$seller_id) {
            return false;
        }

        $wallet_id = $this->integration_manager->dokan()->get_seller_wallet_id($seller_id);
        if (!$wallet_id) {
            return false;
        }

        return array(
            'user_id' => $seller_id,
            'wallet_id' => $wallet_id
        );
    }

    /**
     * Get affiliate data for an order
     *
     * @param WC_Order $order Order object
     * @return array|false Affiliate data array or false if not found
     */
    private function get_affiliate_data($order) {
        return $this->integration_manager->affiliate()->get_affiliate_commission($order->get_id());
    }

    /**
     * Deduct affiliate commission from producer amounts
     *
     * @param array &$producer_amounts Producer amounts array (passed by reference)
     * @param float $affiliate_commission Affiliate commission amount
     */
    private function deduct_affiliate_commission(&$producer_amounts, $affiliate_commission) {
        $total_amount = 0;
        foreach ($producer_amounts as $data) {
            $total_amount += $data['amount'];
        }

        if ($total_amount <= 0) {
            return;
        }

        foreach ($producer_amounts as &$data) {
            $proportion = $data['amount'] / $total_amount;
            $deduction = $affiliate_commission * $proportion;
            $data['amount'] = max(0, $data['amount'] - $deduction);
        }
    }
    
    /**
     * Validate split amounts
     *
     * @param array $splits Array of split data
     * @param float $total_amount Total amount to be split
     * @return true|string True if valid, error message otherwise
     */
    private function validate_split_amounts($splits, $total_amount) {
        $split_total = 0;
        foreach ($splits as $split) {
            // Verifica se todos têm wallet_id
            if (!isset($split['walletId']) || empty($split['walletId'])) {
                return __('Wallet ID ausente em um dos splits.', 'woo-asaas');
            }

            // Valida valor do split
            if (!isset($split['fixedValue']) || $split['fixedValue'] <= 0) {
                return sprintf(
                    __('Valor inválido para wallet %s', 'woo-asaas'),
                    $split['walletId']
                );
            }

            $split_total += $split['fixedValue'];
        }

        // Soma dos splits deve bater com total
        if (abs($split_total - $total_amount) > 0.01) {
            return sprintf(
                __('Soma dos splits (%.2f) não corresponde ao valor total (%.2f)', 'woo-asaas'),
                $split_total,
                $total_amount
            );
        }

        return true;
    }
}
