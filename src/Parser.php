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

}

