<?php
/**
 * Webhook Setting Data
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Webhook;

use WC_Asaas\WC_Asaas;
use WC_Asaas\Gateway\Gateway;
use WC_Asaas\Webhook\Webhook;
use WC_Asaas\Helper\Webhook_Helper;

/**
 * Webhook Setting Data
 */
class Webhook_Setting_Data {
	/**
	 * The gateway to load the settings
	 *
	 * @var Gateway
	 */
	protected $gateway;

	/**
	 * The name of the webhook.
	 *
	 * @var string $name
	 */
	public $name;

	/**
	 * The URL of the webhook endpoint.
	 *
	 * @var string $url
	 */
	public $url;

	/**
	 * The email address for notifications.
	 *
	 * @var string $email
	 */
	public $email;

	/**
	 * The notification sending type.
	 *
	 * @var string $send_type
	 */
	public $send_type;

	/**
	 * Whether the webhook is enabled.
	 *
	 * @var bool $enabled
	 */
	public $enabled;

	/**
	 * Whether the webhook is interrupted.
	 *
	 * @var bool $interrupted
	 */
	public $interrupted;

	/**
	 * The authentication token for the webhook.
	 *
	 * @var string $auth_token
	 */
	public $auth_token;

	/**
	 * An array of supported webhook events.
	 *
	 * @var array $events
	 */
	public $events;

	/**
	 * Asaas webhook suffix
	 *
	 * @var string
	 */
	const WEBHOOK_SUFFIX = '/asaas-webhook';

	/**
	 * Init the webhook setting data.
	 */
	public function __construct() {
		$this->set_request_data();
	}

	/**
	 * Set all webhook request data as an associative array.
	 *
	 * @return void
	 */
	private function set_request_data() {
		$this->name        = __( 'Webhooks from WooCommerce', 'woo-asaas' );
		$this->url         = home_url() . self::WEBHOOK_SUFFIX;
		$this->email       = get_option( 'admin_email' );
		$this->send_type   = 'SEQUENTIALLY';
		$this->enabled     = true;
		$this->interrupted = false;
		$this->auth_token  = ( new Webhook_Helper() )->generate_random_token();
		$this->events      = array(
			Webhook::PAYMENT_CONFIRMED,
			Webhook::PAYMENT_CREATED,
			Webhook::PAYMENT_DELETED,
			Webhook::PAYMENT_OVERDUE,
			Webhook::PAYMENT_RECEIVED,
			Webhook::PAYMENT_REFUNDED,
			Webhook::PAYMENT_RESTORED,
			Webhook::PAYMENT_UPDATED,
		);
	}

	/**
	 * Generates a random token and update the gateway settings.
	 *
	 * @param string $token The generated token.
	 * @return void
	 */
	public function set_access_token( string $token ) {
		$this->gateway = WC_Asaas::get_instance();

		if ( '' === $token ) {
			return;
		}

		foreach ( $this->gateway->get_gateways() as $gateway ) {
			$gateway->update_option( 'webhook_access_token', $token );
		}

		$this->auth_token = $token;
	}

	/**
	 * Updates the email property with a valid email address if provided.
	 *
	 * @param string $email The provided notification email address.
	 * @return void
	 */
	public function set_email( string $email ) {
		if ( ! is_email( $email ) ) {
			return;
		}

		$this->email = $email;
	}

	/**
	 * Gets all webhook request data as an associative array.
	 *
	 * @return array An associative array of webhook data.
	 */
	public function get_request_data() {
		return array(
			'name'        => $this->name,
			'url'         => $this->url,
			'email'       => $this->email,
			'sendType'    => $this->send_type,
			'enabled'     => $this->enabled,
			'interrupted' => $this->interrupted,
			'authToken'   => $this->auth_token,
			'events'      => $this->events,
		);
	}
}
