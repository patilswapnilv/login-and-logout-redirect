<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://swapnilpatil.in
 * @since      1.0.4
 *
 * @package    Login_And_Logout_Redirect
 */

// If uninstall not called from WordPress, then exit.
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if the user has the necessary permissions to uninstall the plugin.
if (! current_user_can('delete_plugins')) {
    wp_die(__('You do not have sufficient permissions to uninstall this plugin.', 'login-and-logout-redirect'));
}

// Check if the uninstall request is valid.
if (! isset($_GET['uninstall_login_logout_redirect']) || 'uninstall' !== $_GET['uninstall_login_logout_redirect']) {
    return;
}

// Sanitize and validate the plugin name.
$plugin = isset($_REQUEST['plugin']) ? sanitize_text_field($_REQUEST['plugin']) : '';

if ('login-and-logout-redirect/login-and-logout-redirect.php' !== $plugin) {
    return;
}

// Delete plugin options.
delete_option('login_redirect_url');
delete_option('logout_redirect_url');
