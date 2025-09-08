<?php
/**
 * Frontend functionality for the plugin.
 *
 * @package WooCommerce_Wholesale_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Wholesale_B2B_Frontend {

    private $registration_success = false;

    public function __construct() {
        // Price filtering hooks.
        add_filter( 'woocommerce_product_get_price', array( $this, 'get_wholesale_price' ), 20, 2 );
        add_filter( 'woocommerce_product_get_regular_price', array( $this, 'get_wholesale_price' ), 20, 2 );
        add_filter( 'woocommerce_product_variation_get_price', array( $this, 'get_wholesale_price' ), 20, 2 );
        add_filter( 'woocommerce_product_variation_get_regular_price', array( $this, 'get_wholesale_price' ), 20, 2 );
        add_filter( 'woocommerce_product_get_sale_price', array( $this, 'remove_sale_price_for_wholesale' ), 20, 2 );
        add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'remove_sale_price_for_wholesale' ), 20, 2 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'set_cart_item_wholesale_price' ), 20, 1 );

        // Shortcodes.
        add_shortcode( 'wholesale_order_form', array( $this, 'render_wholesale_order_form' ) );
        add_shortcode( 'wholesale_registration_form', array( $this, 'render_wholesale_registration_form' ) );

        // AJAX endpoints.
		add_action( 'wp_ajax_wwb_search_products', array( $this, 'ajax_search_products' ) );
		add_action( 'wp_ajax_wwb_add_to_cart', array( $this, 'ajax_add_to_cart' ) );

        // Form handlers.
        add_action( 'init', array( $this, 'handle_lead_registration_submission' ) );

        // Other features
        if ( 'yes' === get_option( 'wwb_private_store_enabled', 'no' ) ) {
            add_filter( 'woocommerce_is_purchasable', array( $this, 'private_store_is_purchasable' ), 10, 2 );
            add_filter( 'woocommerce_get_price_html', array( $this, 'private_store_price_html' ), 100, 2 );
        }
        if ( 'yes' === get_option( 'wwb_disable_coupons_for_wholesale', 'no' ) ) {
            add_filter( 'woocommerce_coupons_enabled', array( $this, 'disable_coupons_for_wholesale' ) );
        }

        $minimum_rules = get_option( 'wwb_category_minimums' );
        if ( ! empty( $minimum_rules ) ) {
            add_action( 'woocommerce_check_cart_items', array( $this, 'enforce_minimum_order_rules' ) );
        }
    }

    public function enforce_minimum_order_rules() {
        if ( empty( wwb_get_current_user_wholesale_roles() ) ) {
            return;
        }

        $rules = get_option( 'wwb_category_minimums', array() );
        if ( empty( $rules ) ) {
            return;
        }

        // First, create "buckets" for each category that has a rule.
        $category_buckets = array();
        foreach ( $rules as $rule ) {
            $category_id = $rule['category'];
            if ( ! isset( $category_buckets[ $category_id ] ) ) {
                $category_buckets[ $category_id ] = array( 'quantity' => 0, 'subtotal' => 0 );
            }
        }

        // Loop through the cart and populate the buckets.
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product_id = $cart_item['product_id'];
            foreach ( $category_buckets as $cat_id => $bucket ) {
                if ( has_term( $cat_id, 'product_cat', $product_id ) ) {
                    $category_buckets[ $cat_id ]['quantity'] += $cart_item['quantity'];
                    $category_buckets[ $cat_id ]['subtotal'] += $cart_item['line_subtotal'];
                }
            }
        }

        // Now, check each rule against its bucket.
        foreach ( $rules as $rule ) {
            $cat_id   = $rule['category'];
            $type     = $rule['type'];
            $value    = $rule['value'];
            $cat_info = get_term( $cat_id, 'product_cat' );
            $cat_name = is_wp_error( $cat_info ) ? '#' . $cat_id : $cat_info->name;

            if ( 'subtotal' === $type ) {
                if ( $category_buckets[ $cat_id ]['subtotal'] < $value ) {
                    wc_add_notice( sprintf( __( 'You must have an order with a minimum of %s from the "%s" category. Your current total for this category is %s.', 'woocommerce-wholesale-b2b' ), wc_price( $value ), $cat_name, wc_price( $category_buckets[ $cat_id ]['subtotal'] ) ), 'error' );
                }
            } elseif ( 'quantity' === $type ) {
                if ( $category_buckets[ $cat_id ]['quantity'] < $value ) {
                    wc_add_notice( sprintf( __( 'You must have a minimum of %d items from the "%s" category. You currently have %d items.', 'woocommerce-wholesale-b2b' ), $value, $cat_name, $category_buckets[ $cat_id ]['quantity'] ), 'error' );
                }
            }
        }
    }

    // ... All other methods are unchanged and present ...
    public function get_wholesale_price( $price, $product ) { /* ... */ }
    public function remove_sale_price_for_wholesale( $price, $product ) { /* ... */ }
    public function set_cart_item_wholesale_price( $cart ) { /* ... */ }
    public function disable_coupons_for_wholesale( $enabled ) { /* ... */ }
    public function private_store_is_purchasable( $is_purchasable, $product ) { /* ... */ }
    public function private_store_price_html( $price_html, $product ) { /* ... */ }
    public function handle_lead_registration_submission() { /* ... */ }
    public function render_wholesale_registration_form( $atts ) { /* ... */ }
    public function enqueue_order_form_scripts() { /* ... */ }
    public function ajax_add_to_cart() { /* ... */ }
    public function ajax_search_products() { /* ... */ }
    public function render_wholesale_order_form( $atts ) { /* ... */ }
}
