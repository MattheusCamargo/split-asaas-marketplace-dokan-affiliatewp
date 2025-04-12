<?php
/**
 * File for class Webhook Ajax
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Webhook;

use stdClass;

use WC_Asaas\Api\Api;
use WC_Asaas\Api\Response\Collection_Response;
use WC_Asaas\Api\Response\Error_Response;
use WC_Asaas\Api\Response\Response;
use WC_Asaas\Gateway\Gateway;
use WC_Asaas\Webhook\Meta\Webhook_Meta_Status;
use WC_Asaas\Helper\Webhook_Helper;
use WC_Asaas\WC_Asaas;
use WP_Error;

/**
 * Webhook Ajax
 */
class Webhook_Ajax {

	/**
	 * The gateway that will call the API
	 *
	 * @var Gateway
	 */
	protected $gateway;

	/**
	 * Helper for webhook
	 *
	 * @var Webhook_Helper
	 */
	protected $helper;

	/**
	 * Asaas API wrapper.
	 *
	 * @var Api
	 */
	protected $api;

	/**
	 * Attributes of the webhook settings
	 *
	 * @var Webhook_Setting_Data
	 */
	protected $data;

	/**
	 * Manage webhook status meta
	 *
	 * @var Webhook_Meta_Status
	 */
	protected $status;

	/**
	 * Initialize the object
	 */
	public function __construct() {
		$this->gateway = WC_Asaas::get_instance()->get_gateway_by_id( 'asaas-ticket' );
		$this->api     = new Api( $this->gateway );
		$this->data    = new Webhook_Setting_Data();
		$this->status  = new Webhook_Meta_Status();
		$this->helper  = new Webhook_Helper();

		add_action( 'wp_ajax_check_webhook_status', array( $this, 'check_webhook_status' ) );
		add_action( 'wp_ajax_check_webhook_setting', array( $this, 'check_webhook_setting' ) );
		add_action( 'wp_ajax_update_existing_webhook_queue', array( $this, 'update_existing_webhook_queue' ) );
		add_action( 'wp_ajax_update_existing_webhook_email', array( $this, 'update_existing_webhook_email' ) );
	}

	/**
	 * Check existing webhook on status settings page
	 */
	public function check_webhook_status() {
		$gateway = $this->gateway;
		$api_key = $gateway->get_api_key();

		if ( '' === $api_key ) {
			$this->status->set_status( false );
			wp_send_json_success( false );
		}

		$response_data = $this->check_and_create_webhook_settings();

		if ( is_array( $response_data ) && $response_data ) {
			wp_send_json_success( $response_data );
		} else {
			wp_send_json_error( $response_data );
		}
	}

	/**
	 * Check existing webhook on gateway settings page
	 */
	public function check_webhook_setting() {
		if ( isset( $_POST['_nonce'] ) && ! wp_verify_nonce( sanitize_key( $_POST['_nonce'] ), 'woo-asaas-admin-nonce' ) ) {
			wp_send_json_error( array( 'error' => __( 'Nonce verification failed', 'woo-asaas' ) ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'error' => __( 'Permission denied', 'woo-asaas' ) ) );
		}

		$params = array(
			'url'     => isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '',
			'api_key' => isset( $_POST['api_key'] ) ? wp_kses_data( wp_unslash( $_POST['api_key'] ) ) : '',
		);

		if ( '' === $params['api_key'] ) {
			$this->status->set_status( false );
			wp_send_json_success( false );
		}

		$response_data = $this->check_and_create_webhook_settings( $params );

		if ( is_array( $response_data ) && $response_data ) {
			wp_send_json_success( $response_data );
		}

		wp_send_json_error( $response_data );
	}

	/**
	 * Update webhook setting queue
	 */
	public function update_existing_webhook_queue() {
		$data = $this->retrieve_single_webhook();

		if ( ! property_exists( $data, 'id' ) ) {
			wp_send_json_error( $data );
		}

		$auth_token = $this->helper->generate_random_token();
		$this->data->set_access_token( $auth_token );

		$data_to_update = array(
			'enabled'     => true,
			'interrupted' => false,
			'authToken'   => $auth_token,
		);

		$response = $this->api->webhooks()->update( $data->id, $data_to_update );

		if ( ! $response instanceof Error_Response ) {
			$this->status->set_status( true, true );
			wp_send_json_success( $response->get_json(), $response->code );
		}

		$this->status->set_status();
		wp_send_json_error( $response->get_json(), $response->code );
	}

	/**
	 * Update webhook setting email
	 */
	public function update_existing_webhook_email() {
		$gateway = $this->gateway;
		$data    = $this->retrieve_single_webhook();
		if ( null === $data ) {
			wp_send_json_error( new WP_Error( 'webhook-url-not-found', __( 'The webhook URL of this website was not found on Asaas webhooks list.', 'woo-asaas' ) ), 404 );
			return;
		}

		$email_notification = $gateway->settings['email_notification'];
		if ( is_email( $email_notification ) ) {
			$this->data->set_email( $email_notification );
		}

		$data_to_update = array(
			'email' => $this->data->email,
		);

		$response = $this->api->webhooks()->update( $data->id, $data_to_update );

		if ( $response instanceof Error_Response ) {
			$this->send_json_webhook_errors( $response );
			return;
		}
		wp_send_json_success();
	}

	/**
	 * Check and create webhook settings
	 *
	 * @param array $params The webhook params.
	 * @return array|Response The response data.
	 */
	private function check_and_create_webhook_settings( array $params = array() ) {
		$gateway = $this->gateway;
		$data    = array();

		if ( '' === $params['url'] && '' === $params['api_key'] ) {
			return $data;
		}

		add_filter(
			'woocommerce_asaas_request_url', function ( $endpoint ) use ( $params ) {
				if ( $params['url'] ) {
					return $params['url'];
				}

				return $endpoint;
			}
		);

		add_filter(
			'woocommerce_asaas_request_api_key', function ( $api_key ) use ( $params ) {
				if ( $params['api_key'] ) {
					return $params['api_key'];
				}

				return $api_key;
			}
		);

		$response = $this->api->webhooks()->exists( $this->data->url );

		if ( $response instanceof Error_Response ) {
			$this->status->set_status( false );

			return $response;
		}
		$email_notification = $gateway->settings['email_notification'];
		if ( is_email( $email_notification ) ) {
			$this->data->set_email( $email_notification );
		}

		if ( $response->json->data ) {
			$enabled     = $response->json->data[0]->enabled;
			$interrupted = $response->json->data[0]->interrupted;
			$auth_token  = $response->json->data[0]->authToken;

			$access_token = $gateway->settings['webhook_access_token'];

			if ( '' !== $auth_token && $access_token === $auth_token && $enabled && ! $interrupted ) {
				$this->status->set_status( true, true );
			} else {
				$response->json->data[0]->authToken = null;
				$this->status->set_status();
			}

			return $response->json->data;
		}

		$auth_token = $this->helper->generate_random_token();
		$this->data->set_access_token( $auth_token );

		$data_created = $this->api->webhooks()->create( $this->data->get_request_data() );
		$data[]       = $data_created->get_json();

		if ( $data ) {
			return $data;
		}

		return $response;
	}

	/**
	 * Retrieves webhook setting data.
	 *
	 * @return stdClass|null The webhook setting data or null if object not found.
	 */
	private function retrieve_single_webhook() {
		$response = $this->api->webhooks()->exists( $this->data->url );

		if ( ! $response instanceof Error_Response && ! empty( $response->json->data ) ) {
			return reset( $response->json->data );
		}

		return null;
	}

	/**
	 * Send a JSON error with the specific webhook response errors.
	 *
	 * @param Error_Response $response  The webhook response.
	 *
	 * @return void
	 */
	private function send_json_webhook_errors( Error_Response $response ) {
		if ( $response->get_errors()->has_errors() ) {
			wp_send_json_error( $response->get_errors(), $response->code );
		}
		wp_send_json_error( new WP_Error( 'unexpected-webhook-settings-error', __( 'An unexpected error occurred while processing the webhook settings update.', 'woo-asaas' ) ),
		400 );
	}
}
