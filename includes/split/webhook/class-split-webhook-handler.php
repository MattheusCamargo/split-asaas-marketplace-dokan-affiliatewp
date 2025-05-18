<?php
/**
 * Split Webhook Handler class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Split\Webhook;

use WC_Asaas\Split\Meta\Split_Meta;
use WC_Asaas\Split\Database\Split_History;
use WC_Asaas\Webhook\Webhook;

/**
 * Handles split webhook notifications from Asaas
 */
class Split_Webhook_Handler {

    /**
     * Instance of this class
     *
     * @var self
     */
    protected static $instance = null;

    /**
     * Split meta instance
     *
     * @var Split_Meta
     */
    private $split_meta;

    /**
     * Split history instance
     *
     * @var Split_History
     */
    private $split_history;

    /**
     * Constructor
     */
    private function __construct() {
        $this->split_meta = Split_Meta::get_instance();
        $this->split_history = Split_History::get_instance();

        add_filter('woocommerce_asaas_webhook_payment_received', array($this, 'handle_payment_received'), 10, 2);
        add_filter('woocommerce_asaas_webhook_payment_confirmed', array($this, 'handle_payment_confirmed'), 10, 2);
        add_filter('woocommerce_asaas_webhook_payment_refunded', array($this, 'handle_payment_refunded'), 10, 2);
        add_filter('woocommerce_asaas_webhook_transfer_received', array($this, 'handle_transfer_received'), 10, 2);
        add_action('woocommerce_asaas_webhook_transfer_failed', array($this, 'handle_transfer_failed'), 10, 2);
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
     * Handle payment received webhook
     *
     * @param array $payment Payment data from webhook
     * @param int   $order_id WooCommerce order ID
     * @return array Payment data
     */
    public function handle_payment_received($payment, $order_id) {
        if (!isset($payment['split'])) {
            return $payment;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return $payment;
        }

        // Atualiza status do split para 'processing'
        $this->split_meta->update_split_status($order, 'processing');
        $this->split_history->update_status($payment['id'], 'processing');

        $order->add_order_note(
            __('Split de pagamento em processamento.', 'woo-asaas')
        );

        return $payment;
    }

    /**
     * Handle payment confirmed webhook
     *
     * @param array $payment Payment data from webhook
     * @param int   $order_id WooCommerce order ID
     * @return array Payment data
     */
    public function handle_payment_confirmed($payment, $order_id) {
        if (!isset($payment['split'])) {
            return $payment;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return $payment;
        }

        // Atualiza status do split para 'confirmed'
        $this->split_meta->update_split_status($order, 'confirmed');
        $this->split_history->update_status($payment['id'], 'confirmed');

        $order->add_order_note(
            __('Split de pagamento confirmado. Aguardando transferências.', 'woo-asaas')
        );

        return $payment;
    }

    /**
     * Handle payment refunded webhook
     *
     * @param array $payment Payment data from webhook
     * @param int   $order_id WooCommerce order ID
     * @return array Payment data
     */
    public function handle_payment_refunded($payment, $order_id) {
        if (!isset($payment['split'])) {
            return $payment;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return $payment;
        }

        // Atualiza status do split para 'refunded'
        $this->split_meta->update_split_status($order, 'refunded');
        $this->split_history->update_status($payment['id'], 'refunded');

        $order->add_order_note(
            __('Split de pagamento estornado.', 'woo-asaas')
        );

        return $payment;
    }

    /**
     * Handle transfer received webhook
     *
     * @param array $transfer Transfer data from webhook
     * @param array $payment Payment data associated with transfer
     */
    public function handle_transfer_received($transfer, $payment) {
        if (!isset($payment['id'])) {
            return;
        }

        $split = $this->split_history->get_split_by_payment($payment['id']);
        if (!$split) {
            return;
        }

        $order = wc_get_order($split['order_id']);
        if (!$order) {
            return;
        }

        $wallet_id = $transfer['walletId'];
        $amount = $transfer['value'];

        $order->add_order_note(
            sprintf(
                /* translators: 1: wallet ID 2: transfer amount */
                __('Transferência do split realizada para wallet %1$s: R$ %2$s', 'woo-asaas'),
                $wallet_id,
                number_format($amount, 2, ',', '.')
            )
        );

        // Se todas as transferências foram concluídas, atualiza o status para 'completed'
        $split_data = json_decode($split['split_data'], true);
        $all_transferred = true;

        foreach ($split_data as $split_item) {
            if ($split_item['walletId'] === $wallet_id) {
                if (abs($split_item['fixedValue'] - $amount) > 0.01) {
                    $all_transferred = false;
                    break;
                }
            }
        }

        if ($all_transferred) {
            $this->split_meta->update_split_status($order, 'completed');
            $this->split_history->update_status($payment['id'], 'completed');
            
            $order->add_order_note(
                __('Todas as transferências do split foram concluídas.', 'woo-asaas')
            );
        }
    }

    /**
     * Handle transfer failed webhook
     *
     * @param array $transfer Transfer data from webhook
     * @param array $payment Payment data associated with transfer
     */
    public function handle_transfer_failed($transfer, $payment) {
        if (!isset($payment['id'])) {
            return;
        }

        $split = $this->split_history->get_split_by_payment($payment['id']);
        if (!$split) {
            return;
        }

        $order = wc_get_order($split['order_id']);
        if (!$order) {
            return;
        }

        $wallet_id = $transfer['walletId'];
        $error_message = isset($transfer['error']) ? $transfer['error'] : __('Erro desconhecido', 'woo-asaas');

        $this->split_meta->update_split_status($order, 'failed');
        $this->split_history->update_status($payment['id'], 'failed');

        $order->add_order_note(
            sprintf(
                /* translators: 1: wallet ID 2: error message */
                __('Falha na transferência do split para wallet %1$s: %2$s', 'woo-asaas'),
                $wallet_id,
                $error_message
            )
        );
    }
}
