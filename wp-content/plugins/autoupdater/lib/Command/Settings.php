<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Command_Settings extends AutoUpdater_Command_Base
{
    protected $bool_options = array(
        'autoupdater-enabled',
        'update-core',
        'update-plugins',
        'update-themes',
        'notification-on-success',
        'notification-on-failure',
        'auto-rollback',
        'maintenance-mode',
        'encrypt-response',
        'debug-response',
        'trace-hooks',
    );

    protected $int_options = array(
        'autoupdate-at',
        'site-id',
    );

    // To remove the value change an empty string to NULL
    protected $null_options = array(
        'sitemap-url',
        'vrt-css-exclusions',
    );

    protected $array_options = array(
        'notification-emails',
        'excluded-plugins',
        'excluded-themes'
    );

    protected $api_options = array(
        'autoupdater-enabled',
        'autoupdate-at',
        'update-core',
        'update-plugins',
        'update-themes',
        'excluded-plugins',
        'excluded-themes',
        'notification-emails',
        'notification-on-success',
        'notification-on-failure',
        'auto-rollback',
        'maintenance-mode',
        'sitemap-url',
        'vrt-css-exclusions',
        'worker-token',
        'aes-key',
    );

    protected $local_options = array(
        'excluded-plugins',
        'excluded-themes',
        'worker-token',
        'aes-key',
        'site-id',
        'encrypt-response',
        'debug-response',
        'trace-hooks',
    );

    /**
     * Display or update AutoUpdater settings
     *
     * ## OPTIONS
     *
     * <action>
     * : get or set AutoUpdater settings
     * ---
     * default: get
     * options:
     *   - get
     *   - set
     *
     * [--autoupdater-enabled=<bool>]
     * : Enable automatic updates.
     *
     * [--autoupdate-at=<hour>]
     * : The time of day (in UTC) when the automatic update will be scheduled.
     * ---
     * options:
     *   - 0
     *   - 6
     *   - 12
     *   - 18
     *
     * [--update-plugins=<bool>]
     * : Enable automatic updates of plugins.
     *
     * [--excluded-plugins=<string>]
     * : A list of excluded plugins, separated with a comma (,). Example value: akismet/akismet.php,wordpress-seo/wp-seo.php.
     *
     * [--notification-emails=<emails>]
     * : E-mail addresses separated with a comma (,) which receive a notification after an automatic update of this website.
     *
     * [--notification-on-success=<bool>]
     * : Receive notifications after successful updates.
     *
     * [--notification-on-failure=<bool>]
     * : Receive notifications after failed updates.
     *
     * [--auto-rollback=<bool>]
     * : Enable automatic rollback after failed updates.
     *
     * [--maintenance-mode=<bool>]
     * : Put the website in a maintenance mode during an update in order to prevent data loss.
     *
     * [--sitemap-url=<url>]
     * : A URL to this website's sitemap to test up to 20 random URLs during an update. Accepted formats: XML <urlset> and <sitemapindex> or a plain text sitemap with an absolute URL on each line. Put a line break after the last URL.
     *
     * [--vrt-css-exclusions=<string>]
     * : Hide elements matching the CSS selectors, separated with a comma (,) during visual regression testing. It’s recommended to exclude dynamic content which might lead to false negatives, such as sliders, ads or testimonials.
     *
     * [--worker-token=<string>]
     * : The token to sign requests and authenticate them.
     *
     * [--aes-key=<string>]
     * : The AES key to encrypt responses if the website is not secured with TLS.
     *
     * [--site-id=<int>]
     * : The site ID.
     *
     * [--encrypt-response=<bool>]
     * : Encrypt responses if the AES key is provided.
     *
     * [--debug-response=<bool>]
     * : Save extended logs to a file.
     *
     * [--trace-hooks=<bool>]
     * : Trace hooks executed in WP Admin and during the AutoUpdater request.
     *
     * [--output=<format>]
     * : Output format of settings to display.
     * ---
     * default: yaml
     * options:
     *   - json
     *   - yaml
     *
     * @when after_wp_load
     */
    public function __invoke($args, $assoc_args)
    {
        if (empty($args) || $args[0] === 'get') {
            $this->getSettings($assoc_args);
            return;
        }

        if ($args[0] === 'set') {
            $this->setSettings($assoc_args);
            return;
        }
    }

    /**
     * @param array $assoc_args
     */
    protected function getSettings($assoc_args)
    {
        $this->validateBeforeApiRequest();
        $response = AutoUpdater_Request::api('get', 'settings');
        if ($response->code !== 200 || !isset($response->body->settings)) {
            WP_CLI::error('Failed to get settings.');
            return;
        }

        foreach ($this->local_options as $option) {
            if (in_array($option, $this->api_options)) {
                continue;
            }
            $local_property = $api_property = str_replace('-', '_', $option);
            if ($option === 'debug-response') {
                $local_property = 'debug';
            }
            $response->body->settings->{$api_property} = $this->castOptionValue($option, AutoUpdater_Config::get($local_property));
        }

        if ($assoc_args['output'] === 'json') {
            WP_CLI::line(json_encode(
                $response->body->settings,
                JSON_PRETTY_PRINT // phpcs:ignore PHPCompatibility.Constants.NewConstants
            ));
        } elseif ($assoc_args['output'] === 'yaml') {
            WP_CLI\Utils\format_items('yaml', $response->body, array(
                'settings',
            ));
        }
    }

    /**
     * @param array $assoc_args
     */
    protected function setSettings($assoc_args)
    {
        if (!$this->validate($assoc_args)) {
            return;
        }

        $payload = array();
        foreach ($this->api_options as $option) {
            if (!isset($assoc_args[$option])) {
                continue;
            }
            $value = $this->castOptionValue($option, $assoc_args[$option]);
            if ($option === 'vrt-css-exclusions' && $value) {
                $value = str_replace(',', "\n", $value);
            }
            $payload[str_replace('-', '_', $option)] = $value;
        }


        if (!empty($payload)) {
            $this->validateBeforeApiRequest();
            $response = AutoUpdater_Request::api('post', 'settings', $payload);
            if ($response->code !== 204) {
                WP_CLI::error('Failed to save settings.');
                return;
            }
        }

        $success = true;
        foreach ($this->local_options as $option) {
            if (!isset($assoc_args[$option])) {
                continue;
            }
            $value = $this->castOptionValue($option, $assoc_args[$option]);
            if ($option === 'debug-response') {
                $option = 'debug';
            }
            $success = AutoUpdater_Config::set(str_replace('-', '_', $option), $value) && $success;
        }

        if ($success) {
            WP_CLI::success('Settings have been saved.');
        } else {
            WP_CLI::error('Failed to save settings.');
        }
    }

    /**
     * @param array $assoc_args
     *
     * @return bool
     */
    protected function validate($assoc_args)
    {
        $result = true;

        if (isset($assoc_args['autoupdate-at'])) {
            $result = $this->validateAutoupdateAt($assoc_args['autoupdate-at']) && $result;
        }

        if (isset($assoc_args['notification-emails'])) {
            $result = $this->validateNotificationEmails($assoc_args['notification-emails']) && $result;
        }

        if (isset($assoc_args['excluded-plugins'])) {
            $result = $this->validateExcludedPlugins($assoc_args['excluded-plugins']) && $result;
        }

        if (isset($assoc_args['excluded-themes'])) {
            $result = $this->validateExcludedThemes($assoc_args['excluded-themes']) && $result;
        }

        if (isset($assoc_args['sitemap-url'])) {
            $result = $this->validateSitemapUrl($assoc_args['sitemap-url']) && $result;
        }

        if (isset($assoc_args['worker-token'])) {
            $result = $this->validateWorkerToken($assoc_args['worker-token']) && $result;
        }

        if (isset($assoc_args['aes-key'])) {
            $result = $this->validateAesKey($assoc_args['aes-key']) && $result;
        }

        $result = $this->validateIntOptions($assoc_args) && $result;

        return $this->validateBoolOptions($assoc_args) && $result;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected function validateAutoupdateAt($value)
    {
        $hours = array(0, 6, 12, 18);
        if (!is_numeric($value) || !in_array((int) $value, $hours)) {
            WP_CLI::error(sprintf('The allowed hours for the --autoupdate-at option are: %s.', implode(', ', $hours)), false);
            return false;
        }

        return true;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected function validateNotificationEmails($value)
    {
        if (strlen($value) > 500) {
            WP_CLI::error('The email addresses have exceded 500 bytes.', false);
            return false;
        }
        $result = true;
        $emails = $this->castArray($value);
        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                WP_CLI::error(sprintf('The email address %s is not valid.', $email), false);
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected function validateExcludedPlugins($value)
    {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $result = true;
        $plugins = $this->castArray($value);
        foreach ($plugins as $plugin) {
            /** @var WP_Error $err */
            $err = validate_plugin($plugin);
            if ($err !== 0) {
                WP_CLI::error(sprintf('%s %s', $err->get_error_message(), $plugin), false);
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected function validateExcludedThemes($value)
    {
        require_once ABSPATH . WPINC . '/theme.php';

        $result = true;
        $themes = $this->castArray($value);
        foreach ($themes as $theme) {
            /** @var WP_Theme $theme */
            $theme = wp_get_theme($theme);
            /** @var WP_Error $err */
            $err = $theme->errors();
            if ($err !== false) {
                WP_CLI::error($err->get_error_message(), false);
                $result = false;
            }
        }

        return $result;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected function validateSitemapUrl($value)
    {
        if (empty($value)) {
            return true;
        }
        if (strlen($value) > 255) {
            WP_CLI::error('The sitemap URL has exceded 255 characters.', false);
            return false;
        }
        if (filter_var(trim($value), FILTER_VALIDATE_URL) === false) {
            WP_CLI::error('The sitemap URL is invalid.', false);
            return false;
        }

        return true;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected function validateWorkerToken($value)
    {
        if (strlen($value) !== 32 || !preg_match('/^[a-zA-Z0-9]$/', $value)) {
            WP_CLI::error('The worker token has to be 32 characters length and can contain a-z, A-Z and 0-9 only.', false);
            return false;
        }

        return true;
    }

    /**
     * @param string $value
     *
     * @return bool
     */
    protected function validateAesKey($value)
    {
        if (strlen($value) !== 32 || !preg_match('/^[a-zA-Z0-9]$/', $value)) {
            WP_CLI::error('The AES key has to be 32 characters length and can contain a-z, A-Z and 0-9 only.', false);
            return false;
        }

        return true;
    }

    protected function validateBeforeApiRequest()
    {
        if (!AutoUpdater_Config::get('site_id')) {
            WP_CLI::error('The site ID is missing.');
            return;
        }
        if (!AutoUpdater_Config::get('worker_token')) {
            WP_CLI::error('The worker token is missing.');
            return;
        }
    }

    public static function beforeInvoke()
    {
        // Do nothing
    }
}
