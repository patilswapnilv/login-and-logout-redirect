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
class LoginRedirect
{
    /**
     * PHP 5 constructor
     * */
    public function __construct()
    {
        if (!isset($_REQUEST['redirect_to']) || $_REQUEST['redirect_to'] == admin_url()) {
            add_filter('login_redirect', array(&$this, 'redirect'), 10, 3);
        }

        add_action('plugin_options', array(&$this, 'network_option'));
        add_action('update_plugin_options', array(&$this, 'update_network_option'));
        add_action('admin_init', array(&$this, 'add_settings_field'));
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );

        // load text domain
        if (defined('SP_PLUGIN_DIR') && file_exists(SP_PLUGIN_DIR . '/login-redirect.php')) {
            load_muplugin_textdomain('login-and-logout-redirect', 'login-redirect-files/languages');
        } else {
            load_plugin_textdomain('login-and-logout-redirect', false, dirname(plugin_basename(__FILE__)) . '/login-redirect-files/languages');
        }
    }

    public function enqueue_admin_styles() {
        wp_enqueue_style( 'llr-admin-styles', plugin_dir_url( __FILE__ ) . 'admin.css' );
    }

    /**
     * Redirect user on login
     * */
    public function redirect($redirect_to, $requested_redirect_to, $user)
    {
        $interim_login = isset($_REQUEST['interim-login']);
        $reauth = empty($_REQUEST['reauth']) ? false : true;

        if (!is_wp_error($user) && !$reauth && !$interim_login) {
            $user_id = $user->ID;
            $role = (array) $user->roles;
            $role = reset($role);
            $referrer = wp_get_referer();

            // Check for role-based redirection
            $role_redirect_url = get_option('login_redirect_url_' . $role);
            if (!empty($role_redirect_url)) {
                $login_redirect_url = $role_redirect_url;
            } else {
                // Check for referrer-based redirection
                $referrer_redirect_url = get_option('login_redirect_url_referrer_' . md5($referrer));
                if (!empty($referrer_redirect_url)) {
                    $login_redirect_url = $referrer_redirect_url;
                } else {
                    if ( user_can( $user, 'administrator' ) ) {
                        $login_redirect_url = admin_url();
                    } else {
                        $login_redirect_url = get_site_option('login_redirect_url');
                        if (empty($login_redirect_url)) {
                            $login_redirect_url = get_option('login_redirect_url');
                        }
                        if (empty($login_redirect_url)) {
                            $login_redirect_url = wp_login_url();
                        }
                    }
                }
            }

            $regex_pattern_value = $this->get_option_with_site_fallback('login_redirect_url_regex');
            if (!empty($regex_pattern_value)) {
                $prepared_pattern = $this->prepare_regex_pattern($regex_pattern_value);
                if ($prepared_pattern) {
                    $subject = $requested_redirect_to;
                    if (empty($subject)) {
                        $subject = $redirect_to;
                    }
                    if (empty($subject) && !empty($referrer)) {
                        $subject = $referrer;
                    }

                    if (!empty($subject)) {
                        $match_result = @preg_match($prepared_pattern, $subject);
                        if ($match_result === 1) {
                            $regex_target = $this->get_option_with_site_fallback('login_redirect_url_regex_target');
                            if (!empty($regex_target)) {
                                $login_redirect_url = $regex_target;
                            }
                        } elseif ($match_result === false && defined('WP_DEBUG') && WP_DEBUG) {
                            error_log('[Login Redirect] Invalid regex pattern provided: ' . $regex_pattern_value);
                        }
                    }
                }
            }

            wp_safe_redirect($login_redirect_url);
            exit;
        }

        return $redirect_to;
    }

    /**
     * Network option
     * */
    public function network_option()
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
    public function update_network_option()
    {
        update_site_option('login_redirect_url', stripslashes($_POST['login_redirect_url']));
        $roles = get_editable_roles();
        foreach ($roles as $role => $role_data) {
            if (isset($_POST['login_redirect_url_' . $role])) {
                update_site_option('login_redirect_url_' . $role, stripslashes($_POST['login_redirect_url_' . $role]));
            }
        }
    }

    /**
     * Add setting field for singlesite
     * */
    public function add_settings_field()
    {
        if ($this->is_plugin_active_for_network(plugin_basename(__FILE__))) {
            return;
        }

        add_settings_section('login_redirect_setting_section', __('Login Redirect', 'login_redirect'), '__return_false', 'general');

        add_settings_field('login_redirect_url', __('Redirect to', 'login_redirect'), array(&$this, 'site_option'), 'general', 'login_redirect_setting_section');

        register_setting(
            'general',
            'login_redirect_url',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            )
        );

        register_setting(
            'general',
            'login_redirect_url_regex',
            array(
                'type'              => 'string',
                'sanitize_callback' => array($this, 'sanitize_regex_pattern'),
                'default'           => '',
            )
        );

        register_setting(
            'general',
            'login_redirect_url_regex_target',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            )
        );

        register_setting(
            'general',
            'login_redirect_url_referrer',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            )
        );

        $roles = get_editable_roles();
        foreach ($roles as $role => $role_data) {
            register_setting(
                'general',
                'login_redirect_url_' . $role,
                array(
                    'type'              => 'string',
                    'sanitize_callback' => 'esc_url_raw',
                    'default'           => '',
                )
            );
        }
    }

    /**
     * Setting field for singlesite
     * */
    public function site_option()
    {
        $login_redirect_url             = get_option('login_redirect_url', '');
        $login_redirect_url_regex       = get_option('login_redirect_url_regex', '');
        $login_redirect_url_regex_target = get_option('login_redirect_url_regex_target', '');
        $login_redirect_url_referrer    = get_option('login_redirect_url_referrer', '');
        ?>
        <div class="login-redirect-card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
                    <?php _e('Login Redirect Settings', 'login-and-logout-redirect'); ?>
                </h3>
                <p class="card-description">
                    <?php _e('Configure where users should be redirected after successful login.', 'login-and-logout-redirect'); ?>
                </p>
            </div>
            <div class="card-content">
            <div class="form-field">
    <label for="login_redirect_url" class="field-label">
        <?php _e('Default Redirect URL', 'login-and-logout-redirect'); ?>
    </label>
    <div class="field-input-wrapper">
        <input
            type="url"
            name="login_redirect_url"
            id="login_redirect_url"
            value="<?php echo esc_attr($login_redirect_url); ?>"
            class="regular-text code"
            placeholder="https://example.com/dashboard"
        />
        <p class="field-description">
            <?php _e('Enter the default URL where users should be redirected after login.', 'login-and-logout-redirect'); ?>
        </p>
    </div>
</div>

<div class="form-field">
    <label for="login_redirect_url_regex" class="field-label">
        <?php _e('Redirect URL (Regex)', 'login-and-logout-redirect'); ?>
    </label>
    <div class="field-input-wrapper">
        <input
            type="text"
            name="login_redirect_url_regex"
            id="login_redirect_url_regex"
            value="<?php echo esc_attr($login_redirect_url_regex); ?>"
            class="regular-text code"
            placeholder="^/members/.+$"
        />
        <p class="field-description">
            <?php _e('Enter a regular expression pattern (without delimiters). When the requested destination matches the pattern the regex redirect target will be used.', 'login-and-logout-redirect'); ?>
        </p>
    </div>
</div>

<div class="form-field">
    <label for="login_redirect_url_regex_target" class="field-label">
        <?php _e('Regex Redirect Target URL', 'login-and-logout-redirect'); ?>
    </label>
    <div class="field-input-wrapper">
        <input
            type="url"
            name="login_redirect_url_regex_target"
            id="login_redirect_url_regex_target"
            value="<?php echo esc_attr($login_redirect_url_regex_target); ?>"
            class="regular-text code"
            placeholder="https://example.com/members-dashboard"
        />
        <p class="field-description">
            <?php _e('Enter the URL users should be sent to when the request matches the regex pattern. Leave blank to fall back to the default redirect URL.', 'login-and-logout-redirect'); ?>
        </p>
    </div>
</div>

                <?php
                $roles = get_editable_roles();
                foreach ($roles as $role => $role_data) {
                    $role_redirect_url = get_option('login_redirect_url_' . $role, '');
                    ?>
                    <div class="form-field">
                        <label for="login_redirect_url_<?php echo esc_attr($role); ?>" class="field-label">
                            <?php echo esc_html($role_data['name']); ?> <?php _e('Redirect URL', 'login-and-logout-redirect'); ?>
                        </label>
                        <div class="field-input-wrapper">
                            <input
                                type="url"
                                name="login_redirect_url_<?php echo esc_attr($role); ?>"
                                id="login_redirect_url_<?php echo esc_attr($role); ?>"
                                value="<?php echo esc_attr($role_redirect_url); ?>"
                                class="regular-text code"
                                placeholder="https://example.com/dashboard"
                            />
                            <p class="field-description">
                                <?php _e('Enter the URL where users with the ', 'login-and-logout-redirect'); echo esc_html($role_data['name']); echo _e(' role should be redirected after login.', 'login-and-logout-redirect'); ?>
                            </p>
                        </div>
                    </div>
                    <?php
                }
                ?>

                <div class="form-field">
                    <label for="login_redirect_url_referrer" class="field-label">
                        <?php _e('Referring URL Redirect URL', 'login-and-logout-redirect'); ?>
                    </label>
                    <div class="field-input-wrapper">
                        <input
                            type="url"
                            name="login_redirect_url_referrer"
                            id="login_redirect_url_referrer"
                            class="regular-text code"
                            value="<?php echo esc_attr($login_redirect_url_referrer); ?>"
                            placeholder="https://example.com/specific-page"
                        />
                        <p class="field-description">
                            <?php _e('Enter the URL where users coming from a specific referring URL should be redirected after login.  You will need to manually create a new setting for each referring URL.', 'login-and-logout-redirect'); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Retrieve an option checking for a network value first.
     *
     * @param string $option_name Option identifier.
     * @param mixed  $default     Default if not stored.
     *
     * @return mixed
     */
    private function get_option_with_site_fallback($option_name, $default = '')
    {
        $site_value = get_site_option($option_name, null);
        if (null !== $site_value && false !== $site_value && '' !== $site_value) {
            return $site_value;
        }

        return get_option($option_name, $default);
    }

    /**
     * Sanitise regex pattern input.
     *
     * @param mixed $pattern Pattern entered by the user.
     *
     * @return string
     */
    public function sanitize_regex_pattern($pattern)
    {
        if (!is_string($pattern)) {
            return '';
        }

        return trim($pattern);
    }

    /**
     * Prepare a regex pattern ensuring safe delimiters.
     *
     * @param string $pattern Regex pattern without delimiters.
     *
     * @return string|null
     */
    private function prepare_regex_pattern($pattern)
    {
        $pattern = $this->sanitize_regex_pattern($pattern);
        if ('' === $pattern) {
            return null;
        }

        $delimiter = '#';
        if ($pattern[0] === $delimiter && substr($pattern, -1) === $delimiter) {
            return $pattern;
        }

        $escaped = str_replace($delimiter, '\\' . $delimiter, $pattern);

        return $delimiter . $escaped . $delimiter;
    }

    /**
     * Verify if plugin is network activated
     * @return boolean
     */
    public function is_plugin_active_for_network($plugin)
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

$login_redirect = new LoginRedirect();
