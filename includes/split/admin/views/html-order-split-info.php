<?php
/**
 * Order split info template
 *
 * @package WooAsaas
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="asaas-split-details">
    <h3><?php esc_html_e('Detalhes do Split de Pagamento', 'woo-asaas'); ?></h3>
    
    <table class="wc-order-split-details">
        <tr>
            <th><?php esc_html_e('Status do Split:', 'woo-asaas'); ?></th>
            <td><?php echo esc_html($split_status ?: __('Pendente', 'woo-asaas')); ?></td>
        </tr>

        <?php if ($marketplace_commission): ?>
        <tr>
            <th><?php esc_html_e('Comissão do Marketplace:', 'woo-asaas'); ?></th>
            <td><?php echo wc_price($marketplace_commission); ?></td>
        </tr>
        <?php endif; ?>

        <?php if ($affiliate_commission): ?>
        <tr>
            <th><?php esc_html_e('Comissão do Afiliado:', 'woo-asaas'); ?></th>
            <td><?php echo wc_price($affiliate_commission); ?></td>
        </tr>
        <?php endif; ?>
    </table>

    <h4><?php esc_html_e('Distribuição do Split', 'woo-asaas'); ?></h4>
    <table class="wc-order-splits-details">
        <thead>
            <tr>
                <th><?php esc_html_e('Wallet ID', 'woo-asaas'); ?></th>
                <th><?php esc_html_e('Valor', 'woo-asaas'); ?></th>
                <th><?php esc_html_e('Tipo', 'woo-asaas'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($split_data as $split): ?>
            <tr>
                <td><?php echo esc_html($split['walletId']); ?></td>
                <td><?php echo wc_price($split['fixedValue']); ?></td>
                <td>
                    <?php
                    if ($split['walletId'] === $marketplace_wallet_id) {
                        esc_html_e('Marketplace', 'woo-asaas');
                    } elseif (isset($affiliate_data) && $split['walletId'] === $affiliate_data['wallet_id']) {
                        esc_html_e('Afiliado', 'woo-asaas');
                    } else {
                        esc_html_e('Produtor', 'woo-asaas');
                    }
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
.asaas-split-details {
    margin: 20px 0;
    padding: 20px;
    background: #fff;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.asaas-split-details h3 {
    margin: 0 0 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.asaas-split-details h4 {
    margin: 20px 0 10px;
}

.wc-order-split-details,
.wc-order-splits-details {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.wc-order-split-details th,
.wc-order-split-details td,
.wc-order-splits-details th,
.wc-order-splits-details td {
    padding: 10px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.wc-order-split-details th,
.wc-order-splits-details th {
    background: #f8f8f8;
    font-weight: 600;
}

.wc-order-splits-details tr:hover td {
    background: #f9f9f9;
}
</style>
