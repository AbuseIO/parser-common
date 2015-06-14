<?php

namespace AbuseIO\Parsers;

use \Illuminate\Config\Repository as Config;

class Parser
{

    public $config;
    public $configFile;

    public function __construct()
    {

        //

    }

    public function getConfig()
    {

        $config = new Config;
        $config->set(include($this->configFile));

        return $config->all();

    }

    protected function failed($message)
    {

        return
            [
                'errorStatus'   => true,
                'errorMessage'  => $message,
                'data'          => '',
            ];

    }

    protected function success($data)
    {

        return
            [
                'errorStatus'   => false,
                'errorMessage'  => 'Data sucessfully parsed',
                'data'          => $data,
            ];

    }

}

