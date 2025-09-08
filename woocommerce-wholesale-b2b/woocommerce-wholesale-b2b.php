<?php
/**
 * Plugin Name:       WooCommerce Wholesale & B2B
 * Plugin URI:        https://socialideas.ai/
 * Description:       #1 WooCommerce wholesale plugin for adding wholesale prices & managing B2B customers. Trusted by 25k+ store owners with 500+ reviews.
 * Version:           1.0.1
 * Author:            Social Ideas Greece
 * Author URI:        https://socialideas.ai/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woocommerce-wholesale-b2b
 * Domain Path:       /languages
 * WC requires at least: 6.0
 * WC tested up to: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Wholesale_B2B' ) ) {

    final class WC_Wholesale_B2B {

        protected static $_instance = null;

        public static function instance() {
            if ( is_null( self::$_instance ) ) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        public function __construct() {
            $this->define_constants();
            $this->includes();
            $this->init_hooks();
        }

        private function define_constants() {
            define( 'WC_WHOLESALE_B2B_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
            define( 'WC_WHOLESALE_B2B_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
        }

        public function includes() {
            require_once WC_WHOLESALE_B2B_PLUGIN_PATH . 'includes/class-wc-wholesale-b2b-frontend.php';
            new WC_Wholesale_B2B_Frontend();

            if ( is_admin() ) {
                require_once WC_WHOLESALE_B2B_PLUGIN_PATH . 'includes/class-wc-wholesale-b2b-admin.php';
                new WC_Wholesale_B2B_Admin();

                require_once WC_WHOLESALE_B2B_PLUGIN_PATH . 'includes/class-wc-wholesale-b2b-settings.php';
                require_once WC_WHOLESALE_B2B_PLUGIN_PATH . 'includes/class-wc-wholesale-b2b-roles-admin.php';
                new WC_Wholesale_B2B_Roles_Admin();
            }
        }

        private function init_hooks() {
            register_activation_hook( __FILE__, array( $this, 'activate' ) );
            add_filter( 'woocommerce_get_settings_pages', array( $this, 'add_wholesale_settings_page' ) );
        }

        public function add_wholesale_settings_page( $pages ) {
            if ( class_exists('WC_Wholesale_B2B_Settings') ) {
                $pages[] = new WC_Wholesale_B2B_Settings();
            }
            return $pages;
        }

        public function activate() {
            $this->add_wholesale_role();
        }

        private function add_wholesale_role() {
            $customer = get_role( 'customer' );
            $capabilities = ( $customer ) ? $customer->capabilities : array( 'read' => true );
            add_role( 'wholesale_customer', __( 'Wholesale Customer', 'woocommerce-wholesale-b2b' ), $capabilities );
        }
    }

    function wc_wholesale_b2b() {
        return WC_Wholesale_B2B::instance();
    }
    wc_wholesale_b2b();
}

if ( ! function_exists( 'wwb_get_current_user_wholesale_roles' ) ) {
    /**
     * Get all wholesale roles for the current user.
     * @return array An array of wholesale role keys. Empty if none.
     */
    function wwb_get_current_user_wholesale_roles() {
        if ( ! is_user_logged_in() ) { return array(); }
        $user = wp_get_current_user();
        $user_roles = (array) $user->roles;
        $wholesale_roles = get_option( 'wwb_wholesale_roles', array() );
        $wholesale_role_keys = array_keys( $wholesale_roles );
        $wholesale_role_keys[] = 'wholesale_customer';
        return array_intersect( $user_roles, $wholesale_role_keys );
    }
}

if ( ! function_exists( 'wwb_get_best_wholesale_price' ) ) {
    /**
     * Get the best (lowest) wholesale price for a product for the current user.
     * @param string|float $original_price The product's original price.
     * @param WC_Product   $product The product object.
     * @return string|float The best price.
     */
    function wwb_get_best_wholesale_price( $original_price, $product ) {
        $user_wholesale_roles = wwb_get_current_user_wholesale_roles();
        if ( empty( $user_wholesale_roles ) || ! is_a( $product, 'WC_Product' ) ) {
            return $original_price;
        }
        $lowest_price = null;
        foreach ( $user_wholesale_roles as $role ) {
            $role_price = $product->get_meta( $role . '_wholesale_price', true );
            if ( '' !== $role_price && is_numeric( $role_price ) ) {
                $role_price = floatval( $role_price );
                if ( is_null( $lowest_price ) || $role_price < $lowest_price ) {
                    $lowest_price = $role_price;
                }
            }
        }
        return ! is_null( $lowest_price ) ? $lowest_price : $original_price;
    }
}
