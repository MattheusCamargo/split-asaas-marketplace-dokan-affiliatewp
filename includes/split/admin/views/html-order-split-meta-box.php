<?php
/**
 * Order split meta box template
 *
 * @package WooAsaas
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="asaas-split-info">
    <?php if ($split_data): ?>
        <p>
            <strong><?php esc_html_e('Status:', 'woo-asaas'); ?></strong>
            <?php echo esc_html($split_status ?: __('Pendente', 'woo-asaas')); ?>
        </p>

        <?php if ($marketplace_commission): ?>
        <p>
            <strong><?php esc_html_e('Comissão Marketplace:', 'woo-asaas'); ?></strong>
            <?php echo wc_price($marketplace_commission); ?>
        </p>
        <?php endif; ?>

        <?php if ($affiliate_commission): ?>
        <p>
            <strong><?php esc_html_e('Comissão Afiliado:', 'woo-asaas'); ?></strong>
            <?php echo wc_price($affiliate_commission); ?>
        </p>
        <?php endif; ?>

        <table class="wc-order-splits">
            <thead>
                <tr>
                    <th><?php esc_html_e('Wallet', 'woo-asaas'); ?></th>
                    <th><?php esc_html_e('Valor', 'woo-asaas'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($split_data as $split): ?>
                <tr>
                    <td><?php echo esc_html($split['walletId']); ?></td>
                    <td><?php echo wc_price($split['fixedValue']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p><?php esc_html_e('Nenhum split configurado para este pedido.', 'woo-asaas'); ?></p>
    <?php endif; ?>
</div>

<style>
.asaas-split-info table {
    width: 100%;
    margin-top: 10px;
    border-collapse: collapse;
}
.asaas-split-info th,
.asaas-split-info td {
    padding: 5px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}
.asaas-split-info th {
    background: #f8f8f8;
}
</style>
