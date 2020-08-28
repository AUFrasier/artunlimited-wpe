<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_GetExtensionsDisabled extends AutoUpdater_Task_Base
{
    /**
     * @return array
     */
    public function doTask()
    {
        AutoUpdater_Loader::loadClass('Helper_Version');

        $list = get_plugins();
        $current_theme = '';

        if (version_compare(AUTOUPDATER_WP_VERSION, '3.4.0', '>=')) {
            $list = array_merge($list, wp_get_themes());
            $current_theme = AutoUpdater_Helper_Version::filterHTML(wp_get_theme()->get('Name'));
        } else {
            $list = array_merge($list, get_allowed_themes());
            $current_theme = AutoUpdater_Helper_Version::filterHTML(get_current_theme());
        }

        $extensions = array();

        foreach ($list as $slug => $item) {
            $extension = new stdClass();
            $extension->slug = $slug;

            if (($item instanceof WP_Theme || isset($item['Template'])) && $item->name != $current_theme) {
                $extension->type = 'theme';
                $extensions[] = $extension;
            } elseif (isset($item['PluginURI']) && !is_plugin_active($slug)) {
                $extension->type = 'plugin';
                $extensions[] = $extension;
            }
        }

        return array(
            'success' => true,
            'extensions' => $extensions
        );
    }
}
