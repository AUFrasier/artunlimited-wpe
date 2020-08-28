<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Task_GetEnvironment extends AutoUpdater_Task_Base
{
    /**
     * @return array
     */
    public function doTask()
    {
        global $wpdb;

        $data = array(
            'success' => true,
            'cms_version' => AUTOUPDATER_WP_VERSION,
            'cms_language' => AutoUpdater_Config::getSiteLanguage(),
            'php_version' => php_sapi_name() !== 'cli' ? PHP_VERSION : '',
            'os' => php_uname('s'),
            'server' => isset($_SERVER['SERVER_SOFTWARE']) ? filter_var(wp_unslash($_SERVER['SERVER_SOFTWARE']), FILTER_SANITIZE_STRING) : '',
            /** $wpdb->is_mysql @since 3.3.0 */
            'database_name' => version_compare(AUTOUPDATER_WP_VERSION, '3.3.0', '<') || $wpdb->is_mysql ? 'MySQL' : '',
            'database_version' => $wpdb->db_version(),
            'hostname' => gethostname(),
            'install_name' => defined('PWP_NAME') ? PWP_NAME : '',
            'cluster_id' => defined('WPE_CLUSTER_ID') ? WPE_CLUSTER_ID : 0,
        );

        $database_version_info = $wpdb->get_var('SELECT version()');
        if (!empty($database_version_info) && strpos(strtolower($database_version_info), 'mariadb') !== false) {
            $data['database_name'] = 'MariaDB';
            $version = explode('-', $database_version_info);
            if (!empty($version[0])) {
                $data['database_version'] = $version[0];
            }
        }

        return $data;
    }
}
