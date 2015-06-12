<?php

namespace AbuseIO\Parsers;

class Factory
{

    /**
     * @param string $email
     * @return Parser
     */
    public static function object($parsedMail, $arfMail)
    {

        // Todo - Build a array with all locally installed parsers
        $parsers = array("Shadowserver");

        foreach ($parsers as $p) {

            $found = false;

            $p = 'AbuseIO\Parsers\\' . $p;

            $parser = new $p($parsedMail, $arfMail);
            $config = $parser->getConfig();

            if ($config['parser']['enabled'] !== true) continue;

            foreach ($config['parser']['sender_map'] as $regex) {
                if (preg_match($regex, $parsedMail->getHeader('from'))) $found = true;
            }

            if (!$found) {
                foreach ($config['parser']['body_map'] as $regex) {
                    if (preg_match($regex, $parsedMail->getMessageBody())) $found = true;
                }
            }

            if (!$found) continue;

            $parser = new $p($parsedMail, $arfMail, $config);
            return $parser;
        }

        return false;
    }

}

