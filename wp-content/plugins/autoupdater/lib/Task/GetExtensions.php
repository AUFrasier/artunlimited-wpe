<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_GetExtensions extends AutoUpdater_Task_Base
{
    protected $admin_privileges = true;
    protected $high_priority = false;
    protected $current_theme = '';
    protected $updates = array();

    /**
     * @return array
     */
    public function doTask()
    {
        AutoUpdater_Loader::loadClass('Helper_Version');

        $extensions = $this->getExtensions();

        return array(
            'success' => true,
            'extensions' => array(
                'changed' => $extensions,
                'checksum' => sha1(json_encode($extensions)),
            ),
            'environment' => AutoUpdater_Task::getInstance('GetEnvironment')->doTask(),
        );
    }

    /**
     * @return array
     */
    protected function getExtensions()
    {
        require_once ABSPATH . '/wp-admin/includes/plugin.php';
        require_once ABSPATH . '/wp-admin/includes/theme.php';

        $extensions = array();
        $this->updates = $this->getUpdatesFromRemoteServers();
        $this->excluded_plugins = $this->getExcludedPlugins();
        $this->excluded_themes = $this->getExcludedThemes();

        $core = new stdClass();
        $core->name = 'WordPress';
        $core->type = 'core';
        $core->slug = 'wordpress';
        $core->version = AUTOUPDATER_WP_VERSION;
        $core->enabled = 1;
        $core->update = null;
        $core->updates_enabled = 1;

        $translations = new stdClass();
        $translations->name = 'Translations';
        $translations->type = 'translation';
        $translations->slug = 'core';
        $translations->version = AUTOUPDATER_WP_VERSION;
        $translations->enabled = 1;
        $translations->update = $this->checkForUpdates($translations->slug, $translations->type);
        $translations->updates_enabled = 1;

        $extensions[] = $core;
        $extensions[] = $translations;

        $list = get_plugins();

        if (version_compare(AUTOUPDATER_WP_VERSION, '3.4.0', '>=')) {
            $list = array_merge($list, wp_get_themes());
            $this->current_theme = AutoUpdater_Helper_Version::filterHTML(wp_get_theme()->get('Name'));
        } else {
            $list = array_merge($list, get_allowed_themes());
            $this->current_theme = AutoUpdater_Helper_Version::filterHTML(get_current_theme());
        }

        foreach ($list as $slug => $item) {
            if ($item instanceof WP_Theme || isset($item['Template'])) {
                $extensions[] = $this->getThemeInfo($slug, $item);
            } elseif (isset($item['PluginURI'])) {
                $plugin = $this->getPluginInfo($slug, $item);
                $extensions[] = $plugin;
            }
        }

        return $extensions;
    }

    /**
     * @param string $slug
     * @param array  $plugin
     *
     * @return array
     */
    protected function getPluginInfo($slug, $plugin)
    {
        $item = new stdClass();
        $item->name = AutoUpdater_Helper_Version::filterHTML($plugin['Name']);
        $item->type = 'plugin';
        $item->slug = $slug;
        $item->version = strtolower(AutoUpdater_Helper_Version::filterHTML($plugin['Version']));
        $item->enabled = (int) is_plugin_active($slug);
        $item->update = $this->checkForUpdates($item->slug, $item->type);
        $item->updates_enabled = (int) !$this->isPluginExcluded($slug);

        if ($slug == AUTOUPDATER_WP_PLUGIN_SLUG) {
            $item->name = AutoUpdater_Helper_Version::filterHTML(AutoUpdater_Config::get('whitelabel_name', $item->name));
        }

        return $item;
    }

    /**
     * @param string         $slug
     * @param array|WP_Theme $theme
     *
     * @return array
     */
    protected function getThemeInfo($slug, $theme)
    {
        /**
         * @var WP_Theme $theme
         * @since 3.4.0
         */
        $legacy = !($theme instanceof WP_Theme);

        // build array with themes data to Dashboard
        $item = new stdClass();
        $item->name = AutoUpdater_Helper_Version::filterHTML($legacy ? $theme['Name'] : $theme->get('Name'));
        $item->type = 'theme';
        $item->slug = $legacy ? $theme['Template'] : pathinfo($slug, PATHINFO_FILENAME);
        $item->version = strtolower(AutoUpdater_Helper_Version::filterHTML($legacy ? $theme['Version'] : $theme->get('Version')));
        $item->enabled = (int) ($this->current_theme == $item->name);
        $item->update = $this->checkForUpdates($item->slug, $item->type);
        $item->updates_enabled = (int) !$this->isThemeExcluded($item->slug);

        return $item;
    }

    /**
     * @return array
     */
    protected function getUpdatesFromRemoteServers()
    {

        // get updates for exceptional extensions (it must be called here)
        if (!class_exists(AutoUpdater_Loader::getClassPrefix() . 'Helper_Extension')) {
            require_once AUTOUPDATER_LIB_PATH . 'Helper/Extension.php';
        }
        AutoUpdater_Helper_Extension::loadMasterSliderPro();

        // delete cached data with updates
        delete_site_transient('update_plugins');
        delete_site_transient('update_themes');
        wp_cache_delete('plugins', 'plugins');

        do_action('load-update-core.php');

        // find updates
        // do it two times, so all data will be correctly filled after deleting whole site_transient for update_plugins and update_themes
        // looks redundant, but for sure after calling wp_update only once there's no "checked" property in update_plugins and update_themes transients
        // and available updates of some plugins are missing in "response" property of these transients
        wp_update_plugins();
        wp_update_plugins();
        wp_update_themes();
        wp_update_themes();

        // get updates
        $plugins = get_site_transient('update_plugins');
        $themes = get_site_transient('update_themes');

        $updates = array();

        if (!empty($plugins->response)) {
            foreach ($plugins->response as $slug => $plugin) {
                if (!is_object($plugin)) {
                    if (!is_array($plugin)) {
                        continue;
                    }
                    $plugin = (object) $plugin;
                }
                if (!empty($plugin->new_version)) {
                    if (isset($plugin->package)) {
                        // Filter and validate download URL
                        $plugin->package = trim(html_entity_decode($plugin->package, ENT_QUOTES, 'UTF-8'));
                        if (filter_var($plugin->package, FILTER_VALIDATE_URL) === false) {
                            $plugin->package = '';
                        }
                    } else {
                        $plugin->package = '';
                    }

                    $updates[$slug . '_plugin'] = array(
                        'version' => $plugin->new_version,
                        'download_url' => $plugin->package,
                        'core_version_min' => !empty($plugin->requires) ? $plugin->requires : null,
                        'core_version_max' => !empty($plugin->tested) ? $plugin->tested : null,
                        'php_version_min' => !empty($plugin->requires_php) ? $plugin->requires_php : null
                    );
                }
            }
        }

        if (!empty($themes->response)) {
            foreach ($themes->response as $slug => $theme) {
                if (!is_object($theme)) {
                    if (!is_array($theme)) {
                        continue;
                    }
                    $theme = (object) $theme;
                }
                if (!empty($theme->new_version)) {
                    if (isset($theme->package)) {
                        // Filter and validate download URL
                        $theme->package = trim(html_entity_decode($theme->package, ENT_QUOTES, 'UTF-8'));
                        if (filter_var($theme->package, FILTER_VALIDATE_URL) === false) {
                            $theme->package = '';
                        }
                    } else {
                        $theme->package = '';
                    }

                    $updates[$slug . '_theme'] = array(
                        'version' => $theme->new_version,
                        'download_url' => $theme->package,
                        'core_version_min' => !empty($theme->requires) ? $theme->requires : null,
                        'core_version_max' => !empty($theme->tested) ? $theme->tested : null,
                        'php_version_min' => !empty($theme->requires_php) ? $theme->requires_php : null
                    );
                }
            }
        }

        $translations = false;
        if (!empty($plugins->translations) || !empty($themes->translations)) {
            $translations = true;
        } else {
            $core = get_site_transient('update_core');
            if (!empty($core->translations)) {
                $translations = true;
            }
        }

        if ($translations) {
            $updates['core_translation'] = array(
                'version' => AUTOUPDATER_WP_VERSION . (substr_count(AUTOUPDATER_WP_VERSION, '.') === 1 ? '.0.1' : '.1'),
                'download_url' => null,
                'core_version_max' => AUTOUPDATER_WP_VERSION,
            );
        }

        return $updates;
    }

    /**
     * @param string $slug
     * @param string $type
     *
     * @return object|null
     */
    protected function checkForUpdates($slug, $type)
    {
        return isset($this->updates[$slug . '_' . $type]) ? $this->updates[$slug . '_' . $type] : null;
    }

    /**
     * @return array
     */
    protected function getExcludedPlugins()
    {
        return (array) AutoUpdater_Config::get('excluded_plugins', array());
    }

    /**
     * @return array
     */
    protected function getExcludedThemes()
    {
        return (array) AutoUpdater_Config::get('excluded_themes', array());
    }

    /**
     * @param strin $slug
     * @return boolean
     */
    protected function isPluginExcluded($slug)
    {
        return in_array($slug, $this->excluded_plugins);
    }

    /**
     * @param strin $slug
     * @return boolean
     */
    protected function isThemeExcluded($slug)
    {
        return in_array($slug, $this->excluded_themes);
    }
}
