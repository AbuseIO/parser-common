<?php

namespace AbuseIO\Parsers;

class Factory extends Parser
{

//    public function __construct() 
//    {
//    }

    /**
     * @param string $email
     * @return Parser
     */
    public static function mapFrom($email) 
    {

        $parsers = array("Shadowserver", "google");

        foreach ($parsers as $parser) {
            $parser = 'AbuseIO\Parsers\\' . $parser;

            $config = call_user_func( array($parser, "getConfig"));
            $regexs = $config['notifier']['sender_map'];

            $found = false;
            foreach ($regexs as $regex) {
                if (preg_match($regex, $email)) $found = true;
            }

            if (!$found) continue;

            //$parser = new $parser;
            return $parser;
        }

        return false;
    }

    /**
     * @param string $body
     * @return Parser
     */
    public static function mapBody($body) 
    {

        return false;

    }
}

