<?php

namespace AbuseIO\Parsers;

use \Illuminate\Config\Repository as Config;

class Parser
{

    public $config;
    public $configFile;

    /**
     * Create a new Parser instance
     */
    public function __construct()
    {
        //
    }

    /**
     * Load parsers' configuration file
     * @return array
     */
    public function getConfig()
    {
        $this->config = new Config;
        $this->config->set(include($this->configFile));
        return $this->config->all();
    }

    /**
     * Return failed
     * @param  string $message
     * @return array
     */
    protected function failed($message)
    {
        return [
            'errorStatus'   => true,
            'errorMessage'  => $message,
            'data'          => '',
        ];
    }

    /**
     * Return success
     * @param  string $message
     * @return array
     */
    protected function success($data)
    {
        return [
            'errorStatus'   => false,
            'errorMessage'  => 'Data sucessfully parsed',
            'data'          => $data,
        ];
    }
}
