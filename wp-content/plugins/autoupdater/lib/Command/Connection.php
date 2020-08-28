<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Command_Connection extends AutoUpdater_Command_Base
{
    /**
     * Gets signed URL to AutoUpdater Worker Plugin valid for 5 minutes to test connection
     *
     * ## OPTIONS
     * 
     * [--frontend]
     * : Output URL to website front-end instead of back-end
     * 
     * [--endpoint=<endpoint>]
     * : Request endpoint.
     * 
     * [--method=<METHOD>]
     * : Request method.
     * ---
     * default: GET
     * options:
     *   - GET
     *   - POST
     * 
     * @when before_wp_load
     */
    public function __invoke($args, $assoc_args)
    {
        $method = !empty($assoc_args['method']) ? strtoupper($assoc_args['method']) : 'GET';
        $endpoint = !empty($assoc_args['endpoint']) ? $assoc_args['endpoint'] : 'child/verify';
        $frontend = isset($assoc_args['frontend']);
        $data = array();

        WP_CLI::line($this->getWorkerPluginApiUrl($method, $endpoint, $data, $frontend));
    }

    protected function getWorkerPluginApiUrl($method = 'GET', $endpoint = 'child/verify', &$data = array(), $frontend = false)
    {
        $data['wpe_timestamp'] = time() + (5 * 60);
        $data['wpe_endpoint'] = $endpoint;

        $query = AutoUpdater_Request::getApiUrlSignedQuery($method, $data);
        foreach ($query as $key => $value) {
            $query[$key] = $key . '=' . rawurlencode($value);
        }
        $query = implode('&', $query);

        if ($frontend) {
            return site_url('?autoupdater=api') . '&' . $query;
        }

        return admin_url('admin-ajax.php?action=autoupdater_api&autoupdater=api') . '&' . $query;
    }

    public static function beforeInvoke()
    {
    }
}
