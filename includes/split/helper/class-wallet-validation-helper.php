<?php
/**
 * Wallet Validation Helper class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Split\Helper;

/**
 * Helper methods for wallet ID validation
 */
class Wallet_Validation_Helper {
    /**
     * Validates a wallet ID format
     *
     * @param string $wallet_id Wallet ID to validate
     * @return bool True if valid, false otherwise
     */
    public static function is_valid_wallet_id($wallet_id) {
        $pattern = '/^[a-f\d]{8}-[a-f\d]{4}-[a-f\d]{4}-[a-f\d]{4}-[a-f\d]{12}$/i';
        return (bool) preg_match($pattern, $wallet_id);
    }

    /**
     * Gets the pattern for HTML5 pattern attribute
     *
     * @return string Pattern for HTML5 pattern attribute
     */
    public static function get_html_pattern() {
        return '[a-fA-F\d]{8}-[a-fA-F\d]{4}-[a-fA-F\d]{4}-[a-fA-F\d]{4}-[a-fA-F\d]{12}';
    }

    /**
     * Gets the placeholder example for wallet ID field
     *
     * @return string Placeholder text
     */
    public static function get_placeholder() {
        return 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
    }

    /**
     * Gets the wallet ID format description
     *
     * @return string Format description
     */
    public static function get_format_description() {
        return __('Formato: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx', 'woo-asaas');
    }
}
