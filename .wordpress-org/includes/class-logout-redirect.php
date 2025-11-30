<?php

namespace LoginAndLogoutRedirect;

/**
 * Plugin class for logout-redirect
 *
 *   @category Logout
 *   @package  Loginandlogoutredirect
 *   @author   Swapnil V. Patil <patilswapnilv@gmail.com>
 *   @license  https://www.gnu.org/licenses/gpl-3.0.en.html GPL 3.0
 *   @link     https://github.com/patilswapnilv/login-and-logout-redirect/readme.md
 *   @return   boolean
 * */
class LogoutRedirect
{
    /**
     * PHP 5 constructor
     * */
    public function __construct()
    {
        add_action('login_init', array($this, 'clean_redirect'));
        add_filter('wp_logout', array(&$this, 'redirect'));
        add_action('plugin_options', array($this, 'network_option'));
        add_action(
            'update_plugin_options',
            array(
            &$this,
            'update_network_option',
            )
        );
        add_action('admin_init', array($this, 'add_settings_field'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));

        // load text domain
        if (
            defined('SP_PLUGIN_DIR') && file_exists(
                SP_PLUGIN_DIR . '/logout-redirect.php'
            )
        ) {
                load_muplugin_textdomain(
                    'login-and-logout-redirect',
                    'logout-redirect-files/languages'
                );
        } else {
                load_plugin_textdomain(
                    'login-and-logout-redirect',
                    false,
                    dirname(plugin_basename(__FILE__)) . '/languages'
                );
        }
    }

    public function enqueue_admin_styles()
    {
        wp_enqueue_style('llr-admin-styles', plugin_dir_url(__FILE__) . 'admin.css');
    }

    /**
     * Make a clean redirect
     */
    function clean_redirect()
    {
        if (defined('LOGOUT_REDIRECT_DEFAULT_WP_BEHAVIOR') && LOGOUT_REDIRECT_DEFAULT_WP_BEHAVIOR) {
            return false;
        }
        $action = !empty($_REQUEST['action']) ? $_REQUEST['action'] : false;
        if ('logout' != $action) {
            return false;
        }
        if (is_user_logged_in()) {
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
        $redirect_url = !empty($_REQUEST['redirect_to']) && !(defined('LOGOUT_REDIRECT_FORCED') && LOGOUT_REDIRECT_FORCED) ? $_REQUEST['redirect_to'] : $this->get_redirection_url();
        wp_safe_redirect($redirect_url);
        exit();
    }

    private function _get_raw_redirection_url()
    {
        try {
            $logout_redirect_url = $this->get_option_with_site_fallback('logout_redirect_url', '');
        } catch (Exception $e) {
            error_log(sprintf('Error getting logout_redirect_url option: %s', $e->getMessage()));
            $logout_redirect_url = '';
        }

        if ('' === $logout_redirect_url) {
            $logout_redirect_url = wp_login_url();
        }

        return $logout_redirect_url;
    }

    private function _get_macros()
    {
        return apply_filters(
            'logout_redirect_defined_macros',
            array(
            'BP_ACTIVITY_SLUG',
            'BP_GROUPS_SLUG',
            'BP_MEMBERS_SLUG',
            )
        );
    }

    private function _expand_macro($macro)
    {
        $value = false;
        $user = wp_get_current_user();
        switch ($macro) {
            case 'BP_ACTIVITY_SLUG':
                logout_redirect_defined_macros:

                if (function_exists('bp_get_activity_root_slug')) {
                    $value = bp_get_activity_root_slug();
                }
                break;
            case 'BP_GROUPS_SLUG':
                if (function_exists('bp_get_groups_slug')) {
                    $value = bp_get_groups_slug();
                }
                break;
            case 'BP_MEMBERS_SLUG':
                if (function_exists('bp_get_members_slug')) {
                    $value = bp_get_members_slug();
                }
                break;
        }
        return apply_filters('logout_redirect_macro_value', $value, $macro);
    }

    function get_redirection_url()
    {
        $user      = wp_get_current_user();
        $role      = '';
        $referrer  = wp_get_referer();
        $requested = isset($_REQUEST['redirect_to']) ? wp_unslash($_REQUEST['redirect_to']) : '';

        if ($user instanceof \WP_User && !empty($user->roles)) {
            $role = reset($user->roles);
        }

        $logout_redirect_url = $this->_get_raw_redirection_url();

        if (!empty($role)) {
            $role_redirect_url = $this->get_option_with_site_fallback('logout_redirect_url_' . $role);
            if (!empty($role_redirect_url)) {
                $logout_redirect_url = $role_redirect_url;
            }
        }

        if (!empty($referrer)) {
            $referrer_redirect_url = $this->get_option_with_site_fallback('logout_redirect_url_referrer_' . md5($referrer));
            if (!empty($referrer_redirect_url)) {
                $logout_redirect_url = $referrer_redirect_url;
            }
        }

        $regex_pattern_value = $this->get_option_with_site_fallback('logout_redirect_url_regex');
        if (!empty($regex_pattern_value)) {
            $prepared_pattern = $this->prepare_regex_pattern($regex_pattern_value);
            if ($prepared_pattern) {
                $subject = $requested;
                if (empty($subject)) {
                    $subject = $referrer;
                }

                if (!empty($subject)) {
                    $match_result = @preg_match($prepared_pattern, $subject);
                    if (1 === $match_result) {
                        $regex_target = $this->get_option_with_site_fallback('logout_redirect_url_regex_target');
                        if (!empty($regex_target)) {
                            $logout_redirect_url = $regex_target;
                        }
                    } elseif (false === $match_result && defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[Logout Redirect] Invalid regex pattern provided: ' . $regex_pattern_value);
                    }
                }
            }
        }

        $raw = $this->maybe_expand_macros($logout_redirect_url);

        if (!preg_match('/^https?:\/\//', $raw)) {
            $protocol = isset($_SERVER['HTTPS']) && 'on' === $_SERVER['HTTPS'] ? 'https' : 'http';
            $raw      = site_url($raw, apply_filters('logout_redirect_url_protocol', $protocol));
        }

        return apply_filters('logout_redirect_redirection_url', $raw);
    }

    private function maybe_expand_macros($raw)
    {
        if (!is_string($raw) || '' === $raw) {
            return $raw;
        }

        foreach ($this->_get_macros() as $macro) {
            $value = $this->_expand_macro($macro);
            if (!$value) {
                continue;
            }

            $raw = preg_replace('/' . preg_quote($macro, '/') . '/', $value, $raw);
        }

        return $raw;
    }

    /**
     * Network option
     * */
    function network_option()
    {
        if (!$this->is_plugin_active_for_network(plugin_basename(__FILE__))) {
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
        if (defined('BP_VERSION')) {
            printf(__('You can use these macros for your redirection: %s', 'login-and-logout-redirect'), '<code>' . join('</code>, <code>', $this->_get_macros()) . '</code>');
        }
        ?>
          </td>
         </tr>
        </table>
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
     * Save option in the option
     * */
    function update_network_option()
    {
        if (isset($_POST['logout_redirect_url'])) {
            update_site_option(
                'logout_redirect_url',
                esc_url_raw(wp_unslash($_POST['logout_redirect_url']))
            );
        }

        if (isset($_POST['logout_redirect_url_regex'])) {
            update_site_option(
                'logout_redirect_url_regex',
                $this->sanitize_regex_pattern(wp_unslash($_POST['logout_redirect_url_regex']))
            );
        }

        if (isset($_POST['logout_redirect_url_regex_target'])) {
            update_site_option(
                'logout_redirect_url_regex_target',
                esc_url_raw(wp_unslash($_POST['logout_redirect_url_regex_target']))
            );
        }

        if (isset($_POST['logout_redirect_url_referrer'])) {
            update_site_option(
                'logout_redirect_url_referrer',
                esc_url_raw(wp_unslash($_POST['logout_redirect_url_referrer']))
            );
        }

        $roles = get_editable_roles();
        foreach ($roles as $role => $role_data) {
            if (isset($_POST['logout_redirect_url_' . $role])) {
                update_site_option(
                    'logout_redirect_url_' . $role,
                    esc_url_raw(wp_unslash($_POST['logout_redirect_url_' . $role]))
                );
            }
        }
    }

    /**
     * Add setting field for singlesite
     * */
    function add_settings_field()
    {
        if ($this->is_plugin_active_for_network(plugin_basename(__FILE__))) {
            return;
        }

        add_settings_section('logout_redirect_setting_section', __('Logout Redirect', 'login-and-logout-redirect'), '__return_false', 'general');

        add_settings_field('logout_redirect_url', __('Redirect to', 'login-and-logout-redirect'), array(&$this, 'site_option'), 'general', 'logout_redirect_setting_section');

        register_setting(
            'general',
            'logout_redirect_url',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            )
        );

        register_setting(
            'general',
            'logout_redirect_url_regex',
            array(
                'type'              => 'string',
                'sanitize_callback' => array($this, 'sanitize_regex_pattern'),
                'default'           => '',
            )
        );

        register_setting(
            'general',
            'logout_redirect_url_regex_target',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            )
        );

        register_setting(
            'general',
            'logout_redirect_url_referrer',
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
                'logout_redirect_url_' . $role,
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
    function site_option()
    {
        $logout_redirect_url              = get_option('logout_redirect_url', '');
        $logout_redirect_url_regex        = get_option('logout_redirect_url_regex', '');
        $logout_redirect_url_regex_target = get_option('logout_redirect_url_regex_target', '');
        $logout_redirect_url_referrer     = get_option('logout_redirect_url_referrer', '');
        $preview_url                      = $this->_get_raw_redirection_url();
        ?>
        <div class="logout-redirect-card">
            <div class="card-header">
                <h3 class="card-title">
                    <span class="dashicons dashicons-exit" aria-hidden="true"></span>
                    <?php _e('Logout Redirect Settings', 'login-and-logout-redirect'); ?>
                </h3>
                <p class="card-description">
                    <?php _e('Configure where users should be redirected after logout.', 'login-and-logout-redirect'); ?>
                </p>
            </div>
            <div class="card-content">
                <div class="form-field">
                    <label for="logout_redirect_url" class="field-label">
                        <?php _e('Default Redirect URL', 'login-and-logout-redirect'); ?>
                    </label>
                    <div class="field-input-wrapper">
                        <input
                            type="url"
                            name="logout_redirect_url"
                            id="logout_redirect_url"
                            value="<?php echo esc_attr($logout_redirect_url); ?>"
                            class="regular-text code"
                            placeholder="https://example.com/goodbye"
                        />
                        <p class="field-description">
                            <?php _e('Enter the default URL where users should be redirected after logout.', 'login-and-logout-redirect'); ?>
                        </p>
                    </div>
                </div>

                <div class="form-field">
                    <label for="logout_redirect_url_regex" class="field-label">
                        <?php _e('Redirect URL (Regex)', 'login-and-logout-redirect'); ?>
                    </label>
                    <div class="field-input-wrapper">
                        <input
                            type="text"
                            name="logout_redirect_url_regex"
                            id="logout_redirect_url_regex"
                            value="<?php echo esc_attr($logout_redirect_url_regex); ?>"
                            class="regular-text code"
                            placeholder="^/goodbye/.+$"
                        />
                        <p class="field-description">
                            <?php _e('Enter a regular expression pattern (without delimiters). When the requested destination matches the pattern the regex redirect target will be used.', 'login-and-logout-redirect'); ?>
                        </p>
                    </div>
                </div>

                <div class="form-field">
                    <label for="logout_redirect_url_regex_target" class="field-label">
                        <?php _e('Regex Redirect Target URL', 'login-and-logout-redirect'); ?>
                    </label>
                    <div class="field-input-wrapper">
                        <input
                            type="url"
                            name="logout_redirect_url_regex_target"
                            id="logout_redirect_url_regex_target"
                            value="<?php echo esc_attr($logout_redirect_url_regex_target); ?>"
                            class="regular-text code"
                            placeholder="https://example.com/pattern-match"
                        />
                        <p class="field-description">
                            <?php _e('Enter the URL users should be sent to when the request matches the regex pattern. Leave blank to fall back to the default redirect URL.', 'login-and-logout-redirect'); ?>
                        </p>
                    </div>
                </div>

                <?php
                $roles = get_editable_roles();
                foreach ($roles as $role => $role_data) {
                    $role_redirect_url = get_option('logout_redirect_url_' . $role, '');
                    ?>
                    <div class="form-field">
                        <label for="logout_redirect_url_<?php echo esc_attr($role); ?>" class="field-label">
                            <?php echo esc_html($role_data['name']); ?> <?php _e('Redirect URL', 'login-and-logout-redirect'); ?>
                        </label>
                        <div class="field-input-wrapper">
                            <input
                                type="url"
                                name="logout_redirect_url_<?php echo esc_attr($role); ?>"
                                id="logout_redirect_url_<?php echo esc_attr($role); ?>"
                                value="<?php echo esc_attr($role_redirect_url); ?>"
                                class="regular-text code"
                                placeholder="https://example.com/member-logout"
                            />
                            <p class="field-description">
                                <?php
                                printf(
                                    esc_html__('Enter the URL where users with the %s role should be redirected after logout.', 'login-and-logout-redirect'),
                                    esc_html($role_data['name'])
                                );
                                ?>
                            </p>
                        </div>
                    </div>
                    <?php
                }
                ?>

                <div class="form-field">
                    <label for="logout_redirect_url_referrer" class="field-label">
                        <?php _e('Referring URL Redirect URL', 'login-and-logout-redirect'); ?>
                    </label>
                    <div class="field-input-wrapper">
                        <input
                            type="url"
                            name="logout_redirect_url_referrer"
                            id="logout_redirect_url_referrer"
                            class="regular-text code"
                            value="<?php echo esc_attr($logout_redirect_url_referrer); ?>"
                            placeholder="https://example.com/specific-page"
                        />
                        <p class="field-description">
                            <?php _e('Enter the URL where users coming from a specific referring URL should be redirected after logout. You will need to manually create a new setting for each referring URL.', 'login-and-logout-redirect'); ?>
                        </p>
                    </div>
                </div>

                <?php if (defined('BP_VERSION')) : ?>
                <div class="form-field">
                    <div class="field-info">
                        <strong><?php _e('BuddyPress Integration:', 'login-and-logout-redirect'); ?></strong>
                        <p>
                            <?php
                            $macros = array_map('esc_html', $this->_get_macros());
                            printf(
                                esc_html__('You can use these macros for your redirection: %s', 'login-and-logout-redirect'),
                                '<code>' . implode('</code>, <code>', $macros) . '</code>'
                            );
                            ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($preview_url)) : ?>
                <div class="form-field">
                    <div class="field-preview">
                        <strong><?php _e('Current Setting:', 'login-and-logout-redirect'); ?></strong>
                        <a href="<?php echo esc_url($preview_url); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html($preview_url); ?>
                            <span class="dashicons dashicons-external" aria-hidden="true"></span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Verify if plugin is network activated
     * */
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

$logout_redirect = new LogoutRedirect();
