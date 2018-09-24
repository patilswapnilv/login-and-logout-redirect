<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://swapnilpatil.in
 * @since      1.0.4
 *
 * @package    Login_And_Logout_Redirect
 * @subpackage Login_And_Logout_Redirect/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.4
 * @package    Login_And_Logout_Redirect
 * @subpackage Login_And_Logout_Redirect/includes
 * @author     Swapnil V. Patil <patilswapnilv@gmail.com>
 */
class Login_And_Logout_Redirect_i18n {


    /**
     * Load the plugin text domain for translation.
     *
     * @since    1.0.4
     */
    public function load_plugin_textdomain() {

        load_plugin_textdomain(
            'login-and-logout-redirect',
            false,
            dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
        );

    }



}
