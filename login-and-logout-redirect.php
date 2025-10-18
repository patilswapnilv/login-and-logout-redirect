<?php
/**
 * The plugin bootstrap file
 * @wordpress-plugin
 * Plugin Name:       Login and Logout Redirect
 * Plugin URI:        https://github.com/patilswapnilv/login-and-logout-redirect/
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           2.0.2
 * Author:            Swapnil V. Patil
 * Author URI:        https://swapnilpatil.in
 * Description:       A WordPress plugin which enables you to redirect users on login and logout or both, in a simplest way.
 * License:           GPL-3.0
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.en.html
 * Text Domain:       login-and-logout-redirect
 * Domain Path:       /languages
 */

// Load the plugin text domain
add_action( 'plugins_loaded', 'load_login_logout_redirect_textdomain' );
function load_login_logout_redirect_textdomain() {
    load_plugin_textdomain( 'login-and-logout-redirect', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

?>
