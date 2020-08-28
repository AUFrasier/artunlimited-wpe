<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_PostChildUpdate extends AutoUpdater_Task_Base
{
    protected $encrypt = false;

    /**
     * @return array
     */
    public function doTask()
    {
        $data = array('wpe_provider' => 'wpengine');
        $this->setInput('type', 'plugin');
        $this->setInput('slug', AUTOUPDATER_WP_PLUGIN_SLUG);
        $this->setInput('path', AutoUpdater_Request::getApiUrl('get', 'download/worker.zip', $data, $this->input('site_id')));

        return AutoUpdater_Task::getInstance('PostExtensionUpdate', $this->payload)
            ->doTask();
    }
}
