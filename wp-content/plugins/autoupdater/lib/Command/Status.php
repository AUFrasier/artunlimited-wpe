<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Command_Status extends AutoUpdater_Command_Base
{
    /**
     * Gets AutoUpdater status
     *
     * ## OPTIONS
     *
     * [--date=<date>]
     * : Get updates performed on a given date. Format: YYYY-MM-DD, all times are UTC. Defaults to the date of the last batch of updates.
     *
     * [--output=<format>]
     * : .
     * ---
     * default: yaml
     * options:
     *   - json
     *   - yaml
     *
     * @when before_wp_load
     */
    public function __invoke($args, $assoc_args)
    {
        $data = null;
        if (!empty($assoc_args['date'])) {
            $data = array('wpe_date' => $assoc_args['date']);
        }
        $response = AutoUpdater_Request::api('get', 'updates', $data);
        if ($response->code !== 200) {
            WP_CLI::error(sprintf('API request failed with %d HTTP code.', $response->code));
        }

        if (!isset($response->body->updates)) {
            WP_CLI::error('Invalid API response.');
        }

        if ($assoc_args['output'] === 'json') {
            WP_CLI::line(json_encode(
                $response->body->updates,
                JSON_PRETTY_PRINT // phpcs:ignore PHPCompatibility.Constants.NewConstants
            ));
        } elseif ($assoc_args['output'] === 'yaml') {
            WP_CLI\Utils\format_items('yaml', $response->body->updates, array(
                'id',
                'type',
                'state',
                'started_at',
                'finished_at',
                'finish_reason',
                'maintenance_enabled_at',
                'maintenance_disabled_at',
                'plugins',
                'testcases',
                'backup',
                'errors',
            ));
        }
    }

    public static function beforeInvoke()
    {
        if (!AutoUpdater_Config::get('site_id')) {
            WP_CLI::error('The site ID is missing.');
        }
        if (!AutoUpdater_Config::get('worker_token')) {
            WP_CLI::error('The worker token is missing.');
        }
    }
}
