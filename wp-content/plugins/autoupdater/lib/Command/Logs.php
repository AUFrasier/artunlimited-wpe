<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Command_Logs extends AutoUpdater_Command_Base
{
    /**
     * Gets logs
     * 
     * ## OPTIONS
     * 
     * [--date=<date>]
     * : Get logs from a given date. Format: YYYY-MM-DD, all times are UTC. Defaults to the current date.
     * 
     * @when before_wp_load
     */
    public function __invoke($args, $assoc_args)
    {
        /** @var AutoUpdater_Task_GetDebugLogs $task */
        $task = AutoUpdater_Task::getInstance('GetDebugLogs', $assoc_args);

        try {
            $result = $task->doTask();
        } catch (Exception $e) {
            WP_CLI::error($e->getMessage());
            return;
        }

        WP_CLI::line($result['logs']);
    }

    public static function beforeInvoke()
    {
    }
}
