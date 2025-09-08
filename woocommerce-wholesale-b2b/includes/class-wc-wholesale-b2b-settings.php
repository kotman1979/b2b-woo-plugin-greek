<?php
/**
 * Settings for WooCommerce Wholesale & B2B.
 *
 * @package WooCommerce_Wholesale_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Wholesale_B2B_Settings' ) ) :

class WC_Wholesale_B2B_Settings extends WC_Settings_Page {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id    = 'wholesale';
		$this->label = __( 'Wholesale', 'woocommerce-wholesale-b2b' );

		parent::__construct();
	}

	/**
	 * Get sections.
	 *
	 * @return array
	 */
	public function get_sections() {
		$sections = array(
			'' => __( 'General', 'woocommerce-wholesale-b2b' ),
		);
		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	/**
	 * Get settings array.
	 *
	 * @param string $current_section Current section name.
	 * @return array
	 */
	public function get_settings( $current_section = '' ) {
		$settings = array();

		if ( '' === $current_section ) {
			$settings = apply_filters(
				'woocommerce_wholesale_general_settings',
				array(
					array(
						'title' => __( 'General Options', 'woocommerce-wholesale-b2b' ),
						'type'  => 'title',
						'desc'  => '',
						'id'    => 'wholesale_general_options',
					),
					array(
						'title'   => __( 'Private Store', 'woocommerce-wholesale-b2b' ),
						'desc'    => __( 'Enable Private Store mode. This will hide prices and the "Add to Cart" button from logged-out users.', 'woocommerce-wholesale-b2b' ),
						'id'      => 'wwb_private_store_enabled',
						'type'    => 'checkbox',
						'default' => 'no',
					),
                    array(
                        'title'   => __( 'Disable Coupons for Wholesale', 'woocommerce-wholesale-b2b' ),
                        'desc'    => __( 'Disable all coupon functionality for wholesale users.', 'woocommerce-wholesale-b2b' ),
                        'id'      => 'wwb_disable_coupons_for_wholesale',
                        'type'    => 'checkbox',
                        'default' => 'no',
                    ),
                    array(
                        'title'             => __( 'Minimum Order Subtotal', 'woocommerce-wholesale-b2b' ),
                        'desc'              => __( 'Set a minimum subtotal for wholesale orders. Leave blank to disable.', 'woocommerce-wholesale-b2b' ),
                        'id'                => 'wwb_minimum_order_subtotal',
                        'type'              => 'number',
                        'custom_attributes' => array( 'min' => 0, 'step' => '0.01' ),
                        'desc_tip'          => true,
                    ),
                    array(
                        'title'             => __( 'Minimum Order Quantity', 'woocommerce-wholesale-b2b' ),
                        'desc'              => __( 'Set a minimum number of items for wholesale orders. Leave blank to disable.', 'woocommerce-wholesale-b2b' ),
                        'id'                => 'wwb_minimum_order_quantity',
                        'type'              => 'number',
                        'custom_attributes' => array( 'min' => 0, 'step' => '1' ),
                        'desc_tip'          => true,
                    ),
					array(
						'type' => 'sectionend',
						'id'   => 'wholesale_general_options',
					),
				)
			);
		}

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}

    /**
	 * Save settings.
	 */
	public function save() {
		parent::save();
	}
}

endif;
