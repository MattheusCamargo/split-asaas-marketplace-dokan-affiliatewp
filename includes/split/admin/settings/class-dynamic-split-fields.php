<?php
/**
 * Dynamic Split Fields class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Split\Admin\Settings;

use Exception;
use WC_Asaas\Admin\Settings\Settings;

/**
 * Dynamic split fields for marketplace and affiliate integration
 */
class Dynamic_Split_Fields {

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
     * Add dynamic split fields to the gateway settings.
     *
     * @param array    $fields Gateway fields.
     * @param Settings $settings Gateway settings object.
     * @return array
     */
    public function add_fields($fields, $settings) {
        return array_merge(
            $fields,
            array(
                'dynamic_split_enabled' => array(
                    'title'       => __('Habilitar Split Dinâmico', 'woo-asaas'),
                    'type'        => 'checkbox',
                    'label'       => __('Ativar', 'woo-asaas'),
                    'description' => __('Ativa o cálculo dinâmico de split para produtores (Dokan) e afiliados (AffiliateWP). Desativa a configuração manual de split abaixo se marcada.', 'woo-asaas'),
                    'default'     => 'no',
                    'section'     => 'split',
                    'priority'    => 5,
                    'shared'      => true,
                ),
                'marketplace_asaas_wallet_id' => array(
                    'title'       => __('ID da Carteira Asaas do Marketplace', 'woo-asaas'),
                    'type'        => 'text',
                    'description' => __('Informe a walletId da conta Asaas principal do marketplace.', 'woo-asaas'),
                    'default'     => '',
                    'section'     => 'split',
                    'priority'    => 10,
                    'shared'      => true,
                ),
                'marketplace_commission_percentage' => array(
                    'title'             => __('Comissão do Marketplace sobre Itens (%)', 'woo-asaas'),
                    'type'              => 'number',
                    'description'       => __('Percentual da comissão do marketplace sobre o valor de cada item (antes da comissão do afiliado).', 'woo-asaas'),
                    'default'           => '0',
                    'custom_attributes' => array(
                        'min'  => '0',
                        'max'  => '100',
                        'step' => '0.01'
                    ),
                    'section'           => 'split',
                    'priority'          => 20,
                    'shared'            => true,
                ),
                'affiliate_commission_priority' => array(
                    'title'       => __('Cálculo da Comissão do Afiliado', 'woo-asaas'),
                    'type'        => 'select',
                    'description' => __('Defina como a comissão do afiliado será considerada no split.', 'woo-asaas'),
                    'default'     => 'affiliatewp_value',
                    'options'     => array(
                        'affiliatewp_value' => __('Usar valor de comissão do AffiliateWP', 'woo-asaas'),
                        'percentage_after_marketplace' => __('Percentual sobre valor do item (após comissão do marketplace)', 'woo-asaas')
                    ),
                    'section'     => 'split',
                    'priority'    => 30,
                    'shared'      => true,
                ),
                'default_affiliate_commission_percentage' => array(
                    'title'             => __('Percentual Padrão Comissão Afiliado sobre Itens (%)', 'woo-asaas'),
                    'type'              => 'number',
                    'description'       => __('Percentual padrão para o afiliado, aplicado sobre o valor do item após a dedução da comissão do marketplace. Só será usado se "Cálculo da Comissão do Afiliado" estiver definido para percentual.', 'woo-asaas'),
                    'default'           => '0',
                    'custom_attributes' => array(
                        'min'  => '0',
                        'max'  => '100',
                        'step' => '0.01'
                    ),
                    'section'           => 'split',
                    'priority'          => 40,
                    'shared'            => true,
                ),
            )
        );
    }
}
