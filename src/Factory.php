<?php

namespace AbuseIO\Parsers;

use Composer\Autoload\ClassMapGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

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
        $parserClassListFiltered = Arr::where(
            Arr::keys($parserClassList),
            function ($value, $key) {
                // Get all parsers, ignore all other packages.
                if (strpos($value, 'AbuseIO\Parsers\\') !== false) {
                    return $value;
                }
                return false;
            }
        );

        $parserList = Arr::map('class_basename', $parserClassListFiltered);
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

                // Check validity of the 'report_file' setting before we continue
                // If no report_file is used, continue w/o validation
                $report_file = config("parsers.{$parserName}.parser.report_file");
                if ($report_file == null ||
                    (is_string($report_file) && isValidRegex($report_file))
                ) {
                    $isValidReport = true;
                } else {
                    $isValidReport = false;

                    Log::warning(
                        'AbuseIO\Parsers\Factory: ' .
                        "The parser {$parserName} has an invalid value for 'report_file' (not a regex)."
                    );
                    break;
                }

                // Check the sender address
                foreach (config("parsers.{$parserName}.parser.sender_map") as $senderMap) {
                    if (isValidRegex($senderMap)) {
                        if (preg_match($senderMap, $parsedMail->getHeader('from')) && $isValidReport) {
                            return new $parserClass($parsedMail, $arfMail);
                        }
                    } else {
                        Log::warning(
                            'AbuseIO\Parsers\Factory: ' .
                            "The parser {$parserName} has an invalid value for 'sender_map' (not a regex)."
                        );
                    }
                }

                // If no valid sender is found, check the body
                foreach (config("parsers.{$parserName}.parser.body_map") as $bodyMap) {
                    if (isValidRegex($bodyMap)) {
                        if (preg_match($bodyMap, $parsedMail->getMessageBody()) && $isValidReport) {
                            return new $parserClass($parsedMail, $arfMail);
                        }

                        if ($arfMail !== false) {
                            foreach ($arfMail as $mailPart) {
                                if (preg_match($bodyMap, $mailPart)) {
                                    return new $parserClass($parsedMail, $arfMail);
                                }
                            }
                        }
                    } else {
                        Log::warning(
                            'AbuseIO\Parsers\Factory: ' .
                            "The parser {$parserName} has an invalid value for 'body_map' (not a regex)."
                        );
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
