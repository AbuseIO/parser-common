<?php

namespace AbuseIO\Parsers;

class Parser extends ParserBase
{
    /** @override */
    public function __construct($parsedMail, $arfMail, $parser)
    {
        parent::__construct($parsedMail, $arfMail);
        $this->startup($parser);
    }

    /** @override */
    protected function startup($parser)
    {
        if (!function_exists('class_basename')) {
            function class_basename($fullyQualifiedClassName) {
                return substr(strrchr($fullyQualifiedClassName, '\\'), 1);
            }
        }
        $this->configBase = 'parsers.' . class_basename($parser);
        parent::startup();
    }

}

