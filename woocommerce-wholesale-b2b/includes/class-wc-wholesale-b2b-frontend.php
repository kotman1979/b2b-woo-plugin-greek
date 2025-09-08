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
        $min_subtotal = get_option( 'wwb_minimum_order_subtotal' );
        $min_quantity = get_option( 'wwb_minimum_order_quantity' );
        if ( ! empty( $min_subtotal ) || ! empty( $min_quantity ) ) {
            add_action( 'woocommerce_check_cart_items', array( $this, 'enforce_minimum_order_rules' ) );
        }
    }

    public function get_wholesale_price( $price, $product ) {
        return wwb_get_best_wholesale_price( $price, $product );
    }

    public function remove_sale_price_for_wholesale( $price, $product ) {
        $user_wholesale_roles = wwb_get_current_user_wholesale_roles();
        if ( ! empty( $user_wholesale_roles ) ) {
            $best_price = wwb_get_best_wholesale_price( $product->get_regular_price(), $product );
            if ( $best_price !== $product->get_regular_price() ) {
                return '';
            }
        }
        return $price;
    }

    public function set_cart_item_wholesale_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) { return; }
        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) { return; }
        if ( empty( wwb_get_current_user_wholesale_roles() ) ) { return; }
        foreach ( $cart->get_cart() as $cart_item ) {
            $product = $cart_item['data'];
            $price = $this->get_wholesale_price( $product->get_price(), $product );
            $cart_item['data']->set_price( $price );
        }
    }

    public function enforce_minimum_order_rules() {
        if ( empty( wwb_get_current_user_wholesale_roles() ) ) { return; }
        $min_subtotal = (float) get_option( 'wwb_minimum_order_subtotal' );
        $min_quantity = (int) get_option( 'wwb_minimum_order_quantity' );
        if ( $min_subtotal > 0 ) {
            $cart_subtotal = WC()->cart->get_subtotal();
            if ( $cart_subtotal < $min_subtotal ) { wc_add_notice( sprintf( __( 'Your current order total is %s — you must have an order with a minimum of %s to place your order.', 'woocommerce-wholesale-b2b' ), wc_price( $cart_subtotal ), wc_price( $min_subtotal ) ), 'error' ); }
        }
        if ( $min_quantity > 0 ) {
            $cart_quantity = WC()->cart->get_cart_contents_count();
            if ( $cart_quantity < $min_quantity ) { wc_add_notice( sprintf( _n( 'You must have a minimum of %d item in your cart to place your order. You currently have %d item.', 'You must have a minimum of %d items in your cart to place your order. You currently have %d items.', $min_quantity, 'woocommerce-wholesale-b2b' ), $min_quantity, $cart_quantity ), 'error' ); }
        }
    }

    public function disable_coupons_for_wholesale( $enabled ) {
        if ( ! empty( wwb_get_current_user_wholesale_roles() ) ) { return false; }
        return $enabled;
    }

    public function private_store_is_purchasable( $is_purchasable, $product ) {
        if ( ! is_user_logged_in() ) { return false; }
        return $is_purchasable;
    }

    public function private_store_price_html( $price_html, $product ) {
        if ( ! is_user_logged_in() ) { return __( 'Please log in to see prices.', 'woocommerce-wholesale-b2b' ); }
        return $price_html;
    }

    public function handle_lead_registration_submission() {
        if ( 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['wwb_action'] ) || 'register_lead' !== $_POST['wwb_action'] ) { return; }
        if ( ! isset( $_POST['wwb_lead_registration_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wwb_lead_registration_nonce'] ), 'wwb_register_lead' ) ) { wc_add_notice( __( 'Security check failed. Please try again.', 'woocommerce-wholesale-b2b' ), 'error' ); return; }
        $required = array( 'first_name', 'last_name', 'email', 'password', 'company_name' );
        foreach ( $required as $field ) { if ( empty( $_POST[ $field ] ) ) { wc_add_notice( sprintf( __( '%s is a required field.', 'woocommerce-wholesale-b2b' ), '<strong>' . ucwords( str_replace( '_', ' ', $field ) ) . '</strong>' ), 'error' ); } }
        $email = sanitize_email( wp_unslash( $_POST['email'] ) );
        if ( ! is_email( $email ) ) { wc_add_notice( __( 'Please provide a valid email address.', 'woocommerce' ), 'error' ); } elseif ( email_exists( $email ) ) { wc_add_notice( __( 'An account is already registered with your email address. Please log in.', 'woocommerce' ), 'error' ); }
        if ( wc_notice_count( 'error' ) > 0 ) { return; }
        $lead_data = array( 'first_name' => sanitize_text_field( wp_unslash( $_POST['first_name'] ) ), 'last_name' => sanitize_text_field( wp_unslash( $_POST['last_name'] ) ), 'email' => $email, 'company_name' => sanitize_text_field( wp_unslash( $_POST['company_name'] ) ), 'tax_id' => sanitize_text_field( wp_unslash( $_POST['tax_id'] ) ) );
        $lead_id = wp_insert_post( array( 'post_type' => 'wholesale_lead', 'post_title' => $lead_data['first_name'] . ' ' . $lead_data['last_name'] . ' - ' . $lead_data['company_name'], 'post_status' => 'publish' ), true );
        if ( is_wp_error( $lead_id ) ) { wc_add_notice( __( 'There was an error submitting your application. Please try again.', 'woocommerce-wholesale-b2b' ), 'error' ); return; }
        update_post_meta( $lead_id, '_lead_status', 'pending' ); update_post_meta( $lead_id, '_lead_email', $email ); update_post_meta( $lead_id, '_lead_data', $lead_data );
        $admin_email = get_option( 'admin_email' ); $subject = sprintf( '[%s] New Wholesale Account Application', get_bloginfo( 'name' ) ); $message = "A new wholesale application has been submitted.\n\nName: {$lead_data['first_name']} {$lead_data['last_name']}\nCompany: {$lead_data['company_name']}\nEmail: {$email}\n\nReview the application here: " . admin_url( 'post.php?post=' . $lead_id . '&action=edit' ); wp_mail( $admin_email, $subject, $message );
        $user_subject = sprintf( '[%s] Your Wholesale Application has been Received', get_bloginfo( 'name' ) ); $user_message = "Hi {$lead_data['first_name']},\n\nThank you for applying for a wholesale account. We have received your application and will review it shortly. You will receive another email once your application has been approved."; wp_mail( $email, $user_subject, $user_message );
        $this->registration_success = true; wc_add_notice( __( 'Thank you! Your application has been submitted successfully and is now under review.', 'woocommerce-wholesale-b2b' ), 'success' );
    }
    public function render_wholesale_registration_form( $atts ) {
        if ( is_user_logged_in() ) { return '<div class="woocommerce-info">' . esc_html__( 'You are already registered and logged in.', 'woocommerce-wholesale-b2b' ) . '</div>'; }
        ob_start();
        wc_print_notices();
        if ( $this->registration_success ) { return ob_get_clean(); }
        ?>
        <div class="woocommerce">
            <h2><?php esc_html_e( 'Wholesale Account Application', 'woocommerce-wholesale-b2b' ); ?></h2>
            <form method="post" class="woocommerce-form woocommerce-form-register register" id="wholesale-registration-form">
                <?php wp_nonce_field( 'wwb_register_lead', 'wwb_lead_registration_nonce' ); ?>
                <input type="hidden" name="wwb_action" value="register_lead" />
                <h3 class="woocommerce-heading"><?php esc_html_e( 'Account Details', 'woocommerce-wholesale-b2b' ); ?></h3>
                <p class="woocommerce-form-row woocommerce-form-row--first"><label for="reg_first_name"><?php esc_html_e( 'First name', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label><input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="first_name" id="reg_first_name" required value="<?php echo ( ! empty( $_POST['first_name'] ) ) ? esc_attr( wp_unslash( $_POST['first_name'] ) ) : ''; ?>" /></p>
                <p class="woocommerce-form-row woocommerce-form-row--last"><label for="reg_last_name"><?php esc_html_e( 'Last name', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label><input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="last_name" id="reg_last_name" required value="<?php echo ( ! empty( $_POST['last_name'] ) ) ? esc_attr( wp_unslash( $_POST['last_name'] ) ) : ''; ?>" /></p>
                <p class="woocommerce-form-row woocommerce-form-row--wide"><label for="reg_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label><input type="email" class="woocommerce-Input woocommerce-Input--text input-text" name="email" id="reg_email" required value="<?php echo ( ! empty( $_POST['email'] ) ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" /></p>
                <p class="woocommerce-form-row woocommerce-form-row--wide"><label for="reg_password"><?php esc_html_e( 'Password', 'woocommerce' ); ?>&nbsp;<span class="required">*</span></label><input type="password" class="woocommerce-Input woocommerce-Input--text input-text" name="password" id="reg_password" required /></p>
                <h3 class="woocommerce-heading"><?php esc_html_e( 'Business Details', 'woocommerce-wholesale-b2b' ); ?></h3>
                <p class="woocommerce-form-row woocommerce-form-row--wide"><label for="reg_company_name"><?php esc_html_e( 'Company name', 'woocommerce-wholesale-b2b' ); ?>&nbsp;<span class="required">*</span></label><input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="company_name" id="reg_company_name" required value="<?php echo ( ! empty( $_POST['company_name'] ) ) ? esc_attr( wp_unslash( $_POST['company_name'] ) ) : ''; ?>" /></p>
                <p class="woocommerce-form-row woocommerce-form-row--wide"><label for="reg_tax_id"><?php esc_html_e( 'Tax/VAT ID', 'woocommerce-wholesale-b2b' ); ?></label><input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="tax_id" id="reg_tax_id" value="<?php echo ( ! empty( $_POST['tax_id'] ) ) ? esc_attr( wp_unslash( $_POST['tax_id'] ) ) : ''; ?>" /></p>
                <p class="woocommerce-form-row"><button type="submit" class="woocommerce-Button button" name="register" value="<?php esc_attr_e( 'Submit Application', 'woocommerce-wholesale-b2b' ); ?>"><?php esc_html_e( 'Submit Application', 'woocommerce-wholesale-b2b' ); ?></button></p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    public function enqueue_order_form_scripts() {
        wp_enqueue_script( 'wwb-order-form', WC_WHOLESALE_B2B_PLUGIN_URL . 'assets/js/order-form.js', array( 'jquery', 'wp-util' ), '1.0.1', true );
        wp_localize_script( 'wwb-order-form', 'wwb_params', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'wwb_order_form_nonce' ), 'cart_url' => wc_get_cart_url(), 'view_cart_text' => esc_attr__( 'View cart', 'woocommerce' ), 'i18n' => array( 'prompt_initial' => __( 'Type to search for products or select a category.', 'woocommerce-wholesale-b2b' ), 'prompt' => __( 'Type at least 3 characters to search.', 'woocommerce-wholesale-b2b' ), 'loading' => __( 'Loading...', 'woocommerce-wholesale-b2b' ), 'no_results' => __( 'No products found.', 'woocommerce-wholesale-b2b' ), 'error' => __( 'An error occurred. Please try again.', 'woocommerce-wholesale-b2b' ) ) ) );
    }
    public function ajax_add_to_cart() {
        check_ajax_referer( 'wwb_order_form_nonce', 'nonce' );
        if ( empty( wwb_get_current_user_wholesale_roles() ) ) { wp_send_json_error( array( 'message' => __( 'Access denied.', 'woocommerce-wholesale-b2b' ) ), 403 ); }
        $quantities = isset( $_POST['quantity'] ) ? wp_unslash( $_POST['quantity'] ) : array();
        if ( empty( $quantities ) ) { wp_send_json_error( array( 'message' => __( 'No products selected.', 'woocommerce-wholesale-b2b' ) ) ); }
        $added_count = 0;
        foreach ( $quantities as $product_id => $quantity ) { $product_id = absint( $product_id ); $quantity = absint( $quantity ); if ( $quantity > 0 && $product_id > 0 ) { if ( false !== WC()->cart->add_to_cart( $product_id, $quantity ) ) { $added_count++; } } }
        if ( $added_count > 0 ) {
            $fragments = apply_filters( 'woocommerce_add_to_cart_fragments', array() );
            wp_send_json_success( array( 'fragments' => $fragments, 'cart_hash' => WC()->cart->get_cart_hash(), 'message' => sprintf( _n( '%s product was successfully added to your cart.', '%s products were successfully added to your cart.', $added_count, 'woocommerce-wholesale-b2b' ), $added_count ) ) );
        } else { wp_send_json_error( array( 'message' => __( 'No products were added to your cart. Please check quantities.', 'woocommerce-wholesale-b2b' ) ) ); }
    }
    public function ajax_search_products() {
        check_ajax_referer( 'wwb_order_form_nonce', 'nonce' );
        if ( empty( wwb_get_current_user_wholesale_roles() ) ) { wp_send_json_error( array( 'message' => __( 'Access denied.', 'woocommerce-wholesale-b2b' ) ), 403 ); }
        $search_term = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $category_id = isset( $_POST['category'] ) ? absint( $_POST['category'] ) : 0;
        if ( empty( $search_term ) && empty( $category_id ) ) { wp_send_json_error( array( 'message' => __( 'Please enter a search term or select a category.', 'woocommerce-wholesale-b2b' ) ) ); }
        $args = array( 'status' => 'publish', 'limit' => 100, 's' => $search_term, 'post_type' => array( 'product', 'product_variation' ), 'orderby' => 'title', 'order' => 'ASC' );
        if ( $category_id > 0 ) { $term = get_term( $category_id, 'product_cat' ); if ( $term && ! is_wp_error( $term ) ) { $args['category'] = array( $term->slug ); } }
        $products = wc_get_products( $args ); $results  = array();
        if ( ! empty( $products ) ) { foreach ( $products as $product ) { if ( ! $product->is_in_stock() || ! $product->is_purchasable() || $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) ) { continue; } $results[] = array( 'id' => $product->get_id(), 'name' => $product->get_formatted_name(), 'permalink'  => $product->get_permalink(), 'sku' => $product->get_sku() ? $product->get_sku() : '-', 'thumbnail'  => $product->get_image( 'woocommerce_thumbnail' ), 'price_html' => $product->get_price_html() ); } }
        wp_send_json_success( $results );
    }
    public function render_wholesale_order_form( $atts ) {
        if ( empty( wwb_get_current_user_wholesale_roles() ) ) { return '<p>' . esc_html__( 'You must be a logged-in wholesale customer to view this page.', 'woocommerce-wholesale-b2b' ) . '</p>'; }
        $this->enqueue_order_form_scripts();
        ob_start();
        ?>
        <script type="text/html" id="tmpl-wholesale-product-row">
            <tr class="cart_item"><td class="product-thumbnail"><a href="{{ data.permalink }}">{{{ data.thumbnail }}}</a></td><td class="product-name" data-title="<?php esc_attr_e( 'Product', 'woocommerce-wholesale-b2b' ); ?>"><a href="{{ data.permalink }}">{{ data.name }}</a><br><small>SKU: {{ data.sku }}</small></td><td class="product-price" data-title="<?php esc_attr_e( 'Price', 'woocommerce-wholesale-b2b' ); ?>">{{{ data.price_html }}}</td><td class="product-quantity" data-title="<?php esc_attr_e( 'Quantity', 'woocommerce-wholesale-b2b' ); ?>"><div class="quantity"><input type="number" class="input-text qty text" step="1" min="0" name="quantity[{{ data.id }}]" value="" title="<?php esc_attr_e( 'Qty', 'woocommerce-wholesale-b2b' ); ?>" size="4" placeholder="0"></div></td></tr>
        </script>
        <style>#wholesale-order-form-filters { margin-bottom: 20px; display: flex; gap: 15px; align-items: center; flex-wrap: wrap; } #wholesale-order-form-actions { margin-top: 20px; text-align: right; } #wholesale-product-list .product-quantity input { width: 70px !important; } #wholesale-product-list .product-thumbnail img { width: 50px; height: auto; } .woocommerce-message, .woocommerce-error { margin-bottom: 20px !important; }</style>
        <div class="woocommerce">
            <div id="wholesale-order-form-container">
                <h2><?php esc_html_e( 'Wholesale Order Form', 'woocommerce-wholesale-b2b' ); ?></h2>
                <div id="wholesale-order-form-messages"></div>
                <div id="wholesale-order-form-filters"><input type="search" id="wholesale-product-search" placeholder="<?php esc_attr_e( 'Search products by name or SKU...', 'woocommerce-wholesale-b2b' ); ?>" style="flex-grow: 1; min-width: 200px;"><?php wp_dropdown_categories( array( 'taxonomy' => 'product_cat', 'name' => 'wholesale_product_category', 'show_option_all' => esc_html__( 'All Categories', 'woocommerce-wholesale-b2b' ), 'hierarchical' => true, 'class' => 'wholesale-product-category-filter', 'id' => 'wholesale-product-category-filter' ) ); ?></div>
                <form id="wholesale-order-form" class="cart"><input type="hidden" name="action" value="wwb_add_to_cart"><?php wp_nonce_field( 'wwb_order_form_nonce', 'nonce' ); ?><table class="shop_table shop_table_responsive cart woocommerce-cart-form__contents"><thead><tr><th class="product-thumbnail">&nbsp;</th><th class="product-name"><?php esc_html_e( 'Product', 'woocommerce-wholesale-b2b' ); ?></th><th class="product-price"><?php esc_html_e( 'Price', 'woocommerce-wholesale-b2b' ); ?></th><th class="product-quantity"><?php esc_html_e( 'Quantity', 'woocommerce-wholesale-b2b' ); ?></th></tr></thead><tbody id="wholesale-product-list"><tr><td colspan="4" style="text-align: center; padding: 20px;"><?php esc_html_e( 'Type to search for products or select a category.', 'woocommerce-wholesale-b2b' ); ?></td></tr></tbody></table><div id="wholesale-order-form-actions"><button type="submit" class="button alt" name="add_all_to_cart" value="1"><?php esc_html_e( 'Add Selected To Cart', 'woocommerce-wholesale-b2b' ); ?></button></div></form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
