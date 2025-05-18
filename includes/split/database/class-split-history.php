<?php
/**
 * Split History Database class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Split\Database;

/**
 * Handles split history database operations
 */
class Split_History {

    /**
     * Table name
     *
     * @var string
     */
    private $table_name;

    /**
     * Instance of this class
     *
     * @var self
     */
    protected static $instance = null;

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'asaas_split_history';
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
     * Create database table
     */
    public function create_table() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            payment_id varchar(100) NOT NULL,
            split_data longtext NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            status varchar(50) NOT NULL DEFAULT 'pending',
            total_amount decimal(10,2) NOT NULL,
            marketplace_commission decimal(10,2) NOT NULL,
            affiliate_commission decimal(10,2) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY order_id (order_id),
            KEY payment_id (payment_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Record a new split operation
     *
     * @param array $data Split data
     * @return int|false The number of rows inserted, or false on error
     */
    public function record_split($data) {
        global $wpdb;

        $data['created_at'] = current_time('mysql');
        
        return $wpdb->insert(
            $this->table_name,
            $data,
            array(
                '%d',  // order_id
                '%s',  // payment_id
                '%s',  // split_data (JSON)
                '%s',  // created_at
                '%s',  // status
                '%f',  // total_amount
                '%f',  // marketplace_commission
                '%f'   // affiliate_commission
            )
        );
    }

    /**
     * Update split status
     *
     * @param string $payment_id Asaas payment ID
     * @param string $status New status
     * @return int|false The number of rows updated, or false on error
     */
    public function update_status($payment_id, $status) {
        global $wpdb;

        return $wpdb->update(
            $this->table_name,
            array('status' => $status),
            array('payment_id' => $payment_id),
            array('%s'),
            array('%s')
        );
    }

    /**
     * Get split history for an order
     *
     * @param int $order_id Order ID
     * @return array|null Array of split history or null if not found
     */
    public function get_order_splits($order_id) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE order_id = %d ORDER BY created_at DESC",
                $order_id
            ),
            ARRAY_A
        );
    }

    /**
     * Get split by payment ID
     *
     * @param string $payment_id Asaas payment ID
     * @return array|null Split data or null if not found
     */
    public function get_split_by_payment($payment_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE payment_id = %s",
                $payment_id
            ),
            ARRAY_A
        );
    }
}
