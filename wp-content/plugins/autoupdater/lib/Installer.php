<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Installer
{
    protected static $instance = null;
    protected $options = array();
    protected $uninstalled = false;
    /**
     * @return static
     */
    public static function getInstance()
    {
        if (!is_null(static::$instance)) {
            return static::$instance;
        }

        $class_name = AutoUpdater_Loader::loadClass('Installer');

        static::$instance = new $class_name();

        return static::$instance;
    }

    public function __construct()
    {
        register_activation_hook(AUTOUPDATER_WP_PLUGIN_FILE, array($this, 'install'));
        register_uninstall_hook(AUTOUPDATER_WP_PLUGIN_FILE, array('AutoUpdater_Installer', 'hookUninstall'));

        add_action('init', array($this, 'selfUpdate'), 0);
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function setOption($key, $value)
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getOption($key, $default = null)
    {
        if (isset($this->options[$key])) {
            return $this->options[$key];
        }

        return $default;
    }

    /**
     * @return bool
     */
    public function install()
    {
        $result = true;
        AutoUpdater_Log::debug(sprintf('Installing worker %s', AUTOUPDATER_VERSION));

        if (!AutoUpdater_Config::get('version')) {
            AutoUpdater_Config::set('version', AUTOUPDATER_VERSION);
        }

        $this->createTokens();

        AutoUpdater_Log::debug(sprintf('Worker %s has been installed.', AUTOUPDATER_VERSION));

        // Disable WordPress core automatic updates
        $this->changeWordPressAutomaticUpdates();

        return $result;
    }

    public function selfUpdate()
    {
        if (in_array(AutoUpdater_Request::getQueryVar('wpe_endpoint'), array('child/update/after', 'child/verify'))) {
            // Do not run self-update as we are running it through API
            return;
        }

        $version = AutoUpdater_Config::get('version', '2.0');
        if (version_compare($version, AUTOUPDATER_VERSION, '<')) {
            AutoUpdater_Log::debug("Self update from version $version to " . AUTOUPDATER_VERSION);
            $this->update();
        }
    }

    /**
     * @return bool
     */
    public function update()
    {
        $current_version = AutoUpdater_Config::get('version', '2.0');
        $new_version = $this->getOption('version', AUTOUPDATER_VERSION);
        AutoUpdater_Log::debug(sprintf('Updating worker from version %s to %s', $current_version, $new_version));
        if (version_compare($current_version, $new_version, '<')) {
            AutoUpdater_Config::set('version', $new_version);
        }
        if (version_compare($current_version, '2.0', '<')) {
            AutoUpdater_Log::debug('Migrating settings');

            AutoUpdater_Config::set('worker_token', AutoUpdater_Config::get('write_token'));
            AutoUpdater_Config::set('update_plugins', AutoUpdater_Config::get('update_extensions', 1));
            AutoUpdater_Config::set('notification_emails', AutoUpdater_Config::get('notification_end_user_email'));

            $time_of_day = AutoUpdater_Config::get('time_of_day', 'afternoon');
            $day_periods = array(
                'night' => 0,
                'morning' => 6,
                'afternoon' => 12,
                'evening' => 18
            );
            AutoUpdater_Config::set('autoupdate_at', array_key_exists($time_of_day, $day_periods) ? $day_periods[$time_of_day] : 12);

            $excludedSlugsMigrationFunction = function ($value) {
                list(, $slug) = explode('::', $value, 2);
                return $slug;
            };

            $excluded_plugins = (array) AutoUpdater_Config::get('excluded_extensions', array());
            if (!empty($excluded_plugins)) {
                $excluded_plugins = array_map($excludedSlugsMigrationFunction, $excluded_plugins);
                AutoUpdater_Config::set('excluded_plugins', $excluded_plugins);
            }

            $excluded_themes = (array) AutoUpdater_Config::get('excluded_themes', array());
            if (!empty($excluded_themes)) {
                $excluded_plugins = array_map($excludedSlugsMigrationFunction, $excluded_themes);
                AutoUpdater_Config::set('excluded_themes', $excluded_themes);
            }

            AutoUpdater_Config::remove('read_token');
            AutoUpdater_Config::remove('write_token');
            AutoUpdater_Config::remove('update_cms');
            AutoUpdater_Config::remove('update_cms_stage');
            AutoUpdater_Config::remove('update_extensions');
            AutoUpdater_Config::remove('excluded_extensions');
            AutoUpdater_Config::remove('notification_end_user_email');
            AutoUpdater_Config::remove('time_of_day');
            AutoUpdater_Config::remove('config_cached');

            // Migrate logs files
            $old_path = rtrim(WP_CONTENT_DIR, '/\\') . '/logs/';
            $new_path = AutoUpdater_Log::getInstance()->getLogsPath();
            $filemanager = AutoUpdater_Filemanager::getInstance();
            if (!$filemanager->is_dir($new_path)) {
                $filemanager->mkdir($new_path);
            }
            $files = $filemanager->dirlist($old_path, false);
            if (is_array($files)) {
                foreach ($files as $file => $fileinfo) {
                    if (strpos($file, 'autoupdater_') === 0) {
                        $filemanager->move($old_path . $file, $new_path . $file);
                    }
                }
            }
            // Delete empty old path
            $files = $filemanager->dirlist($old_path, false);
            if (is_array($files) && !count($files)) {
                $filemanager->delete($old_path);
            }
        }
        if (version_compare($current_version, '2.0.5', '<')) {
            AutoUpdater_Log::debug('Migrating settings');
            AutoUpdater_Config::set('encrypt_response', AutoUpdater_Config::get('encryption'));
            AutoUpdater_Config::remove('encryption');
        }
        if (version_compare($current_version, '3.1.0', '<')) {
            AutoUpdater_Config::remove('extensions_cache');
            AutoUpdater_Config::remove('extensions_cached_at');
        }

        AutoUpdater_Log::debug(sprintf('Worker has been updated from version %s to %s', $current_version, $new_version));

        return true;
    }

    /**
     * @return bool
     */
    protected function createTokens()
    {
        if (!AutoUpdater_Config::get('worker_token')) {
            AutoUpdater_Config::set('worker_token', $this->generateToken());
        }
        if (!AutoUpdater_Config::get('aes_key')) {
            AutoUpdater_Config::set('aes_key', $this->generateToken());
        }

        return true;
    }

    /**
     * @return string
     */
    protected function generateToken()
    {
        $key = '';
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $max = strlen($chars)  - 1;
        for ($i = 0; $i < 32; ++$i) {
            $key .= $chars[wp_rand(0, $max)];
        }

        return $key;
    }

    /**
     * @param bool $self
     *
     * @return bool
     */
    public function uninstall($self = false)
    {
        // Make sure that it would not run twice with WP register_uninstall_hook
        if ($this->uninstalled) {
            return true;
        }
        $this->uninstalled = true;

        AutoUpdater_Log::debug(sprintf('Uninstalling worker %s', AUTOUPDATER_VERSION));

        AutoUpdater_Config::removeAll();

        AutoUpdater_Log::debug(sprintf('Worker %s has been uninstalled.', AUTOUPDATER_VERSION));

        // Enable WordPress core automatic updates
        $this->changeWordPressAutomaticUpdates(false);

        // Do not delete the plugin if the uninstaller was triggered by the back-end
        // because the plugin will be deleted by the WP core
        if ($self === false) {
            return true;
        }

        if (is_plugin_active(AUTOUPDATER_WP_PLUGIN_SLUG)) {
            deactivate_plugins(AUTOUPDATER_WP_PLUGIN_SLUG);
        }

        if (is_uninstallable_plugin(AUTOUPDATER_WP_PLUGIN_SLUG)) {
            include_once ABSPATH . 'wp-admin/includes/file.php';
            if (delete_plugins(array(AUTOUPDATER_WP_PLUGIN_SLUG)) !== true) {
                return false;
            }
        }

        return true;
    }

    public static function hookUninstall()
    {
        AutoUpdater_Installer::getInstance()->uninstall();
    }

    /**
     * @param bool $disable
     */
    protected function changeWordPressAutomaticUpdates($disable = true)
    {
        // setup file path
        $file = ABSPATH . 'wp-config.php';
        $filemanager = AutoUpdater_Filemanager::getInstance();

        //check if file exists
        if (!$filemanager->exists($file)) {
            return;
        }
        // grab content of that file
        $content = $filemanager->get_contents($file);

        $closing_php_position = strrpos($content, '?>');
        if ($closing_php_position !== false) {
            $content = substr_replace($content, '', $closing_php_position, strlen('?>'));
        }

        // search for automatic updater
        preg_match('/(?:define\s*\(\s*[\'"]AUTOMATIC_UPDATER_DISABLED[\'"]\s*,\s*)(false|true|1|0)(?:\s*\);)/i', $content, $match);

        // if $match empty we don't have this variable in file
        if (!empty($match)) {
            if (($disable === true && ($match[1] === 'true' || $match[1] === '1')) || ($disable === false && ($match[1] === 'false' || $match[1] === '0'))
            ) {
                return;
            }

            // modify this constans : )
            $content = str_replace(
                $match[0],
                'define(\'AUTOMATIC_UPDATER_DISABLED\', ' . ($disable ? 'true' : 'false') . ');',
                $content
            );
        } else {
            // so lets create this constans : )
            $content = str_replace(
                '/**#@-*/',
                'if (!defined(\'AUTOMATIC_UPDATER_DISABLED\')) define(\'AUTOMATIC_UPDATER_DISABLED\', ' . ($disable ? 'true' : 'false') . ');',
                $content
            );
        }

        // save it to file
        $filemanager->put_contents($file, $content . PHP_EOL);
    }
}
