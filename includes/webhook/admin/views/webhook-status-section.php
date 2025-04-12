<?php
/**
 * Webhook system status section template
 *
 * @package WooAsaas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* translators: Add a tooltip help. */
$tooltip_help = esc_html__( 'If the API key is valid, the connection to Asaas will be established. Otherwise, it will be necessary to update it.', 'woo-asaas' );

?>

<table class="wc_status_table widefat" id="webhook-status-section" cellspacing="0">
	<thead>
		<tr>
			<th colspan="3" data-export-label="<?php esc_html_e( 'Asaas Payment Method', 'woo-asaas' ); ?>"><h2><?php esc_html_e( 'Asaas Payment Method', 'woo-asaas' ); ?></h2></th>
		</tr>
	</thead>
	<tbody>
		<tr id="webhook-status-connection">
			<td data-export-label="<?php esc_html_e( 'Connection to Asaas', 'woo-asaas' ); ?>"><?php esc_html_e( 'Connection to Asaas', 'woo-asaas' ); ?></td>
			<td class="help"><span class="woocommerce-help-tip" tabindex="0" aria-label="<?php echo esc_attr( $tooltip_help ); ?>" data-tip="<?php echo esc_attr( $tooltip_help ); ?>"></span></td>
			<td class="loader"><span class="preloader"></span></td>
			<td class="connection-yes" style="display: none"><mark class="yes"><span class="dashicons dashicons-yes"></span></mark></td>
			<td class="connection-error" style="display: none"><mark class="error"><span class="dashicons dashicons-warning"></span><?php esc_html_e( 'Your API key is invalid or missing', 'woo-asaas' ); ?>. <a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ); ?>" target="_self"><?php esc_html_e( 'Click here to provide your new key', 'woo-asaas' ); ?>.</a></mark></td>
		</tr>
		<tr id="webhook-status-queue">
			<td data-export-label="<?php esc_html_e( 'Webhook Status (Payment Settlements)', 'woo-asaas' ); ?>"><?php esc_html_e( 'Webhook Status (Payment Settlements)', 'woo-asaas' ); ?></td>
			<td class="help"><span class="woocommerce-help-tip" tabindex="0" aria-label="<?php esc_html_e( 'Webhook Status (Payment Settlements)', 'woo-asaas' ); ?>" data-tip="<?php esc_html_e( 'Webhook queue status', 'woo-asaas' ); ?>"></span></td>
			<td class="loader"><span class="preloader"></span></td>
			<td class="queue-yes" style="display: none"><mark class="yes"><span class="dashicons dashicons-yes"></span></mark></td>
			<td class="queue-error" style="display: none"><mark class="error" style="margin-right: 20px"><span class="dashicons dashicons-no"></span></mark></td>
		</tr>
		<tr>
			<td></td>
			<td class="help"><span class="woocommerce-help-tip" tabindex="0" aria-label="<?php esc_html_e( 'If the webhook queue is interrupted, click the button next to it to reactivate it', 'woo-asaas' ); ?>." data-tip="<?php esc_html_e( 'If the webhook queue is interrupted, click the button next to it to reactivate it', 'woo-asaas' ); ?>."></span></td>
			<td>
				<div id="webhook-reenable-action">
					<button class="button-secondary reenable-queue" disabled><?php esc_html_e( 'Re-enable webhook queue', 'woo-asaas' ); ?></button>
				</div>
			</td>
		</tr>
	</tbody>
</table>
