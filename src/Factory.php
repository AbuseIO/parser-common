<?php

namespace AbuseIO\Parsers;

use Symfony\Component\ClassLoader\ClassMapGenerator;

class Factory
{
    /**
     * Create a new Factory instance
     */
    public function __construct()
    {
        //
    }

    /**
     * Get a list of installed AbuseIO parsers and return as an array
     * @return Array
     */
    public static function getParsers()
    {
        $parserClassList = ClassMapGenerator::createMap(base_path().'/vendor/abuseio');
        $parserList = array_map('class_basename', array_keys($parserClassList));
        foreach ($parserList as $parser) {
            if (!in_array($parser, ['Factory', 'Parser'])) {
                $parsers[] = $parser;
            }
        }
        return $parsers;
    }

    /**
     * Create and return a Parser class and it's configuration
     * @param  String $parsedMail
     * @param  String $arfMail
     * @return Class
     */
    public static function create($parsedMail, $arfMail)
    {
        /**
         * Loop through the parser list and try to find a match by
         * validating the send or the body according to the parsers'
         * configuration.
         */
        $parsers = Factory::getParsers();
        foreach ($parsers as $parserName) {
            $parserClass = 'AbuseIO\\Parsers\\' . $parserName;

            // Parser is enabled, see if we can match it's sender_map or body_map
            if (config("{$parserName}.parser.enabled") === true) {
                // Check the sender address
                foreach (config("{$parserName}.parser.sender_map") as $regex) {
                    if (preg_match($regex, $parsedMail->getHeader('from'))) {
                        return new $parserClass($parsedMail, $arfMail);
                    }
                }

                // If no valid sender is found, check the body
                foreach (config("{$parserName}.parser.body_map") as $regex) {
                    if (preg_match($regex, $parsedMail->getMessageBody())) {
                        return new $parserClass($parsedMail, $arfMail);
                    }
                }
            }
        }

        // No valid parsers found
        return false;
    }
}
