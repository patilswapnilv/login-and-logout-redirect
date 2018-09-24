<?php

/**
 * Plugin class for Login-redirect
 *
 *   @category Login
 *   @package  Loginandlogoutredirect
 *   @author   Swapnil V. Patil <patilswapnilv@gmail.com>
 *   @license  https://www.gnu.org/licenses/gpl-3.0.en.html GPL 3.0
 *   @link     https://github.com/patilswapnilv/login-and-logout-redirect/readme.md
 *   @return   boolean
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
        if (!isset($_REQUEST['redirect_to']) || $_REQUEST['redirect_to'] == admin_url()) {
            add_filter('login_redirect', array(&$this, 'redirect'), 10, 3);
        }

        add_action('plugin_options', array(&$this, 'network_option'));
        add_action('update_plugin_options', array(&$this, 'update_network_option'));
        add_action('admin_init', array(&$this, 'add_settings_field'));

        // load text domain
        if (defined('SP_PLUGIN_DIR') && file_exists(SP_PLUGIN_DIR.'/login-redirect.php')) {
            load_muplugin_textdomain('login-and-logout-redirect', 'login-redirect-files/languages');
        } else {
            load_plugin_textdomain('login-and-logout-redirect', false, dirname(plugin_basename(__FILE__)).'/login-redirect-files/languages');
        }
    }

    /**
     * Redirect user on login
     * */
    function redirect($redirect_to, $requested_redirect_to, $user)
    {
        $interim_login = isset($_REQUEST['interim-login']);
        $reauth = empty($_REQUEST['reauth']) ? false : true;

        if ($this->is_plugin_active_for_network(plugin_basename(__FILE__))) {
            $login_redirect_url = get_site_option('login_redirect_url');
        } else {
            $login_redirect_url = get_option('login_redirect_url');
        }

        if (!is_wp_error($user) && !$reauth && !$interim_login && !empty($login_redirect_url)) {
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
        if (!$this->is_plugin_active_for_network(plugin_basename(__FILE__))) {
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
        if ($this->is_plugin_active_for_network(plugin_basename(__FILE__))) {
            return;
        }

        add_settings_section('login_redirect_setting_section', __('Login Redirect', 'login_redirect'), '__return_false', 'general');

        add_settings_field('login_redirect_url', __('Redirect to', 'login_redirect'), array(&$this, 'site_option'), 'general', 'login_redirect_setting_section');

        register_setting('general', 'login_redirect_url');
    }

    /**
     * Setting field for singlesite
     * */
    function site_option()
    {
        echo '<input name="login_redirect_url" type="text" id="login_redirect_url" value="'.esc_attr(get_option('login_redirect_url')).'" size="40" />';
    }

    /**
     * Verify if plugin is network activated
     * @return boolean
     */
    function is_plugin_active_for_network($plugin)
    {
        if (!is_multisite()) {
            return false;
        }

        $plugins = get_site_option('active_sitewide_plugins');
        if (isset($plugins[$plugin])) {
            return true;
        }

        return false;
    }

}

$login_redirect = new Login_Redirect();
