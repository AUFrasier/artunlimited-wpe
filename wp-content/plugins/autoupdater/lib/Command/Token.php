<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Command_Token extends AutoUpdater_Command_Base
{
    /**
     * Gets token
     * 
     * @when before_wp_load
     */
    public function __invoke($args, $assoc_args)
    {
        /** @var AutoUpdater_Task_GetChildToken $task */
        $task = AutoUpdater_Task::getInstance('GetChildToken');

        WP_CLI::line($task->getToken());
    }

    public static function beforeInvoke()
    {
    }
}
