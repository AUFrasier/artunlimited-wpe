<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_PostExtensionUpdate extends AutoUpdater_Task_Base
{
    protected $admin_privileges = true;
    protected $high_priority = false;

    protected $upgrader;
    protected $upgrader_path = '';
    protected $old_version = '';
    protected $new_version = '';
    protected $expected_version = '';

    /**
     * @return array
     * @throws
     */
    public function doTask()
    {
        $type = strtolower($this->input('type'));
        $slug = strtolower($this->input('slug'));
        $path = $this->input('path');

        if (!$type || (!$path && !$slug && $type != 'translation')) {
            throw new AutoUpdater_Exception_Response('Nothing to update', 400);
        }

        $filemanager = AutoUpdater_Filemanager::getInstance();
        if ($path && !preg_match('!^(http|https|ftp)://!i', $path) && !$filemanager->exists($path)) {
            $path = AUTOUPDATER_SITE_PATH . $path;
            if (!$filemanager->exists($path)) {
                throw new AutoUpdater_Exception_Response('Installation path not found', 404);
            }
        }

        $this->upgrader_path = AUTOUPDATER_LIB_PATH . 'Upgrader/';
        require_once $this->upgrader_path . 'Dependencies.php'; // phpcs:ignore

        AutoUpdater_Loader::loadClass('Helper_Extension');
        AutoUpdater_Loader::loadClass('Helper_Version');

        $this->expected_version = $this->input('version');

        switch ($type) {
            case 'core':
                $result = $this->updateCore();
                break;

            case 'plugin':
                $result = $this->updatePlugin($slug, $path);
                break;

            case 'theme':
                $result = $this->updateTheme($slug, $path);
                break;

            case 'translation':
                $result = $this->updateTranslation();
        }

        $filemanager->clearPhpCache();

        return $this->getResponse($result, $slug, $type);
    }

    /**
     * @return mixed
     */
    protected function updateCore()
    {
        AutoUpdater_Log::debug('Starting to update WordPress');

        require_once $this->upgrader_path . 'Core.php'; // phpcs:ignore
        require_once $this->upgrader_path . 'Skin/Core.php'; // phpcs:ignore

        $wp_upgrade_dir = WP_CONTENT_DIR . '/upgrade';
        $filemanager = AutoUpdater_Filemanager::getInstance();
        if (!$filemanager->is_dir($wp_upgrade_dir)) {
            $filemanager->mkdir($wp_upgrade_dir);
        }

        $expected_version = $this->expected_version;
        if (empty($expected_version)) {
            $expected_version = AUTOUPDATER_WP_VERSION;
        }
        if (substr($expected_version, -2) == '.0') {
            // Remove the last zero from the version X.Y.0
            $expected_version = substr($expected_version, 0, -2);
        }
        $this->expected_version = $expected_version;

        $working_dir = $this->input('path');
        $update = (object) array(
            'response' => 'upgrade',
            'download' => $working_dir,
            'locale' => 'en_US',
            'package' => $working_dir,
            /** @since 3.2.0 */
            'packages' => (object) array(
                'full' => false,
                'no_content' => $working_dir,
                'new_bundled' => false,
                'partial' => false,
                'rollback' => false,
            ),
            'current' => $expected_version,
            'version' => $expected_version,
            'php_version' => '5.2.4',
            'mysql_version' => '5.0',
            'new_bundled' => false,
            'partial_version' => false,
        );

        ob_start();

        $this->upgrader = new AutoUpdater_Upgrader_Core(
            new AutoUpdater_Upgrader_Skin_Core()
        );
        $result = $this->upgrader->upgrade($update, array('pre_check_md5' => false));

        $output = ob_get_clean();
        if (!empty($output)) {
            AutoUpdater_Log::debug('Updater output: ' . $output);
        }

        // returns string with a new version or null on success
        if (is_string($result) && preg_match('/^\d+(\.\d+)+/', $result)) {
            /** @since 3.3.0 */
            // Check if the version after update is the same or higher than expected
            if (version_compare(AutoUpdater_Helper_Version::fixAndFormat($expected_version), AutoUpdater_Helper_Version::fixAndFormat($result), '<=')) {
                $result = new WP_Error('wrong_version', sprintf('Expected version: %s, current version: %s', $expected_version, $result));
            }
        }

        return $result;
    }

    /**
     * @param string $slug
     * @param string $path
     *
     * @return mixed
     * @throws
     */
    protected function updatePlugin($slug, $path = '')
    {
        AutoUpdater_Log::debug('Starting to update plugin: ' . $slug);

        require_once $this->upgrader_path . 'Plugin.php'; // phpcs:ignore
        require_once $this->upgrader_path . 'Skin/Plugin.php'; // phpcs:ignore

        if (!$path && strpos($slug, 'masterslider.php') !== false) {
            // prepare update of exceptional plugins
            AutoUpdater_Helper_Extension::loadMasterSliderPro();
        }

        $plugin_path = $this->getPluginPath($slug);
        if (!$plugin_path) {
            throw AutoUpdater_Exception_Response::getException(
                200,
                'Failed to update plugin: ' . $slug,
                'no_update_warning',
                'No update was performed, plugin directory not found'
            );
        }

        // Update slug as it may have changed
        $slug = plugin_basename($plugin_path);
        $data = get_file_data($plugin_path, array('Version' => 'Version'));
        $this->old_version = $data['Version'];

        if ($path) {
            $nonce = 'plugin-upload';
            $url = add_query_arg(array('package' => $path), 'update.php?action=upload-plugin');
            $type = 'upload'; //Install plugin type, From Web or an Upload.
        } else {
            $plugin = $slug;
            $nonce = 'upgrade-plugin_' . $plugin;
            $url = 'update.php?action=upgrade-plugin&plugin=' . rawurlencode($plugin);
            $type = 'plugin';
        }

        ob_start();

        $this->upgrader = new AutoUpdater_Upgrader_Plugin(
            new AutoUpdater_Upgrader_Skin_Plugin(
                compact('nonce', 'url', 'plugin', 'type')
            )
        );
        // don't clear update cache, so next plugin's update step in same action will be able to use update cache data
        $result = $path ? $this->upgrader->install($path, array('clear_update_cache' => false)) : $this->upgrader->upgrade($slug, array('clear_update_cache' => false));

        $output = ob_get_clean();
        if (!empty($output)) {
            AutoUpdater_Log::debug('Updater output: ' . $output);
        }

        // Get the plugin path again, as the plugin main file may have changed
        $plugin_path = $this->getPluginPath($slug);
        if ($plugin_path) {
            // Update slug as it may have changed
            $slug = plugin_basename($plugin_path);
            $data = get_file_data($plugin_path, array('Version' => 'Version'));
            $this->new_version = $data['Version'];
        }

        return $result;
    }

    /**
     * @param string $slug
     *
     * @return string
     */
    protected function getPluginPath($slug)
    {
        $plugin_path = WP_PLUGIN_DIR . '/' . $slug;
        if (AutoUpdater_Filemanager::getInstance()->exists($plugin_path)) {
            return $plugin_path;
        }

        AutoUpdater_Log::error('Plugin directory not found: ' . $plugin_path);
        $slug = AutoUpdater_Helper_Extension::getPluginRealSlug($slug);
        if (!$slug) {
            return '';
        }

        AutoUpdater_Log::debug('Changing plugin slug to: ' . $slug);
        $plugin_path = WP_PLUGIN_DIR . '/' . $slug;

        return $plugin_path;
    }

    /**
     * @param string $slug
     * @param string $path
     *
     * @return mixed
     * @throws
     */
    protected function updateTheme($slug, $path = '')
    {
        AutoUpdater_Log::debug('Starting to update theme: ' . $slug);

        require_once $this->upgrader_path . 'Theme.php'; // phpcs:ignore
        require_once $this->upgrader_path . 'Skin/Theme.php'; // phpcs:ignore

        $theme_path = WP_CONTENT_DIR . '/themes/' . $slug . '/style.css';
        if (!AutoUpdater_Filemanager::getInstance()->exists($theme_path)) {
            AutoUpdater_Log::error('Theme directory not found: ' . $theme_path);
            $theme_path = AutoUpdater_Helper_Extension::getThemeRealPath($slug);
            if (!$theme_path) {
                throw AutoUpdater_Exception_Response::getException(
                    200,
                    'Failed to update theme: ' . $slug,
                    'no_update_warning',
                    'No update was performed, theme directory not found'
                );
            }
            AutoUpdater_Log::error('Changing theme directory to: ' . $theme_path);
        }

        $data = get_file_data($theme_path, array('Version' => 'Version'));
        $this->old_version = $data['Version'];

        if ($path) {
            $nonce = 'theme-upload';
            $url = add_query_arg(array('package' => $path), 'update.php?action=upload-theme');
            $type = 'upload'; //Install theme type, From Web or an Upload.
        } else {
            $theme = $slug;
            $nonce = 'upgrade-theme_' . $theme;
            $url = 'update.php?action=upgrade-theme&theme=' . rawurlencode($theme);
            $type = 'theme';
        }

        ob_start();

        $this->upgrader = new AutoUpdater_Upgrader_Theme(
            new AutoUpdater_Upgrader_Skin_Theme(
                compact('nonce', 'url', 'theme', 'type')
            )
        );
        // don't clear update cache, so next theme's update step in same action will be able to use update cache data
        $result = $path ? $this->upgrader->install($path, array('clear_update_cache' => false)) : $this->upgrader->upgrade($slug, array('clear_update_cache' => false));

        $output = ob_get_clean();
        if (!empty($output)) {
            AutoUpdater_Log::debug('Updater output: ' . $output);
        }

        $data = get_file_data($theme_path, array('Version' => 'Version'));
        $this->new_version = $data['Version'];

        return $result;
    }

    /**
     * @return mixed
     */
    protected function updateTranslation()
    {
        AutoUpdater_Log::debug('Starting to update translations');

        // Language_Pack_Upgrader skin was introduced in 3.7 so...
        if (version_compare(AUTOUPDATER_WP_VERSION, '3.7', '<')) {
            return true;
        }

        require_once $this->upgrader_path . 'Skin/Languagepack.php'; // phpcs:ignore

        $url = 'update-core.php?action=do-translation-upgrade';
        $nonce = 'upgrade-translations';
        $context = WP_LANG_DIR;

        ob_start();

        $this->upgrader = new Language_Pack_Upgrader(
            new AutoUpdater_Upgrader_Skin_Languagepack(
                compact('url', 'nonce', 'context')
            )
        );
        // don't clear update cache, so next extension's update step in same action will be able to use update cache data
        $result = $this->upgrader->bulk_upgrade(array(), array('clear_update_cache' => false));

        $output = ob_get_clean();
        if (!empty($output)) {
            AutoUpdater_Log::debug('Updater output: ' . $output);
        }

        // returns an array of results on success, or true if there are no updates
        if (is_array($result)) {
            $result = true;
        } elseif ($result === true) {
            $result = new WP_Error('up_to_date', 'There are no translations updates');
        }

        return $result;
    }

    /**
     * @param mixed $result
     * @param string $slug
     * @param string $type
     *
     * @return array
     */
    protected function getResponse($result, $slug, $type)
    {
        $response = array(
            'success' => false,
            'message' => 'Failed to update ' . $type . ': ' . $slug,
        );

        $errors = array();
        if ($this->upgrader) {
            /** @var AutoUpdater_Upgrader_Skin_Core|AutoUpdater_Upgrader_Skin_Plugin|AutoUpdater_Upgrader_Skin_Theme|AutoUpdater_Upgrader_Skin_Languagepack $skin */
            $skin = $this->upgrader->skin;
            $errors = $skin->get_errors();

            if ($skin instanceof AutoUpdater_Upgrader_Skin_Languagepack) {
                /** @var AutoUpdater_Upgrader_Skin_Languagepack $skin */
                $translations = $skin->get_translations();
                if (!empty($translations)) {
                    $response['translations'] = $translations;
                }
            }
        }

        if (is_wp_error($result)) {
            /** @var WP_Error $result */
            $errors[$result->get_error_code()] = $result->get_error_message();
            $result = false;
        }

        if (array_key_exists('up_to_date', $errors) && (in_array($type, array('core', 'translation')) || $this->expected_version && $this->new_version &&
            version_compare(AutoUpdater_Helper_Version::fixAndFormat($this->expected_version), AutoUpdater_Helper_Version::fixAndFormat($this->new_version), '<='))) {
            $response['success'] = true;
            $response['message'] = $errors['up_to_date'] != 'up_to_date' ? $errors['up_to_date'] : 'Up-to-date';
            return $response;
        } elseif (array_key_exists('no_package', $errors)) {
            $result = false;
            $response['error'] = array(
                'code' => 'no_package_warning',
                'message' => $errors['no_package'],
            );
            unset($errors['no_package']);
        } elseif (in_array($type, array('plugin', 'theme')) && $this->new_version && ($this->expected_version &&
            version_compare(AutoUpdater_Helper_Version::fixAndFormat($this->expected_version), AutoUpdater_Helper_Version::fixAndFormat($this->new_version), '>') ||
            !$this->expected_version &&
            version_compare(AutoUpdater_Helper_Version::fixAndFormat($this->old_version), AutoUpdater_Helper_Version::fixAndFormat($this->new_version), '='))) {
            $result = false;
            $response['error'] = array(
                'code' => 'no_update_warning',
                'message' => 'No update was performed, current version: ' . $this->new_version
                    . ', expected version: ' . $this->expected_version,
            );
        }

        if ($result === true || is_null($result)) {
            $response['success'] = true;
            unset($response['message']);
            return $response;
        } elseif (!is_null($result) && !is_bool($result)) {
            $errors['unknown_error'] = 'Result dump: ' . var_export($result, true);
        }

        if (count($errors)) {
            if (!isset($response['error'])) {
                end($errors);
                $response['error'] = array(
                    'code' => key($errors),
                    'message' => current($errors),
                );
                unset($errors[$response['error']['code']]);
            }
            if (count($errors)) {
                $response['errors'] = $errors;
            }
        }

        return $response;
    }
}
