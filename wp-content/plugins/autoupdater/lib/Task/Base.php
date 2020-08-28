<?php
defined('AUTOUPDATER_LIB') or die;

abstract class AutoUpdater_Task_Base
{
    /**
     * API request payload
     * @var array $payload
     */
    protected $payload;

    /**
     * Encrypt the task response if the website is not secured with TLS
     * @var bool
     */
    protected $encrypt = true;

    /**
     * @var bool
     */
    protected $admin_privileges = false;

    /**
     * @var bool
     */
    protected $high_priority = true;

    public function __construct($payload)
    {
        $this->payload = (array) $payload;
    }

    /**
     * Handle the task and return the data for the API response
     *
     * @throws Exception
     * @throws AutoUpdater_Exception_Response
     *
     * @return array
     */
    abstract public function doTask();

    public function getName()
    {
        $parts = explode('_', get_class($this));

        return end($parts);
    }

    /**
     * @param string     $key
     * @param mixed|null $default
     *
     * @return mixed|null
     */
    public function input($key, $default = null)
    {
        if (array_key_exists($key, $this->payload)) {
            return $this->payload[$key];
        }
        if (substr($key, 0, 4) != 'wpe_' && array_key_exists('wpe_' . $key, $this->payload)) {
            return $this->payload['wpe_' . $key];
        }

        return $default;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    protected function setInput($key, $value)
    {
        $this->payload[$key] = $value;

        return $this;
    }

    /**
     * @return bool
     */
    public function isEncryptionRequired()
    {
        return $this->encrypt;
    }

    /**
     * @return bool
     */
    public function areAdminPrivilegesRequired()
    {
        return $this->admin_privileges;
    }

    /**
     * @return bool
     */
    public function isHighPriorityRequired()
    {
        return $this->high_priority;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        if ($this->isHighPriorityRequired()) {
            return 1;
        }

        $priority = $this->input('priority');
        if (is_numeric($priority)) {
            return (int) $priority;
        }

        return 11;
    }
}
