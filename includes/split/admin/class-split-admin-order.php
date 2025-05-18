<?php
/**
 * Split Admin Order class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Split\Admin;

use WC_Order;
use WC_Asaas\Split\Meta\Split_Meta;
use WC_Asaas\Split\Database\Split_History;

/**
 * Handles split information display in admin order page
 */
class Split_Admin_Order {

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

        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_split_info'), 10, 1);
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
     * Add split meta box to order page
     */
    public function add_meta_box() {
        add_meta_box(
            'wc_asaas_split_info',
            __('Informações do Split Asaas', 'woo-asaas'),
            array($this, 'render_meta_box'),
            'shop_order',
            'side',
            'default'
        );
    }

    /**
     * Render split meta box content
     *
     * @param WC_Order $order Order object
     */
    public function render_meta_box($order) {
        $order = wc_get_order($order->ID);
        $split_data = $this->split_meta->get_split_data($order);
        $split_status = $this->split_meta->get_split_status($order);
        $marketplace_commission = $this->split_meta->get_marketplace_commission($order);
        $affiliate_commission = $this->split_meta->get_affiliate_commission($order);

        include dirname(__FILE__) . '/views/html-order-split-meta-box.php';
    }

    /**
     * Display split information in order details
     *
     * @param WC_Order $order Order object
     */
    public function display_split_info($order) {
        $split_data = $this->split_meta->get_split_data($order);
        if (!$split_data) {
            return;
        }

        $split_status = $this->split_meta->get_split_status($order);
        $marketplace_commission = $this->split_meta->get_marketplace_commission($order);
        $affiliate_commission = $this->split_meta->get_affiliate_commission($order);

        include dirname(__FILE__) . '/views/html-order-split-info.php';
    }
}
