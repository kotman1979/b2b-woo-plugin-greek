<?php
/**
 * Settings for WooCommerce Wholesale & B2B.
 *
 * @package WooCommerce_Wholesale_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Make sure the class is only defined once.
if ( ! class_exists( 'WC_Wholesale_B2B_Settings' ) ) :

/**
 * WC_Wholesale_B2B_Settings Class.
 */
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
	 */
	public function get_sections() {
		$sections = array(
			''         => __( 'General', 'woocommerce-wholesale-b2b' ),
            'minimums' => __( 'Minimum Order Rules', 'woocommerce-wholesale-b2b' ),
		);
		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}

	/**
	 * Get settings array.
	 */
	public function get_settings( $current_section = '' ) {
		if ( 'minimums' === $current_section ) {
            return array(
                array(
                    'title' => __( 'Per-Category Minimum Order Rules', 'woocommerce-wholesale-b2b' ),
                    'type'  => 'title',
                    'desc'  => __( 'Set minimum purchase requirements for specific product categories. These rules apply only to wholesale users.', 'woocommerce-wholesale-b2b' ),
                    'id'    => 'wholesale_minimums_options',
                ),
                array(
                    'type' => 'category_minimums_repeater',
                    'id'   => 'wwb_category_minimums',
                ),
                array(
                    'type' => 'sectionend',
                    'id'   => 'wholesale_minimums_options',
                ),
            );
        } else {
			return array(
                array(
                    'title' => __( 'General Options', 'woocommerce-wholesale-b2b' ),
                    'type'  => 'title',
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
                    'type' => 'sectionend',
                    'id'   => 'wholesale_general_options',
                ),
            );
		}
	}

    /**
     * Save settings.
     */
    public function save() {
        if ( 'minimums' === $this->get_current_section() ) {
            $this->save_minimums_settings();
        } else {
            parent::save();
        }
    }

    /**
     * Save the repeater settings.
     */
    public function save_minimums_settings() {
        $rules = array();
        if ( isset( $_POST['wwb_category_minimums'] ) && is_array( $_POST['wwb_category_minimums'] ) ) {
            $submitted_rules = $_POST['wwb_category_minimums'];
            foreach ( $submitted_rules as $rule ) {
                if ( ! empty( $rule['category'] ) ) {
                    $rules[] = array(
                        'category' => intval( $rule['category'] ),
                        'type'     => sanitize_key( $rule['type'] ),
                        'value'    => wc_format_decimal( $rule['value'] ),
                    );
                }
            }
        }
        update_option( 'wwb_category_minimums', $rules, true );
        WC_Admin_Settings::add_message( __( 'Your settings have been saved.', 'woocommerce' ) );
    }
}

endif;


if ( ! function_exists( 'woocommerce_admin_field_category_minimums_repeater' ) ) {
    function woocommerce_admin_field_category_minimums_repeater( $value ) {
        $rules = get_option( 'wwb_category_minimums', array() );
        ?>
        <tr valign="top">
            <td class="forminp" colspan="2">
                <table class="widefat wc_input_table" id="wwb-category-minimums-table">
                    <thead>
                        <tr>
                            <th style="width:40%;"><?php esc_html_e( 'Category', 'woocommerce-wholesale-b2b' ); ?></th>
                            <th style="width:20%;"><?php esc_html_e( 'Rule Type', 'woocommerce-wholesale-b2b' ); ?></th>
                            <th style="width:20%;"><?php esc_html_e( 'Minimum Value', 'woocommerce-wholesale-b2b' ); ?></th>
                            <th style="width:10%;"><?php esc_html_e( 'Remove', 'woocommerce-wholesale-b2b' ); ?></th>
                        </tr>
                    </thead>
                    <tbody id="wwb-minimums-repeater-body">
                        <?php if ( ! empty( $rules ) ) :
                            foreach ( $rules as $i => $rule ) : ?>
                                <tr class="wwb-minimums-repeater-row">
                                    <td>
                                        <?php
                                        wp_dropdown_categories( array(
                                            'taxonomy'         => 'product_cat',
                                            'name'             => 'wwb_category_minimums[<?php echo $i; ?>][category]',
                                            'selected'         => $rule['category'],
                                            'show_option_none' => __( 'Select a category', 'woocommerce-wholesale-b2b' ),
                                            'hierarchical'     => true,
                                            'class'            => 'wc-enhanced-select',
                                        ) );
                                        ?>
                                    </td>
                                    <td>
                                        <select name="wwb_category_minimums[<?php echo $i; ?>][type]">
                                            <option value="subtotal" <?php selected( $rule['type'], 'subtotal' ); ?>><?php esc_html_e( 'Subtotal', 'woocommerce-wholesale-b2b' ); ?></option>
                                            <option value="quantity" <?php selected( $rule['type'], 'quantity' ); ?>><?php esc_html_e( 'Quantity', 'woocommerce-wholesale-b2b' ); ?></option>
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.01" min="0" name="wwb_category_minimums[<?php echo $i; ?>][value]" value="<?php echo esc_attr( $rule['value'] ); ?>" /></td>
                                    <td><button type="button" class="button button-secondary wwb-remove-rule-button">X</button></td>
                                </tr>
                            <?php endforeach;
                        endif; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4">
                                <button type="button" class="button button-primary wwb-add-rule-button"><?php esc_html_e( 'Add Rule', 'woocommerce-wholesale-b2b' ); ?></button>
                            </th>
                        </tr>
                    </tfoot>
                </table>
            </td>
        </tr>
        <script type="text/template" id="tmpl-wwb-minimums-repeater-row">
            <tr class="wwb-minimums-repeater-row">
                <td>
                    <?php
                    wp_dropdown_categories( array(
                        'taxonomy'         => 'product_cat',
                        'name'             => 'wwb_category_minimums[{{{ data.index }}}][category]',
                        'show_option_none' => __( 'Select a category', 'woocommerce-wholesale-b2b' ),
                        'hierarchical'     => true,
                        'class'            => 'wc-enhanced-select-new',
                    ) );
                    ?>
                </td>
                <td>
                    <select name="wwb_category_minimums[{{{ data.index }}}][type]">
                        <option value="subtotal"><?php esc_html_e( 'Subtotal', 'woocommerce-wholesale-b2b' ); ?></option>
                        <option value="quantity"><?php esc_html_e( 'Quantity', 'woocommerce-wholesale-b2b' ); ?></option>
                    </select>
                </td>
                <td><input type="number" step="0.01" min="0" name="wwb_category_minimums[{{{ data.index }}}][value]" value="" /></td>
                <td><button type="button" class="button button-secondary wwb-remove-rule-button">X</button></td>
            </tr>
        </script>
        <?php
    }
}
