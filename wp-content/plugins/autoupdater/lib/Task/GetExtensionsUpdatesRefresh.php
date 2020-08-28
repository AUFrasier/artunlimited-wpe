<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_GetExtensionsUpdatesRefresh extends AutoUpdater_Task_Base
{
    /**
     * @return array
     */
    public function doTask()
    {
        $type = $this->input('wpe_type', null);
        $force = (bool) $this->input('wpe_force', null);
        $updates_data_exists = false;

        switch ($type) {
            case 'plugin':
                $updates_data_exists = (bool) get_site_transient('update_plugins');
                if (!$updates_data_exists || $force) {
                    wp_update_plugins();
                }
                break;
            case 'theme':
                $updates_data_exists = (bool) get_site_transient('update_themes');
                if (!$updates_data_exists || $force) {
                    wp_update_themes();
                }
                break;
        }

        return array(
            'success' => true,
            'updates_data_exists' => $updates_data_exists,
        );
    }
}
