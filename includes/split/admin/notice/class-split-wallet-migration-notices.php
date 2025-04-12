<?php
/**
 * Handles the display of migration notices for split wallets in the WP admin.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Split\Admin\Notice;

use Exception;
use WC_Asaas\WC_Asaas;
use WP_Screen;

/**
 * Class for handling the display of migration notices related to split wallets.
 */
class Split_Wallet_Migration_Notices {
	const WALLET_QUANTITY_OPTION_FIELD = 'wallets';

	/**
	 * List of allowed screens with their configurations.
	 *
	 * Each screen is defined as an associative array which may include any WP_Screen parameter.
	 *
	 * @var array
	 */
	const ALLOWED_SCREENS              = array(
		array( 'id' => 'dashboard' ),
		array( 'id' => 'plugins' ),
		array(
			'base'      => 'edit',
			'post_type' => 'shop_order',
		),
		array(
			'id'  => 'woocommerce_page_wc-settings',
			'tab' => 'checkout',
		),
		array(
			'id'      => 'woocommerce_page_wc-settings',
			'tab'     => 'checkout',
			'section' => 'asaas-credit-card',
		),
		array(
			'id'      => 'woocommerce_page_wc-settings',
			'tab'     => 'checkout',
			'section' => 'asaas-ticket',
		),
		array(
			'id'      => 'woocommerce_page_wc-settings',
			'tab'     => 'checkout',
			'section' => 'asaas-pix',
		),
	);
	const DISMISSED_WALLET_NOTICE_META = '_dismissed_wallet_notice';
	const NOTICE_EMAIL_ADDRESS         = 'integracoes@asaas.com.br';

	/**
	 * Instance of this class
	 *
	 * @var self
	 */
	protected static $instance = null;

	/**
	 * Is not allowed to call from outside to prevent creating multiple instances.
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
		throw new Exception(
			esc_html__( 'Cannot unserialize singleton', 'woo-asaas' )
		);
	}

	/**
	 * Return an instance of this class.
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
	 * Initializes the necessary hooks and sets up the action for displaying notices
	 * on the current screen using the `maybe_display_notice` method.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'current_screen', array( $this, 'maybe_display_notice' ) );
		add_action( 'admin_post_dismiss-asaas-split-wallet-notice', array( $this, 'dismiss_notice' ) );
	}

	/**
	 * Determines whether to display a notice based on the provided screen.
	 *
	 * @param   WP_Screen $screen  The current WordPress screen object.
	 *
	 * @return void
	 */
	public function maybe_display_notice( WP_Screen $screen ) {
		if ( ! $this->should_render_on_screen( $screen ) || ! $this->has_split_wallets() ) {
			return;
		}
		if ( ! $this->user_can_manage_woocommerce() || $this->user_disabled_notice() ) {
			return;
		}
		$this->display_notice();
	}

	/**
	 * Handles the AJAX request to dismiss the wallet notice.
	 * Verifies the nonce, updates user meta to mark notice as dismissed.
	 *
	 * @return void
	 */
	public function dismiss_notice() {
		if ( ! check_ajax_referer( 'asaas-split-wallet-notice', '_wallet_post_nonce', false ) ) {
			wp_die( esc_html__( 'Nonce verification failed', 'woo-asaas' ) );
		}
		$user_id = get_current_user_id();
		update_user_meta( $user_id, self::DISMISSED_WALLET_NOTICE_META, true );
	}

	/**
	 * Determines whether the content should render on the given screen.
	 *
	 * @param   WP_Screen $screen  The screen object to evaluate against the allowed screens.
	 *
	 * @return bool True if the content should render on the screen, false otherwise.
	 */
	private function should_render_on_screen( WP_Screen $screen ) {
		if ( $this->is_woocommerce_screen( $screen ) ) {
			return $this->should_render_on_woocommerce_screen( $screen );
		}

		return $this->should_render_on_regular_screen( $screen );
	}

	/**
	 * Checks if the current screen belongs to WooCommerce.
	 *
	 * @param WP_Screen $screen The current screen object.
	 * @return bool True if the screen belongs to WooCommerce, false otherwise.
	 */
	private function is_woocommerce_screen( WP_Screen $screen ) {
		if ( str_contains( $screen->id, 'woocommerce_page' ) ) {
			return true;
		}
		return false;
	}
	/**
	 * Determines whether to render content on the WooCommerce screen based on allowed screens and URL parameters.
	 *
	 * @param WP_Screen $screen The current screen object.
	 * @return bool True if content should be rendered, false otherwise.
	 */
	private function should_render_on_woocommerce_screen( WP_Screen $screen ) {
		foreach ( self::ALLOWED_SCREENS as $allowed_screen ) {
			if ( $screen->id === $allowed_screen['id'] ) {
				if ( $this->compare_url_parameters( $allowed_screen ) ) {
					return true;
				}
			}
		}

		return false;
	}
	/**
	 * Compares the current URL parameters with the allowed screen parameters.
	 *
	 * @param array $allowed_screen The allowed screen parameters.
	 * @return bool True if all URL parameters match the allowed parameters, false otherwise.
	 */
	private function compare_url_parameters( array $allowed_screen ) {
		unset( $allowed_screen['id'] );

		$get = $_GET; // phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification
		unset( $get['page'] );
		$filtered_get = $this->sanitize_input( $get );
		if ( count( $filtered_get ) === 0 ) {
			return false;
		}
		foreach ( $filtered_get as $key => $value ) {
			if ( ! array_key_exists( $key, $allowed_screen ) || $allowed_screen[ $key ] !== $value ) {
				return false;
			}
		}

		return true;
	}
	/**
	 * Sanitizes input data by trimming whitespace and converting special characters to HTML entities.
	 *
	 * @param array $data The input data to be sanitized.
	 * @return array The sanitized input data, with special characters converted and whitespace trimmed.
	 */
	private function sanitize_input( array $data ) {
		return array_map(
			function( $value ) {
				return htmlspecialchars( trim( $value ), ENT_SUBSTITUTE, 'UTF-8' );
			}, $data
		);
	}
	/**
	 * Determines whether to render content on a regular screen based on allowed screens and parameters.
	 *
	 * @param WP_Screen $screen The current screen object.
	 * @return bool True if content should be rendered, false otherwise.
	 */
	private function should_render_on_regular_screen( WP_Screen $screen ) {
		$should_render = false;
		foreach ( self::ALLOWED_SCREENS as $allowed_screen ) {
			if ( true === $should_render ) {
				return true;
			}
			if ( count( $allowed_screen ) > 1 ) {
				$should_render = $this->should_display_on_multiple_parameter_screen( $screen, $allowed_screen );
				continue;
			}
			$should_render = $this->should_display_on_single_parameter_screen( $screen, $allowed_screen );
		}

		return $should_render;
	}

	/**
	 * Evaluates whether the content should display on a screen with multiple parameters.
	 *
	 * @param   WP_Screen $screen_object  The screen object to check against the allowed parameters.
	 * @param   array     $allow_screen   An associative array of parameters and their expected values.
	 *
	 * @return bool True if all parameters match the screen object's properties, false otherwise.
	 */
	private function should_display_on_multiple_parameter_screen( WP_Screen $screen_object, array $allow_screen ) {
		foreach ( $allow_screen as $parameter => $value ) {
			if ( $screen_object->$parameter !== $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determines whether the screen matches a single parameter condition for display.
	 *
	 * @param   WP_Screen $screen_object   The screen object to evaluate.
	 * @param   array     $allowed_screen  An associative array containing a single parameter and its expected value.
	 *
	 * @return bool True if the screen parameter matches the expected value, false otherwise.
	 */
	private function should_display_on_single_parameter_screen( WP_Screen $screen_object, array $allowed_screen ) {
		$parameter = key( $allowed_screen );
		$value     = $allowed_screen[ $parameter ];

		return $screen_object->$parameter === $value;
	}

	/**
	 * Checks if there are gateways with split wallets configured.
	 *
	 * @return bool True if any Asaas gateway has split wallets, false otherwise.
	 */
	private function has_split_wallets() {
		$gateways = WC_Asaas::get_instance()->get_gateways();
		foreach ( $gateways as $gateway ) {
			if ( intval( $gateway->get_option( self::WALLET_QUANTITY_OPTION_FIELD ) ) > 0
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks if current user has WooCommerce management capabilities.
	 *
	 * @return bool True if user can manage WooCommerce, false otherwise.
	 */
	private function user_can_manage_woocommerce() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Checks if current user has disabled the wallet notice.
	 *
	 * @return bool True if notice was previously dismissed, false otherwise.
	 */
	private function user_disabled_notice() {
		return boolval( get_user_meta( get_current_user_id(), self::DISMISSED_WALLET_NOTICE_META, true ) );
	}

	/**
	 * Displays a notice in the WordPress admin area and provides functionality for a dismiss button.
	 *
	 * @return void
	 */
	public function display_notice() {
		add_action( 'admin_notices', array( $this, 'print_notice' ) );
		add_action( 'admin_footer', array( $this, 'change_default_dismiss_button_behavior' ) );
	}

	/**
	 * Outputs an admin notice about upcoming updates to the plugin's management of splits and partner wallets.
	 *
	 * This notice informs users about the new format for managing splits and partner wallets,
	 * and provides guidance on storing wallet IDs for migration and configuration purposes.
	 *
	 * @return void
	 */
	public function print_notice() {
		$notice = sprintf(
		/* translators: %1$s and %2$s are the contact emails for assistance with split migration. */
			__(
				'Attention! In the next update of the Asaas Plugin, a new format for managing splits and partner wallets will be created. If you have configured payment splits, make sure to store the walletId of your partners to perform the migration and reconfigure your payment methods. If you have any questions, contact our team at <a href="mailto:%1$s">%2$s</a>.',
				'woo-asaas'
			),
			self::NOTICE_EMAIL_ADDRESS,
			self::NOTICE_EMAIL_ADDRESS
		);
		$button_label = __( 'OK, got it', 'woo-asaas' );

		echo wp_kses_post(
			"<div id='asaas-split-wallet-notice-container' class='notice notice-warning is-dismissible'>
				<p>{$notice}</p>
				<p><button id='dismiss-asaas-split-wallet-notice' type='button' class='button button-secondary'>{$button_label}</button></p>
			</div>"
		);
	}

	/**
	 * Renders a JavaScript snippet that attaches a click event listener to dismiss buttons.
	 * The script removes the specific notice element from the DOM when the button is clicked.
	 *
	 * @return void
	 */
	public function change_default_dismiss_button_behavior() {
		$nonce = wp_create_nonce( 'asaas-split-wallet-notice' );
		?>
		<script>
			jQuery(document).ready(function ($) {
				const noticeContainer = $('#asaas-split-wallet-notice-container');
				const walletNoticeDismissButton = $('#dismiss-asaas-split-wallet-notice');

				waitForElement('#asaas-split-wallet-notice-container .notice-dismiss', function (defaultDismissButton) {
					defaultDismissButton.off('click');
					defaultDismissButton.on('click', dismiss_notice);
				});
				walletNoticeDismissButton.on('click', dismiss_notice);

				function waitForElement(selector, callback, interval = 100) {
					const elementExist = setInterval(function () {
						const element = $(selector);
						if (element.length) {
							clearInterval(elementExist);
							callback(element);
						}
					}, interval);
				}

				function dismiss_notice() {
					$.post("<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>", {
						action: 'dismiss-asaas-split-wallet-notice',
						_wallet_post_nonce: '<?php echo esc_attr( $nonce ); ?>'
					})
					.done(function () {
						noticeContainer.remove();
					});
				}
			});
		</script>
		<?php
	}
}
