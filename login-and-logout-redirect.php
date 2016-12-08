<?php
/**
 * Login and Logout Redirect
 *
 * @category  Head
 * @package   Loginandlogoutredirect
 * @author    Swapnil V. Patil <patilswapnilv@gmail.com>
 * @copyright 2016 Swapnil V. Patil <patilswapnilv@gmail.com>
 * @license   GPL-3.0+ <https://www.gnu.org/licenses/gpl-3.0.en.html GPL 3.0>
 * @link      https://github.com/patilswapnilv/login-and-logout-redirect/readme.md
 * 
 * @wordpress-plugin
 * Plugin Name: Login and Logout Redirect
 * Plugin URI:  https://wordpress.org/plugins/login-and-logout-redirect/
 * Description: Redirects users to specified url on logging in, logging out OR both.
 * Version:     1.0.3
 * Author:      patilswapnilv
 * Author URI:  http://swapnilpatil.in/
 * Text Domain: login-and-logout-redirect
 * License:     GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

/**
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License (Version 2 - GPLv2)
    as published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/**
 * Plugin main class for logout-redirect
 *
 *   @category Main
 *   @package  Loginandlogoutredirect
 *   @author   Swapnil V. Patil <patilswapnilv@gmail.com>
 *   @license  https://www.gnu.org/licenses/gpl-3.0.en.html GPL 3.0
 *   @link     https://github.com/patilswapnilv/login-and-logout-redirect/readme.md
 *   @return   boolean
 * */
class LogoutRedirect
{

    /**
     * PHP 4 constructor
     *
     * @return boolean
     * */
    function LogoutRedirect() 
    {

    }

    /**
     * PHP 5 constructor
     * */
    function __construct() 
    {
        add_action('login_init', array( $this, 'clean_redirect' ));
        add_filter('wp_logout', array( &$this, 'redirect' ));
        add_action('plugin_options', array( &$this, 'network_option' ));
        add_action(
            'update_plugin_options', array(
            &$this,
            'update_network_option',
            )
        );
        add_action('admin_init', array( &$this, 'add_settings_field' ));

        // load text domain
        if (defined('SP_PLUGIN_DIR') && file_exists(
            SP_PLUGIN_DIR . '/logout-redirect.php'
        )
        ) {
               load_muplugin_textdomain(
                   'login-and-logout-redirect', 'logout-redirect-files/languages'
               );
        } else {
             load_plugin_textdomain(
                 'login-and-logout-redirect', false, dirname(plugin_basename(__FILE__)) . '/languages'
             );
        }
    }

    /**
    * Make a clean redirect
    */
    function clean_redirect() 
    {
        if (defined('LOGOUT_REDIRECT_DEFAULT_WP_BEHAVIOR') && LOGOUT_REDIRECT_DEFAULT_WP_BEHAVIOR ) {
            return false;
        }
        $action = ! empty($_REQUEST['action']) ? $_REQUEST['action'] : false;
        if ('logout' != $action ) {
            return false;
        }
        if (is_user_logged_in() ) {
            return true; // User is still logged in, let WP do its job.
        }

        // We're still here, so we have a case of user already logged out, requesting logout.
        // Suppress standard error and just redirect.
        $this->redirect();
    }

    /**
     * Redirect user on logout
     * */
    function redirect() 
    {
        $redirect_url = ! empty($_REQUEST['redirect_to']) && ! (defined('LOGOUT_REDIRECT_FORCED') && LOGOUT_REDIRECT_FORCED) ? $_REQUEST['redirect_to'] : $this->get_redirection_url();
        wp_redirect($redirect_url);
        exit();
    }

    private function _get_raw_redirection_url() 
    {
        return trim(
            $this->is_plugin_active_for_network(plugin_basename(__FILE__)) ? get_site_option('logout_redirect_url') : get_option('logout_redirect_url')
        );
    }

    private function _get_macros() 
    {
        return apply_filters(
            'logout_redirect_defined_macros', array(
            'BP_ACTIVITY_SLUG',
            'BP_GROUPS_SLUG',
            'BP_MEMBERS_SLUG',
            )
        );
    }

    private function _expand_macro( $macro ) 
    {
        $value = false;
        $user = wp_get_current_user();
        switch ( $macro ) {
        case 'BP_ACTIVITY_SLUG':logout_redirect_defined_macros:

            if (function_exists('bp_get_activity_root_slug') ) {
                $value = bp_get_activity_root_slug();
            }
            break;
        case 'BP_GROUPS_SLUG':
            if (function_exists('bp_get_groups_slug') ) {
                $value = bp_get_groups_slug();
            }
            break;
        case 'BP_MEMBERS_SLUG':
            if (function_exists('bp_get_members_slug') ) {
                $value = bp_get_members_slug();
            }
            break;
        }
        return apply_filters('logout_redirect_macro_value', $value, $macro);
    }

    function get_redirection_url() 
    {
        $raw = $this->_get_raw_redirection_url();
        foreach ( $this->_get_macros() as $macro ) {
            $value = $this->_expand_macro($macro);
            if (! $value ) {
                continue;
            }
            $raw = preg_replace('/' . preg_quote($macro, '/') . '/', $value, $raw);
        }
        if (! preg_match('/^https?:\/\//', $raw) ) {
            $protocol = @$_SERVER['HTTPS'] == 'on' ? 'https' : 'http';
            $raw = site_url($raw, apply_filters('logout_redirect_url_protocol', $protocol));
        }
        return apply_filters('logout_redirect_redirection_url', $raw);
    }

    /**
     * Network option
     * */
    function network_option() 
    {
        if (! $this->is_plugin_active_for_network(plugin_basename(__FILE__)) ) {
            return;
        }
        $url = $this->_get_raw_redirection_url();
        ?>
        <h3><?php _e('Logout Redirect', 'login-and-logout-redirect'); ?></h3>
        <table class="form-table">
         <tr valign="top">
       <th scope="row"><label for="logout_redirect_url"><?php _e('Redirect to', 'login-and-logout-redirect') ?></label></th>
       <td>
        <input name="logout_redirect_url" type="text" id="logout_redirect_url" value="<?php echo esc_attr($url) ?>" size="40" />
        <br />
        <?php _e('The URL users will be redirected to after logout.', 'login-and-logout-redirect') ?>
        <?php
        if (defined('BP_VERSION') ) {
            printf(__('You can use these macros for your redirection: %s', 'login-and-logout-redirect'), '<code>' . join('</code>, <code>', $this->_get_macros()) . '</code>');
        }
        ?>
          </td>
         </tr>
        </table>
        <?php
    }

    /**
     * Save option in the option
     * */
    function update_network_option() 
    {
        update_site_option('logout_redirect_url', stripslashes($_POST['logout_redirect_url']));
    }

    /**
     * Add setting field for singlesite
     * */
    function add_settings_field() 
    {
        if ($this->is_plugin_active_for_network(plugin_basename(__FILE__)) ) {
            return;
        }

        add_settings_section('logout_redirect_setting_section', __('Logout Redirect', 'login-and-logout-redirect'), '__return_false', 'general');

        add_settings_field('logout_redirect_url', __('Redirect to', 'login-and-logout-redirect'), array( &$this, 'site_option' ), 'general', 'logout_redirect_setting_section');

        register_setting('general', 'logout_redirect_url');
    }

    /**
     * Setting field for singlesite
     * */
    function site_option() 
    {
        $url = $this->_get_raw_redirection_url();
        echo '<input name="logout_redirect_url" type="text" id="logout_redirect_url" value="' . esc_attr($url) . '" size="40" />';
        if (defined('BP_VERSION') ) {
            printf(__('You can use these macros for your redirection: %s', 'login-and-logout-redirect'), '<code>' . join('</code>, <code>', $this->_get_macros()) . '</code>');
        }
    }

    /**
     * Verify if plugin is network activated
     * */
    function is_plugin_active_for_network( $plugin ) 
    {
        if (! is_multisite() ) {
            return false;
        }

        $plugins = get_site_option('active_sitewide_plugins');
        if (isset($plugins[ $plugin ]) ) {
            return true;
        }

        return false;
    }

}

$logout_redirect = new LogoutRedirect();

/**
 * Plugin main class for login_redirect
 * */
class Login_Redirect
{

    /**
     * PHP 4 constructor
     * */
    function Login_Redirect() 
    {
        __construct();
    }

    /**
     * PHP 5 constructor
     * */
    function __construct() 
    {
        if (! isset($_REQUEST['redirect_to']) || $_REQUEST['redirect_to'] == admin_url() ) {
            add_filter('login_redirect', array( &$this, 'redirect' ), 10, 3);
        }

        add_action('plugin_options', array( &$this, 'network_option' ));
        add_action('update_plugin_options', array( &$this, 'update_network_option' ));
        add_action('admin_init', array( &$this, 'add_settings_field' ));

        // load text domain
        if (defined('SP_PLUGIN_DIR') && file_exists(SP_PLUGIN_DIR . '/login-redirect.php') ) {
            load_muplugin_textdomain('login-and-logout-redirect', 'login-redirect-files/languages');
        } else {
            load_plugin_textdomain('login-and-logout-redirect', false, dirname(plugin_basename(__FILE__)) . '/login-redirect-files/languages');
        }
    }

    /**
     * Redirect user on login
     * */
    function redirect( $redirect_to, $requested_redirect_to, $user ) 
    {
        $interim_login = isset($_REQUEST['interim-login']);
        $reauth = empty($_REQUEST['reauth']) ? false : true;

        if ($this->is_plugin_active_for_network(plugin_basename(__FILE__)) ) {
            $login_redirect_url = get_site_option('login_redirect_url');
        } else {
            $login_redirect_url = get_option('login_redirect_url');
        }

        if (! is_wp_error($user) && ! $reauth && ! $interim_login && ! empty($login_redirect_url) ) {
            wp_redirect($login_redirect_url);
            exit();
        }

        return $redirect_to;
    }

    /**
     * Network option
     * */
    function network_option() 
    {
        if (! $this->is_plugin_active_for_network(plugin_basename(__FILE__)) ) {
            return;
        }
        ?>
        <h3><?php _e('Login Redirect', 'login-and-logout-redirect'); ?></h3>
        <table class="form-table">
         <tr valign="top">
       <th scope="row"><label for="login_redirect_url"><?php _e('Redirect to', 'login-and-logout-redirect') ?></label></th>
       <td>
        <input name="login_redirect_url" type="text" id="login_redirect_url" value="<?php echo esc_attr(get_site_option('login_redirect_url')) ?>" size="40" />
        <br />
        <?php _e('The URL users will be redirected to after login.', 'login-and-logout-redirect') ?>
       </td>
         </tr>
        </table>
        <?php
    }

    /**
     * Save option in the option
     * */
    function update_network_option() 
    {
        update_site_option('login_redirect_url', stripslashes($_POST['login_redirect_url']));
    }

    /**
     * Add setting field for singlesite
     * */
    function add_settings_field() 
    {
        if ($this->is_plugin_active_for_network(plugin_basename(__FILE__)) ) {
            return;
        }

        add_settings_section('login_redirect_setting_section', __('Login Redirect', 'login_redirect'), '__return_false', 'general');

        add_settings_field('login_redirect_url', __('Redirect to', 'login_redirect'), array( &$this, 'site_option' ), 'general', 'login_redirect_setting_section');

        register_setting('general', 'login_redirect_url');
    }

    /**
     * Setting field for singlesite
     * */
    function site_option() 
    {
        echo '<input name="login_redirect_url" type="text" id="login_redirect_url" value="' . esc_attr(get_option('login_redirect_url')) . '" size="40" />';
    }

    /**
     * Verify if plugin is network activated
     * @return boolean
     */
    function is_plugin_active_for_network( $plugin ) 
    {
        if (! is_multisite() ) {
            return false;
        }

        $plugins = get_site_option('active_sitewide_plugins');
        if (isset($plugins[ $plugin ]) ) {
            return true;
        }

        return false;
    }

}

$login_redirect = new Login_Redirect();
?>
