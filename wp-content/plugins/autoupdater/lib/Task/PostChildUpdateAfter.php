<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_PostChildUpdateAfter extends AutoUpdater_Task_Base
{
    protected $encrypt = false;

    /**
     * @return array
     */
    public function doTask()
    {
        $installer = AutoUpdater_Installer::getInstance()
            ->setOption('site_id', (int) $this->input('site_id'));

        if (!$installer->update()) {
            return array(
                'success' => false,
                'message' => 'Failed to finish update',
            );
        }

        return array(
            'success' => true,
        );
    }
}
