<?php
/**
 * Admin page for managing wholesale roles.
 *
 * @package WooCommerce_Wholesale_B2B
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Wholesale_B2B_Roles_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_actions' ) );
    }

    public static function get_wholesale_roles() {
        return get_option( 'wwb_wholesale_roles', array() );
    }

    public function handle_actions() {
        // Handle Add New Role
        if ( isset( $_POST['wwb_add_role_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['wwb_add_role_nonce'] ), 'wwb_add_role' ) ) {
            $this->add_role();
        }

        // Handle Delete Role
        if ( isset( $_GET['action'], $_GET['role'], $_GET['_wpnonce'] ) && 'delete' === $_GET['action'] && wp_verify_nonce( sanitize_key( $_GET['_wpnonce'] ), 'wwb_delete_role_' . sanitize_key( $_GET['role'] ) ) ) {
            $this->delete_role( sanitize_key( $_GET['role'] ) );
        }
    }

    private function add_role() {
        $role_name = isset( $_POST['role_name'] ) ? sanitize_text_field( wp_unslash( $_POST['role_name'] ) ) : '';
        $role_key  = isset( $_POST['role_key'] ) ? sanitize_key( $_POST['role_key'] ) : '';

        if ( ! $role_name || ! $role_key ) {
            add_action( 'admin_notices', function() { echo '<div class="notice notice-error"><p>Role name and key are required.</p></div>'; } );
            return;
        }

        $roles = self::get_wholesale_roles();
        if ( isset( $roles[ $role_key ] ) || role_exists( $role_key ) ) {
            add_action( 'admin_notices', function() { echo '<div class="notice notice-error"><p>Role key already exists.</p></div>'; } );
            return;
        }

        $customer = get_role( 'customer' );
        add_role( $role_key, $role_name, $customer ? $customer->capabilities : array( 'read' => true ) );

        $roles[ $role_key ] = array( 'name' => $role_name );
        update_option( 'wwb_wholesale_roles', $roles );

        wp_redirect( admin_url( 'admin.php?page=wc-wholesale-roles&role_added=true' ) );
        exit;
    }

    private function delete_role( $role_key ) {
        $roles = self::get_wholesale_roles();
        if ( isset( $roles[ $role_key ] ) ) {
            unset( $roles[ $role_key ] );
            update_option( 'wwb_wholesale_roles', $roles );
            remove_role( $role_key );
        }
        wp_redirect( admin_url( 'admin.php?page=wc-wholesale-roles&role_deleted=true' ) );
        exit;
    }

    public function add_admin_menu() {
        add_submenu_page('woocommerce', 'Wholesale Roles', 'Wholesale Roles', 'manage_woocommerce', 'wc-wholesale-roles', array( $this, 'render_roles_page' ));
    }

    public function render_roles_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Wholesale Roles', 'woocommerce-wholesale-b2b' ); ?></h1>
            <p><?php esc_html_e( 'Manage your custom wholesale user roles here. These roles will be available when setting tiered pricing on products.', 'woocommerce-wholesale-b2b' ); ?></p>

            <div id="col-container" class="wp-clearfix">
                <div id="col-left"><div class="col-wrap">
                    <h2><?php esc_html_e( 'Add New Wholesale Role', 'woocommerce-wholesale-b2b' ); ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wc-wholesale-roles' ) ); ?>">
                        <div class="form-field">
                            <label for="role_name"><?php esc_html_e( 'Role Name', 'woocommerce-wholesale-b2b' ); ?></label>
                            <input name="role_name" id="role_name" type="text" value="" style="width: 95%;" required/>
                            <p>The name as it appears on your site.</p>
                        </div>
                        <div class="form-field">
                            <label for="role_key"><?php esc_html_e( 'Role Key', 'woocommerce-wholesale-b2b' ); ?></label>
                            <input name="role_key" id="role_key" type="text" value="" style="width: 95%;" required pattern="[a-z0-9_]+"/>
                            <p>A unique key for the role (e.g., 'gold_tier'). Use only lowercase letters, numbers, and underscores.</p>
                        </div>
                        <?php wp_nonce_field( 'wwb_add_role', 'wwb_add_role_nonce' ); ?>
                        <?php submit_button( __( 'Add New Role', 'woocommerce-wholesale-b2b' ) ); ?>
                    </form>
                </div></div>
                <div id="col-right"><div class="col-wrap">
                    <h2><?php esc_html_e( 'Existing Roles', 'woocommerce-wholesale-b2b' ); ?></h2>
                    <table class="wp-list-table widefat fixed striped">
                        <thead><tr><th scope="col">Role Name</th><th scope="col">Role Key</th></tr></thead>
                        <tbody>
                            <?php $roles = self::get_wholesale_roles();
                            if ( ! empty( $roles ) ) :
                                foreach ( $roles as $key => $role ) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html( $role['name'] ); ?></strong>
                                            <div class="row-actions">
                                                <span class="trash"><a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=wc-wholesale-roles&action=delete&role=' . $key ), 'wwb_delete_role_' . $key ) ); ?>" class="delete-tag"><?php esc_html_e( 'Delete', 'woocommerce-wholesale-b2b' ); ?></a></span>
                                            </div>
                                        </td>
                                        <td><?php echo esc_html( $key ); ?></td>
                                    </tr>
                                <?php endforeach;
                            else : ?>
                                <tr><td colspan="2"><?php esc_html_e( 'No wholesale roles found.', 'woocommerce-wholesale-b2b' ); ?></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div></div>
            </div>
        </div>
        <?php
    }
}
