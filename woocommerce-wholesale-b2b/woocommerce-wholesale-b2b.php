<?php
/**
 * Plugin Name:       WooCommerce Wholesale & B2B
 * Plugin URI:        https://example.com/
 * Description:       A B2B wholesale plugin for WooCommerce with cost of goods, tiered pricing, and more.
 * Version:           1.0.0
 * Author:            Jules
 * Author URI:        https://example.com/
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

        /**
         * The single instance of the class.
         *
         * @var WC_Wholesale_B2B
         */
        protected static $_instance = null;

        /**
         * Main Instance.
         * Ensures only one instance of the main class is loaded.
         */
        public static function instance() {
            if ( is_null( self::$_instance ) ) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        /**
         * Constructor.
         */
        public function __construct() {
            $this->define_constants();
            // Activation hook.
            register_activation_hook( __FILE__, array( $this, 'activate' ) );

            if ( is_admin() ) {
                // Load admin features.
                require_once dirname( __FILE__ ) . '/includes/class-wc-wholesale-b2b-admin.php';
                new WC_Wholesale_B2B_Admin();
            } else {
                // Load frontend features.
                require_once dirname( __FILE__ ) . '/includes/class-wc-wholesale-b2b-frontend.php';
                new WC_Wholesale_B2B_Frontend();
            }
        }

        /**
         * Plugin activation callback.
         * This runs once when the plugin is activated.
         */
        /**
         * Define constants.
         */
        private function define_constants() {
            define( 'WC_WHOLESALE_B2B_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
            define( 'WC_WHOLESALE_B2B_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
        }

        public function activate() {
            $this->add_wholesale_role();
        }

        /**
         * Add the 'Wholesale Customer' user role.
         * This role has the same capabilities as a regular customer.
         */
        private function add_wholesale_role() {
            // Get the 'customer' role to clone its capabilities.
            $customer = get_role( 'customer' );

            // Fallback capabilities if customer role doesn't exist.
            $capabilities = ( $customer ) ? $customer->capabilities : array( 'read' => true );

            // Add the 'wholesale_customer' role.
            add_role(
                'wholesale_customer',
                __( 'Wholesale Customer', 'woocommerce-wholesale-b2b' ),
                $capabilities
            );
        }
    }

    /**
     * Begins execution of the plugin.
     *
     * Since everything within the plugin is registered via hooks,
     * then kicking off the plugin from this point in the file does
     * not affect the page life cycle.
     */
    function wc_wholesale_b2b() {
        return WC_Wholesale_B2B::instance();
    }

    // Let's get this party started.
    wc_wholesale_b2b();

}
