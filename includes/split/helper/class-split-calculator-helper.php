<?php
/**
 * Split Calculator Helper class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Split\Helper;

/**
 * Helper methods for split calculations
 */
class Split_Calculator_Helper {

    /**
     * Calculate marketplace commission for a given value
     *
     * @param float $value Value to calculate commission on
     * @param float $percentage Commission percentage
     * @return float Commission amount
     */
    public static function calculate_marketplace_commission($value, $percentage) {
        return ($value * $percentage) / 100;
    }

    /**
     * Calculate value distribution between multiple recipients proportionally
     *
     * @param array $recipients Array of recipients with their base amounts
     * @param float $deduction Amount to be deducted proportionally
     * @return array Updated amounts for each recipient
     */
    public static function calculate_proportional_distribution($recipients, $deduction) {
        $total = 0;
        foreach ($recipients as $recipient) {
            $total += $recipient['amount'];
        }

        if ($total <= 0) {
            return $recipients;
        }

        $updated = array();
        foreach ($recipients as $key => $recipient) {
            $proportion = $recipient['amount'] / $total;
            $deduction_amount = $deduction * $proportion;
            $updated[$key] = array_merge(
                $recipient,
                array('amount' => max(0, $recipient['amount'] - $deduction_amount))
            );
        }

        return $updated;
    }

    /**
     * Normalize split amounts to match total
     *
     * @param array $splits Array of splits with amounts
     * @param float $total_amount Expected total amount
     * @return array Normalized splits
     */
    public static function normalize_split_amounts($splits, $total_amount) {
        $current_total = 0;
        foreach ($splits as $split) {
            $current_total += $split['fixedValue'];
        }

        if ($current_total == $total_amount) {
            return $splits;
        }

        $difference = $total_amount - $current_total;
        if (abs($difference) <= 0.01) {
            // Diferença menor que 1 centavo, ajusta no primeiro split
            $splits[0]['fixedValue'] = round($splits[0]['fixedValue'] + $difference, 2);
            return $splits;
        }

        // Distribui a diferença proporcionalmente
        $proportions = array();
        foreach ($splits as $index => $split) {
            $proportions[$index] = $split['fixedValue'] / $current_total;
        }

        foreach ($splits as $index => &$split) {
            $adjustment = $difference * $proportions[$index];
            $split['fixedValue'] = max(0, round($split['fixedValue'] + $adjustment, 2));
        }

        return $splits;
    }

    /**
     * Round split amounts properly
     *
     * @param array $splits Array of splits
     * @return array Splits with rounded amounts
     */
    public static function round_split_amounts($splits) {
        foreach ($splits as &$split) {
            if (isset($split['fixedValue'])) {
                $split['fixedValue'] = round($split['fixedValue'], 2);
            }
        }
        return $splits;
    }

    /**
     * Validate split amounts
     *
     * @param array $splits Array of splits
     * @param float $total_amount Expected total amount
     * @return bool|string True if valid, error message if not
     */
    public static function validate_split_amounts($splits, $total_amount) {
        $split_total = 0;
        foreach ($splits as $split) {
            if (!isset($split['walletId']) || empty($split['walletId'])) {
                return __('Wallet ID ausente em um dos splits.', 'woo-asaas');
            }

            if (!isset($split['fixedValue']) || $split['fixedValue'] <= 0) {
                return sprintf(
                    __('Valor inválido para wallet %s', 'woo-asaas'),
                    $split['walletId']
                );
            }

            $split_total += $split['fixedValue'];
        }

        // Permite uma diferença de até 1 centavo devido a arredondamentos
        if (abs($split_total - $total_amount) > 0.01) {
            return sprintf(
                __('Soma dos splits (%.2f) não corresponde ao valor total (%.2f)', 'woo-asaas'),
                $split_total,
                $total_amount
            );
        }

        return true;
    }
}
