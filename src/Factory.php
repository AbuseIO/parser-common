<?php

namespace AbuseIO\Parsers;

use Symfony\Component\ClassLoader\ClassMapGenerator;
use Illuminate\Support\Facades\Log;

/**
 * Class Factory
 * @package AbuseIO\Parsers
 */
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
     * @return array
     */
    public static function getParsers()
    {
        $parsers = [];
        $parserClassList = ClassMapGenerator::createMap(base_path().'/vendor/abuseio');
        /** @noinspection PhpUnusedParameterInspection */
        $parserClassListFiltered = array_where(
            array_keys($parserClassList),
            function ($key, $value) {
                // Get all parsers, ignore all other packages.
                if (strpos($value, 'AbuseIO\Parsers\\') !== false) {
                    return $value;
                }
                return false;
            }
        );

        $parserList = array_map('class_basename', $parserClassListFiltered);
        foreach ($parserList as $parser) {
            if (!in_array($parser, ['Factory', 'Parser'])) {
                $parsers[] = $parser;
            }
        }
        return $parsers;
    }

    /**
     * Create and return a Parser class and it's configuration
     * @param  \PhpMimeMailParser\Parser $parsedMail
     * @param  array $arfMail
     * @return object parser
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
            if (config("parsers.{$parserName}.parser.enabled") === true) {
                // Check the sender address
                foreach (config("parsers.{$parserName}.parser.sender_map") as $regex) {
                    if (preg_match($regex, $parsedMail->getHeader('from'))) {
                        return new $parserClass($parsedMail, $arfMail);
                    }
                }

                // If no valid sender is found, check the body
                foreach (config("parsers.{$parserName}.parser.body_map") as $regex) {
                    if (preg_match($regex, $parsedMail->getMessageBody())) {
                        return new $parserClass($parsedMail, $arfMail);
                    }

                    if ($arfMail !== false) {
                        foreach ($arfMail as $mailPart) {
                            if (preg_match($regex, $mailPart)) {
                                return new $parserClass($parsedMail, $arfMail);
                            }
                        }
                    }
                }
            } else {
                Log::info(
                    'AbuseIO\Parsers\Factory: ' .
                    "The parser {$parserName} has been disabled and will not be used for this message."
                );
            }
        }

        // No valid parsers found
        return false;
    }
}
