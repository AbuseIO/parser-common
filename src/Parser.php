<?php

namespace AbuseIO\Parsers;

abstract class Parser {

    protected $config;
    public $parsedMail;
    public $arfMail;

    public function __construct($config, $parsedMail, $arfMail) {

        $this->config = $config;
        $this->parsedMail = $parsedMail;
        $this->arfMail = $arfMail;
    }

}

