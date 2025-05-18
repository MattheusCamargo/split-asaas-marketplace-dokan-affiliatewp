<?php
/**
 * Payment Split class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Split\Gateway;

use Exception;
use WC_Order;
use WC_Asaas\Gateway\Gateway;
use WC_Asaas\Split\Helper\Values_Formater_Helper;
use WC_Asaas\Split\Integration\Split_Integration_Manager;
use WC_Asaas\Split\Meta\Split_Meta;
use WC_Asaas\Split\Database\Split_History;

/**
 * Handle init payment splits.
 */
class Payment_Split {


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
	}

	/**
	 * Prevent the instance from being cloned.
	 */
	private function __clone() {
	}

	/**
	 * Prevent from being unserialized.
	 *
	 * @throws Exception If create a second instance of it.
	 */
	public function __wakeup() {
		throw new Exception( esc_html__( 'Cannot unserialize singleton', 'woo-asaas' ) );
	}

	/**
	 * Return an instance of this class
	 *
	 * @return self A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Handle with Gateway payment split.
	 *
	 * @param array    $payment_data Payment data.
	 * @param WC_Order $wc_order WC Order object.
	 * @param Gateway  $gateway Current payment gateway.
	 * @return array Updated payment data
	 */
	public function split_payment_data( array $payment_data, WC_Order $wc_order, Gateway $gateway ) {
		// Verifica se o split dinâmico está ativo
		$integration_manager = Split_Integration_Manager::get_instance();
		
		if ( $integration_manager->is_dynamic_split_enabled() ) {
			// Usa o calculador dinâmico
			$calculator = Dynamic_Split_Calculator::get_instance();
			$calculator->init( $gateway );
			
			$split_data = $calculator->calculate_order_splits( $wc_order );
			if ( ! empty( $split_data ) ) {
				$payment_data['split'] = $split_data;
				
				// Salva os dados do split
				$meta = Split_Meta::get_instance();
				$meta->save_split_data( $wc_order, $split_data );
				$meta->update_split_status( $wc_order, 'pending' );
				
				// Se tiver payment_id, salva no histórico
				if ( isset( $payment_data['payment_id'] ) ) {
					$history = Split_History::get_instance();
					$history->record_split( array(
						'order_id'              => $wc_order->get_id(),
						'payment_id'            => $payment_data['payment_id'],
						'split_data'            => wp_json_encode( $split_data ),
						'status'                => 'pending',
						'total_amount'          => $wc_order->get_total(),
						'marketplace_commission'=> $calculator->get_total_marketplace_commission(),
						'affiliate_commission'  => $calculator->get_total_affiliate_commission()
					) );
				}

				$wc_order->add_order_note( __( 'Split dinâmico calculado e aplicado ao pagamento.', 'woo-asaas' ) );
			}
			
			return $payment_data;
		}

		// Se o split dinâmico não está ativo, usa a lógica original
		$wallets = $gateway->settings['split_wallet'];
		if ( null === $wallets ) {
			return $payment_data;
		}

		$wc_order->add_order_note( $this->order_notes( $wallets ) );
		$this->add_split_log( $gateway, $wallets );

		$split_data = $this->split_api_format( $wallets );
		if ( ! empty( $split_data ) ) {
			$payment_data['split'] = $split_data;
			
			// Salva os dados do split manual também
			$meta = Split_Meta::get_instance();
			$meta->save_split_data( $wc_order, $split_data );
			$meta->update_split_status( $wc_order, 'pending' );
		}

		return $payment_data;
	}

	/**
	 * Add split order notes.
	 *
	 * @param array $wallets The array of wallets.
	 * @return int|string The order note.
	 */
	private function order_notes( array $wallets ) {
		$order_note = ( new Values_Formater_Helper() )->convert_into_order_note( $wallets );
		return $order_note;
	}

	/**
	 * Adds the split log for the given gateway and wallets.
	 *
	 * @param Gateway $gateway The gateway object.
	 * @param array   $wallets An array of wallets.
	 */
	private function add_split_log( Gateway $gateway, array $wallets ) {
		$messages = ( new Values_Formater_Helper() )->convert_into_log_format( $wallets );
		if ( empty( $messages ) ) {
			return;
		}
		foreach ( $messages as $message ) {
			$gateway->get_logger()->log( $message );
		}
	}

	/**
	 * Convert the given array of wallets into the API format used by Asaas.
	 *
	 * @param array $wallets The array of wallets to be converted.
	 * @return array The formatted wallets in the API format.
	 */
	private function split_api_format( array $wallets ) {
		$formatted_wallets = ( new Values_Formater_Helper() )->convert_into_wallet_api_format( $wallets );
		return $formatted_wallets;
	}
}
