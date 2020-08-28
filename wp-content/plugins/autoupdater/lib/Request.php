<?php
defined('AUTOUPDATER_LIB') or die;

class AutoUpdater_Request
{
    protected static $timeout = 5;

    /**
     * @param string     $url
     * @param null|array $data
     * @param null|array $headers
     * @param null|int   $timeout
     *
     * @return AutoUpdater_Response
     */
    public static function get($url, $data = null, $headers = null, $timeout = null)
    {
        if (is_array($data)) {
            $query = array();
            foreach ($data as $key => $value) {
                $query[] = $key . '=' . rawurlencode($value);
            }

            if (!empty($query)) {
                $url .= (strpos($url, '?') === false ? '?' : '&') . implode('&', $query);
            }
        }

        $args = array(
            'sslverify' => AutoUpdater_Config::get('ssl_verify', 0) ? true : false,
            'timeout' => $timeout ? $timeout : static::$timeout,
        );

        if (!empty($headers)) {
            $args['headers'] = $headers;
        }

        AutoUpdater_Log::debug("GET $url\nArgs " . print_r($args, true));
        $result = wp_remote_get($url, $args);

        return AutoUpdater_Response::getInstance()
            ->bind($result);
    }

    /**
     * @param string            $url
     * @param null|array|string $data
     * @param null|array        $headers
     * @param null|int          $timeout
     *
     * @return AutoUpdater_Response
     *
     * @throws AutoUpdater_Exception_Response
     */
    public static function post($url, $data = null, $headers = null, $timeout = null)
    {
        $args = array(
            'sslverify' => AutoUpdater_Config::get('ssl_verify', 0) ? true : false,
            'timeout' => $timeout ? $timeout : static::$timeout,
        );

        if (!empty($headers)) {
            $args['headers'] = $headers;
        }

        if (!empty($data)) {
            if (
                isset($headers['Content-Type']) &&
                strpos($headers['Content-Type'], 'application/json') !== false &&
                !is_scalar($data)
            ) {
                $args['body'] = json_encode($data);
            } else {
                $args['body'] = $data;
            }
        }

        AutoUpdater_Log::debug("POST $url\nArgs " . print_r($args, true));
        $result = wp_remote_post($url, $args);

        return AutoUpdater_Response::getInstance()
            ->bind($result);
    }

    /**
     * @return string
     */
    public static function getCurrentUrl()
    {
        if (empty($_SERVER['HTTP_HOST'])) {
            return '';
        }

        return 'http' . (is_ssl() ? 's' : '')
            . '://'
            // Not form input data
            . $_SERVER['HTTP_HOST'] // phpcs:ignore
            . (!empty($_SERVER['REQUEST_URI']) ?
                parse_url(
                    filter_var(wp_unslash($_SERVER['REQUEST_URI']), FILTER_SANITIZE_URL),
                    PHP_URL_PATH
                ) : '');
    }

    /**
     * @param string     $method
     * @param string     $endpoint The endpoint is always prefixed with /site/ID/ by this method
     * @param null|array $data
     * @param int        $site_id
     *
     * @return string
     *
     * @throws AutoUpdater_Exception_Response
     */
    public static function getApiUrl($method, $endpoint, &$data = null, $site_id = 0)
    {
        if (!in_array($method, array('get', 'post'))) {
            throw new AutoUpdater_Exception_Response(sprintf('Invalid request method: %s', $method), 400);
        }

        $site_id = (int) $site_id ? $site_id : AutoUpdater_Config::get('site_id');
        $query = self::getApiUrlSignedQuery($method, $data);

        if (!$site_id || empty($query['wpe_signature'])) {
            throw new AutoUpdater_Exception_Response('Missing required parameters', 400);
        }

        foreach ($query as $key => $value) {
            $query[$key] = $key . '=' . rawurlencode($value);
        }

        return AutoUpdater_Config::getAutoUpdaterApiBaseUrl()
            . $site_id . '/'
            . trim($endpoint, '/')
            . '?' . implode('&', $query);
    }

    /**
     * @param string     $method
     * @param null|array $data
     *
     * @return array
     */
    public static function getApiUrlSignedQuery($method, &$data = null)
    {
        $query = array('wpe_nonce' => time());

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (strpos($key, 'wpe_') !== 0) {
                    continue;
                }
                $query[$key] = $value;
                unset($data[$key]);
            }
            $data = (object) $data;
        } else {
            $data = new stdClass;
        }

        ksort($query);
        $query['wpe_signature'] = AutoUpdater_Authentication::getInstance()
            ->getSignature(strtolower($method) != 'post' ? $query : array_merge($query, array('json' => json_encode($data))));

        return $query;
    }

    /**
     * @param string     $method
     * @param string     $endpoint The endpoint is always prefixed with /site/ID/ by this method
     * @param null|array $data
     * @param int        $site_id
     *
     * @return AutoUpdater_Response
     *
     * @throws AutoUpdater_Exception_Response
     */
    public static function api($method, $endpoint, $data = null, $site_id = 0)
    {
        $url = self::getApiUrl($method, $endpoint, $data, $site_id);

        return static::$method($url, $data, array(
            'Content-Type' => 'application/json',
        ));
    }

    /**
     * @param string $key
     * @param mixed $default
     * 
     * @return mixed
     */
    public static function getQueryVar($key, $default = null)
    {
        if (!array_key_exists($key, $_GET)) { // phpcs:ignore
            return $default;
        }

        return $_GET[$key]; // phpcs:ignore
    }
}
