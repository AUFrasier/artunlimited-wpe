<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_GetChildVerify extends AutoUpdater_Task_Base
{
    protected $encrypt = false;

    /**
     * @return array
     */
    public function doTask()
    {
        $data = array(
            'success' => true,
        );

        return $data;
    }
}
