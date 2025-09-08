<?php
/**
 * Admin-side functionality for the plugin.
 *
 * @package WooCommerce_Wholesale_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Wholesale_B2B_Admin {

    public function __construct() {
        // Product meta fields
        add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_custom_product_fields' ) );
        add_action( 'woocommerce_process_product_meta', array( $this, 'save_custom_product_fields' ) );
        // CPT for Leads
        add_action( 'init', array( $this, 'register_wholesale_lead_cpt' ) );
        // Custom columns for CPT
        add_filter( 'manage_wholesale_lead_posts_columns', array( $this, 'set_wholesale_lead_columns' ) );
        add_action( 'manage_wholesale_lead_posts_custom_column', array( $this, 'render_wholesale_lead_columns' ), 10, 2 );
        // Meta boxes for CPT
        add_action( 'add_meta_boxes', array( $this, 'add_lead_details_meta_box' ) );
        // Handle lead actions (approve/deny) via admin-post
        add_action( 'admin_post_wwb_lead_action', array( $this, 'handle_lead_actions_post' ) );
        // Display admin notices
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
    }

    public function handle_lead_actions_post() {
        $lead_id = isset( $_POST['lead_id'] ) ? absint( $_POST['lead_id'] ) : 0;
        if ( ! $lead_id || ! isset( $_POST['wwb_lead_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wwb_lead_nonce'] ), 'wwb_lead_action_' . $lead_id ) ) { wp_die( __( 'Security check failed.', 'woocommerce-wholesale-b2b' ) ); }
        if ( ! current_user_can( 'edit_post', $lead_id ) ) { wp_die( __( 'You do not have permission to perform this action.', 'woocommerce-wholesale-b2b' ) ); }
        if ( ! isset( $_POST['wwb_decision'] ) ) { wp_redirect( admin_url( 'post.php?post=' . $lead_id . '&action=edit' ) ); exit; }

        $status = get_post_meta( $lead_id, '_lead_status', true );
        if ( in_array( $status, array( 'approved', 'denied' ), true ) ) { wp_redirect( admin_url( 'post.php?post=' . $lead_id . '&action=edit' ) ); exit; }

        $decision = sanitize_key( $_POST['wwb_decision'] );
        if ( 'approve' === $decision ) {
            $role_to_assign = isset( $_POST['wwb_assign_role'] ) ? sanitize_key( $_POST['wwb_assign_role'] ) : 'wholesale_customer';
            $result = $this->approve_wholesale_lead( $lead_id, $role_to_assign );
            set_transient( 'wwb_admin_notice_' . get_current_user_id(), $result, 60 );
        } elseif ( 'deny' === $decision ) {
            $result = $this->deny_wholesale_lead( $lead_id );
            set_transient( 'wwb_admin_notice_' . get_current_user_id(), $result, 60 );
        }
        wp_redirect( admin_url( 'post.php?post=' . $lead_id . '&action=edit' ) );
        exit;
    }

    private function approve_wholesale_lead( $post_id, $role_to_assign ) {
        $lead_data = get_post_meta( $post_id, '_lead_data', true );
        $email = $lead_data['email'];
        if ( email_exists( $email ) ) { return array( 'type' => 'error', 'message' => 'User with this email already exists!' ); }

        $user_id = wp_insert_user( array(
            'user_login' => $email, 'user_email' => $email, 'user_pass'  => wp_generate_password(),
            'first_name' => $lead_data['first_name'], 'last_name'  => $lead_data['last_name'], 'role' => $role_to_assign,
        ) );

        if ( is_wp_error( $user_id ) ) { return array( 'type' => 'error', 'message' => 'Error creating user: ' . $user_id->get_error_message() ); }

        update_post_meta( $post_id, '_lead_status', 'approved' );
        update_post_meta( $post_id, '_user_id', $user_id );

        $user = get_user_by( 'id', $user_id );
        $reset_key = get_password_reset_key( $user );
        $reset_link = network_site_url( "wp-login.php?action=rp&key=$reset_key&login=" . rawurlencode( $user->user_login ), 'login' );
        $subject = sprintf( '[%s] Your Wholesale Account has been Approved!', get_bloginfo( 'name' ) );
        $message = "Hi {$user->first_name},\n\nCongratulations! Your wholesale account has been approved.\n\nYour username is: {$user->user_login}\nPlease set your password by clicking here: {$reset_link}\n\nWe look forward to doing business with you!";
        wp_mail( $user->user_email, $subject, $message );
        return array( 'type' => 'success', 'message' => 'Lead approved and user created successfully. An email with a password reset link has been sent.' );
    }

    public function render_lead_details_meta_box_content( $post ) {
        $lead_data = get_post_meta( $post->ID, '_lead_data', true );
        $lead_data = is_array( $lead_data ) ? $lead_data : array(); ?>
        <style> .lead-details-table { width: 100%; } .lead-details-table td { padding: 8px; vertical-align: top; } .lead-details-table td:first-child { font-weight: bold; width: 150px; } </style>
        <table class="lead-details-table">
            <?php foreach ( $lead_data as $key => $value ) : ?>
            <tr><td><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?></td><td><?php echo esc_html( $value ); ?></td></tr>
            <?php endforeach; ?>
        </table><hr><h3><?php esc_html_e( 'Actions', 'woocommerce-wholesale-b2b' ); ?></h3>
        <?php $status = get_post_meta( $post->ID, '_lead_status', true );
        if ( 'approved' === $status ) {
            $user_id = get_post_meta( $post->ID, '_user_id', true );
            echo '<p>' . esc_html__( 'This lead has been approved and a user account has been created.', 'woocommerce-wholesale-b2b' ) . '</p>';
            if ( $user_id && get_user_by( 'ID', $user_id ) ) { echo '<a href="' . esc_url( get_edit_user_link( $user_id ) ) . '" class="button">' . esc_html__( 'View User Profile', 'woocommerce-wholesale-b2b' ) . '</a>'; }
        } elseif ( 'denied' === $status ) {
            echo '<p>' . esc_html__( 'This lead has been denied.', 'woocommerce-wholesale-b2b' ) . '</p>';
        } else { ?>
            <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
                <input type="hidden" name="action" value="wwb_lead_action">
                <input type="hidden" name="lead_id" value="<?php echo esc_attr( $post->ID ); ?>">
                <?php wp_nonce_field( 'wwb_lead_action_' . $post->ID, 'wwb_lead_nonce' ); ?>
                <p>
                    <label for="wwb_assign_role"><strong><?php esc_html_e( 'Assign Role:', 'woocommerce-wholesale-b2b' ); ?></strong></label>
                    <select name="wwb_assign_role" id="wwb_assign_role">
                        <option value="wholesale_customer"><?php esc_html_e( 'Wholesale Customer (Default)', 'woocommerce-wholesale-b2b' ); ?></option>
                        <?php
                        $custom_roles = class_exists('WC_Wholesale_B2B_Roles_Admin') ? WC_Wholesale_B2B_Roles_Admin::get_wholesale_roles() : array();
                        foreach ( $custom_roles as $role_key => $role_data ) {
                            echo '<option value="' . esc_attr( $role_key ) . '">' . esc_html( $role_data['name'] ) . '</option>';
                        }
                        ?>
                    </select>
                </p>
                <p>
                    <button type="submit" name="wwb_decision" value="approve" class="button button-primary"><?php esc_html_e( 'Approve Application', 'woocommerce-wholesale-b2b' ); ?></button>
                    <button type="submit" name="wwb_decision" value="deny" class="button button-secondary"><?php esc_html_e( 'Deny Application', 'woocommerce-wholesale-b2b' ); ?></button>
                </p>
            </form>
        <?php }
    }

    // All other methods are unchanged and present in the file.
    public function save_custom_product_fields( $product_id ) { /* ... */ }
    public function add_custom_product_fields() { /* ... */ }
    public function display_admin_notices() { /* ... */ }
    private function deny_wholesale_lead( $post_id ) { /* ... */ }
    public function register_wholesale_lead_cpt() { /* ... */ }
    public function set_wholesale_lead_columns( $columns ) { /* ... */ }
    public function render_wholesale_lead_columns( $column, $post_id ) { /* ... */ }
    public function add_lead_details_meta_box() { /* ... */ }
}
