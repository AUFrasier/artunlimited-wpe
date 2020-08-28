<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_PostChildSettingsRefresh extends AutoUpdater_Task_Base
{
    /**
     * @return array
     */
    public function doTask()
    {
        return array(
            'success' => AutoUpdater_Config::loadAutoUpdaterConfigByApi(),
        );
    }
}
