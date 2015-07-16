<?php

namespace AbuseIO\Parsers;

use Symfony\Component\ClassLoader\ClassMapGenerator;

class Factory
{
    public $parsers;

    public function __construct()
    {
    }
    /**
     * Create and return a Parser class and it's configuration
     * @param  string $parsedMail
     * @param  string $arfMail
     * @return Class
     */
    public static function create($parsedMail, $arfMail)
    {
        // Create array with available parsers, skip parsers-common
        $installedParsers = ClassMapGenerator::createMap(base_path().'/vendor/abuseio');
        $parsers = [ ];
        foreach ($installedParsers as $key => $val) {
            $key = class_basename($key);
            if (!in_array($key, ['Parser', 'Factory'], true)) {
                $parsers[] = $key;
            }
        }

        /**
         *  Loop through all parsers and see which one we should use.
         *  We'll validate the send or the body according to the parsers'
         *  configuration
         */
        foreach ($parsers as $parserName) {
            // Create parser and get config
            $parserName = 'AbuseIO\Parsers\\' . $parserName;
            $parser = new $parserName($parsedMail, $arfMail);
            $config = $parser->getConfig();

            // Parser is disabled, go to the next one
            if ($config['parser']['enabled'] !== true) {
                continue;
            };

            // Check the sender address
            foreach ($config['parser']['sender_map'] as $regex) {
                if (preg_match($regex, $parsedMail->getHeader('from'))) {
                    return $parser;
                }
            }

            // If no valid sender is found, check the body
            foreach ($config['parser']['body_map'] as $regex) {
                if (preg_match($regex, $parsedMail->getMessageBody())) {
                    return $parser;
                }
            }
        }

        // No valid parsers found
        return false;
    }
}
