<?php
/**
 * Split Meta Data class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Split\Meta;

use WC_Order;

/**
 * Handles split meta data operations
 */
class Split_Meta {

    /**
     * Meta key for split data
     */
    const SPLIT_DATA_KEY = '_asaas_split_data';

    /**
     * Meta key for split status
     */
    const SPLIT_STATUS_KEY = '_asaas_split_status';

    /**
     * Meta key for marketplace commission
     */
    const MARKETPLACE_COMMISSION_KEY = '_asaas_marketplace_commission';

    /**
     * Meta key for affiliate commission
     */
    const AFFILIATE_COMMISSION_KEY = '_asaas_affiliate_commission';

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
     * Save split data to order
     *
     * @param WC_Order $order Order object
     * @param array    $split_data Split data
     */
    public function save_split_data($order, $split_data) {
        $order->update_meta_data(self::SPLIT_DATA_KEY, wp_json_encode($split_data));
        $order->save();
    }

    /**
     * Get split data from order
     *
     * @param WC_Order $order Order object
     * @return array|null Split data array or null if not found
     */
    public function get_split_data($order) {
        $data = $order->get_meta(self::SPLIT_DATA_KEY);
        return $data ? json_decode($data, true) : null;
    }

    /**
     * Update split status
     *
     * @param WC_Order $order Order object
     * @param string   $status New status
     */
    public function update_split_status($order, $status) {
        $order->update_meta_data(self::SPLIT_STATUS_KEY, $status);
        $order->save();
    }

    /**
     * Get split status
     *
     * @param WC_Order $order Order object
     * @return string|null Status or null if not found
     */
    public function get_split_status($order) {
        return $order->get_meta(self::SPLIT_STATUS_KEY);
    }

    /**
     * Save marketplace commission
     *
     * @param WC_Order $order Order object
     * @param float    $commission Commission amount
     */
    public function save_marketplace_commission($order, $commission) {
        $order->update_meta_data(self::MARKETPLACE_COMMISSION_KEY, $commission);
        $order->save();
    }

    /**
     * Get marketplace commission
     *
     * @param WC_Order $order Order object
     * @return float|null Commission amount or null if not found
     */
    public function get_marketplace_commission($order) {
        return $order->get_meta(self::MARKETPLACE_COMMISSION_KEY);
    }

    /**
     * Save affiliate commission
     *
     * @param WC_Order $order Order object
     * @param float    $commission Commission amount
     */
    public function save_affiliate_commission($order, $commission) {
        $order->update_meta_data(self::AFFILIATE_COMMISSION_KEY, $commission);
        $order->save();
    }

    /**
     * Get affiliate commission
     *
     * @param WC_Order $order Order object
     * @return float|null Commission amount or null if not found
     */
    public function get_affiliate_commission($order) {
        return $order->get_meta(self::AFFILIATE_COMMISSION_KEY);
    }

    /**
     * Add split information to order notes
     *
     * @param WC_Order $order Order object
     * @param array    $split_data Split data
     */
    public function add_split_note($order, $split_data) {
        $note = __('Split de pagamento configurado:', 'woo-asaas') . "\n";
        
        foreach ($split_data as $split) {
            $note .= sprintf(
                "- Wallet: %s, Valor: R$ %.2f\n",
                $split['walletId'],
                $split['fixedValue']
            );
        }

        $order->add_order_note($note);
    }

    /**
     * Remove all split meta data from order
     *
     * @param WC_Order $order Order object
     */
    public function clear_split_meta($order) {
        $order->delete_meta_data(self::SPLIT_DATA_KEY);
        $order->delete_meta_data(self::SPLIT_STATUS_KEY);
        $order->delete_meta_data(self::MARKETPLACE_COMMISSION_KEY);
        $order->delete_meta_data(self::AFFILIATE_COMMISSION_KEY);
        $order->save();
    }
}
