<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_PostExtensionEnable extends AutoUpdater_Task_Base
{
    /**
     * @return array
     */
    public function doTask()
    {
        $extensions = (array) $this->input('extensions', array());

        $plugins = array();
        foreach ($extensions as $extension) {
            if (empty($extension['slug'])) {
                continue;
            }
            $plugins[] = $extension['slug'];
        }

        // Skip AutoUpdater extension
        if (($key = array_search(AUTOUPDATER_WP_PLUGIN_SLUG, $plugins)) !== false) {
            unset($plugins[$key]);
        }

        if (empty($plugins)) {
            throw new AutoUpdater_Exception_Response('No extensions to activate', 400);
        }

        AutoUpdater_Loader::loadClass('Helper_Extension');
        $filemanager = AutoUpdater_Filemanager::getInstance();
        // Find a real path to plugins as the input might be lower-case while the directory name uses capital letters too
        foreach ($plugins as &$plugin) {
            if ($filemanager->exists(WP_PLUGIN_DIR . '/' . $plugin)) {
                continue;
            }
            $new_plugin = AutoUpdater_Helper_Extension::getPluginRealSlug($plugin);
            if ($new_plugin && $filemanager->exists(WP_PLUGIN_DIR . '/' . $new_plugin)) {
                $plugin = $new_plugin;
            }
        }

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $result = activate_plugins($plugins);
        if (!is_wp_error($result)) {
            return array(
                'success' => true,
            );
        }

        /** @var WP_Error $result */
        $data = array(
            'success' => false,
            'error' => array(
                'code' => $result->get_error_code(),
                'message' => $result->get_error_message(),
            ),
        );

        if (count($plugins) > 1 && count($messages = $result->get_error_messages()) > 1) {
            $data['error']['messages'] = $messages;
        }

        return $data;
    }
}
