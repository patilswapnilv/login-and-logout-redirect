<?php
/**
 * Plugin Name: Login and Logout Redirect
 * Description: This plugin adds extra option in Settings page (Setting>General), for specifying redirect URL for login and logout.
 */

// Load the plugin text domain
add_action( 'plugins_loaded', 'load_login_logout_redirect_textdomain' );
function load_login_logout_redirect_textdomain() {
    load_plugin_textdomain( 'login-and-logout-redirect', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

// Example of how to internationalize a string
function my_plugin_function() {
    echo esc_html__( 'Welcome to my plugin!', 'login-and-logout-redirect' );
}

add_action( 'wp_footer', 'my_plugin_function' );

?>
