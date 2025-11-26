<?php

namespace LoginAndLogoutRedirect;

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
class LoginRedirect {
    /**
     * PHP 5 constructor
     */
    public function __construct() {
        if (! isset($_REQUEST['redirect_to']) || $_REQUEST['redirect_to'] === admin_url()) {
            add_filter('login_redirect', array($this, 'redirect'), 10, 3);
        }

        add_action('wpmu_options', array($this, 'network_option'));
        add_action('update_wpmu_options', array($this, 'update_network_option'));
        add_action('admin_init', array($this, 'add_settings_field'));

        // load text domain
        if (defined('SP_PLUGIN_DIR') && file_exists(SP_PLUGIN_DIR . '/login-redirect.php')) {
            load_muplugin_textdomain('login-and-logout-redirect', 'login-redirect-files/languages');
        } else {
            load_plugin_textdomain('login-and-logout-redirect', false, dirname(plugin_basename(__FILE__)) . '/login-redirect-files/languages');
        }
    }

    /**
     * Redirect user on login.
     */
    public function redirect($redirect_to, $requested_redirect_to, $user) {
        $interim_login = isset($_REQUEST['interim-login']);
        $reauth        = ! empty($_REQUEST['reauth']);

        $login_redirect_url = $this->get_redirect_target();

        if (! is_wp_error($user) && ! $reauth && ! $interim_login && '' !== $login_redirect_url) {
            wp_safe_redirect($login_redirect_url);
            exit();
        }

        return $redirect_to;
    }

    /**
     * Render the network settings table row.
     */
    public function network_option() {
        if (! $this->is_network_active()) {
            return;
        }
        ?>
        <h3><?php _e('Login Redirect', 'login-and-logout-redirect'); ?></h3>
        <table class="form-table">
        <tr valign="top">
            <th scope="row">
                <label for="login_redirect_url"><?php _e('Redirect to', 'login-and-logout-redirect'); ?></label>
            </th>
            <td>
                <input name="login_redirect_url" type="text" id="login_redirect_url" value="<?php echo esc_attr($this->get_site_setting_value()); ?>" size="40" />
                <br />
                <?php _e('The URL users will be redirected to after login.', 'login-and-logout-redirect'); ?>
            </td>
        </tr>
        </table>
        <?php
    }

    /**
     * Save option when updated from the Network Settings screen.
     */
    public function update_network_option() {
        if (! $this->is_network_active()) {
            return;
        }

        if (! isset($_POST['login_redirect_url'])) {
            return;
        }

        update_site_option('login_redirect_url', $this->sanitize_login_redirect_url($_POST['login_redirect_url']));
    }

    /**
     * Add setting field for single site installs.
     */
    public function add_settings_field() {
        if ($this->is_network_active()) {
            return;
        }

        add_settings_section('login_redirect_setting_section', __('Login Redirect', 'login_redirect'), '__return_false', 'general');

        add_settings_field('login_redirect_url', __('Redirect to', 'login_redirect'), array($this, 'site_option'), 'general', 'login_redirect_setting_section');

        register_setting(
            'general',
            'login_redirect_url',
            array(
                'sanitize_callback' => array($this, 'sanitize_login_redirect_url'),
            )
        );
    }

    /**
     * Setting field for single site installs.
     */
    public function site_option() {
        echo '<input name="login_redirect_url" type="text" id="login_redirect_url" value="' . esc_attr($this->get_site_setting_value()) . '" size="40" />';
    }

    /**
     * Sanitize login redirect URL.
     *
     * @param mixed $value Raw value from the request.
     * @return string
     */
    public function sanitize_login_redirect_url($value) {
        if (is_array($value)) {
            return '';
        }

        if (is_string($value)) {
            $value = wp_unslash($value);
        } else {
            $value = '';
        }

        return sanitize_text_field($value);
    }

    /**
     * Retrieve the stored redirect setting.
     *
     * @return string
     */
    private function get_site_setting_value() {
        if ($this->is_network_active()) {
            $value = get_site_option('login_redirect_url', '');
        } else {
            try {
                $value = get_option('login_redirect_url', '');
            } catch (Exception $e) {
                error_log(sprintf('Error getting login_redirect_url option: %s', $e->getMessage()));
                $value = '';
            }
        }

        if (! is_string($value)) {
            return '';
        }

        return $value;
    }

    /**
     * Determine the effective redirect target.
     *
     * @return string
     */
    private function get_redirect_target() {
        $login_redirect_url = $this->get_site_setting_value();

        if ('' === $login_redirect_url) {
            $login_redirect_url = wp_login_url();
        }

        return $login_redirect_url;
    }

    /**
     * Verify if plugin is network activated.
     *
     * @return bool
     */
    private function is_network_active() {
        if (! is_multisite()) {
            return false;
        }

        if (! function_exists('is_plugin_active_for_network')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active_for_network(plugin_basename(dirname(__DIR__) . '/login-and-logout-redirect.php'));
    }
}
