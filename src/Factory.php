<?php

namespace AbuseIO\Parsers;

use Composer\Autoload\ClassMapGenerator;
use Illuminate\Support\Facades\Log;

class Factory
{
    /**
     * Fetch AbuseIO parsers available in ./vendor/abuseio and return their basenames.
     * AbuseIO parsers are classes that have a basename of Factory or Parser.
     * @return array available AbuseIO parsers
     */
    public static function getParsers($classAndBasenames = false)
    {
        $allowedBasenames = [0 => 'Factory', 1 => 'Parser', ];

        // @var string[] $vendorAbuseioClasses = [$className => $classFile, ...]
        $vendorAbuseioClasses = ClassMapGenerator::createMap(base_path().'/vendor/abuseio');

        $parsers = collect($vendorAbuseioClasses)
            ->transform(function ($className) {
                $hasAbuseioParserNamespace = strpos($className, 'AbuseIO\\Parsers\\');
                if ($hasAbuseioParserNamespace === false) {
                    return null;
                }
                $basename = class_basename($item);
                $parserHasAllowedBasename = isset($allowedBasenames[$basename]);
                if ($parserHasAllowedBasename === false) {
                    $className = null;
                    return null;
                }
                if ($classAndBasenames) {
                    return ['basename' => $basename, 'class' => $className, ];
                } else {
                    return $basename;
                }
            }
        )->filter()->toArray();
        
        return $parsers;
    }

    /**
     * Create and return a Parser class and it's configuration
     * @param  \PhpMimeMailParser\Parser $parsedMail
     * @param  array $arfMail
     * @return object|false parser
     */
    public static function create($parsedMail, $arfMail)
    {
        /**
         * Loop through the parser list and try to find a match by
         * validating the send or the body according to the parsers'
         * configuration.
         */
        $parsers = Factory::getParsers($classAndBasenames=true);
        foreach ($parsers as $parser) {
            $parserClass = $parser['class'];
            $parserName = $parser['basename'];

            if (config("parsers.{$parserName}.parser.enabled") !== true) {
                Log::info(
                    'AbuseIO\Parsers\Factory: ' .
                    "The parser {$parserName} has been disabled and will not be used for this message."
                );
                return false;
            }
            // Parser is enabled, see if we can match it's sender_map or body_map

            // Check validity of the 'report_file' setting before we continue
            // If no report_file is used, continue w/o validation
            $reportFile = config("parsers.{$parserName}.parser.report_file");
            $isValidReport = isValidRegex($reportFile);
            if ($isValidReport === false) {
                Log::warning(
                    'AbuseIO\Parsers\Factory: ' .
                    "The parser {$parserName} has an invalid value for 'report_file' (not a regex)."
                );
                // continue with next parser
                break;
            }
            // report_file is Valid Report

            // Check the sender address
            $senderMaps = config("parsers.{$parserName}.parser.sender_map");
            foreach ($senderMaps as $senderMap) {
                if (!isValidRegex($senderMap)) {
                    Log::warning(
                        'AbuseIO\Parsers\Factory: ' .
                        "The parser {$parserName} has an invalid value for 'sender_map' (not a regex)."
                    );
                    // continue with next sendermap
                    break;
                }
                $senderMapMatched = preg_match($senderMap, $parsedMail->getHeader('from'));
                if ($senderMapMatched) {
                    return new $parserClass($parsedMail, $arfMail);
                }
            }

            // If no valid sender is found, check the body
            $bodyMaps = config("parsers.{$parserName}.parser.body_map");
            foreach ($bodyMaps as $bodyMap) {
                if (!isValidRegex($bodyMap)) {
                    Log::warning(
                        'AbuseIO\Parsers\Factory: ' .
                        "The parser {$parserName} has an invalid value for 'body_map' (not a regex)."
                    );
                    // continue with next bodymap
                    break;
                }
                $bodyMapMatched = preg_match($bodyMap, $parsedMail->getMessageBody());
                if ($bodyMapMatched) {
                    return new $parserClass($parsedMail, $arfMail);
                }
                if ($arfMail === false) {
                    break;
                }
                foreach ($arfMail as $mailPart) {
                    $bodyMapMatched = preg_match($bodyMap, $mailPart);
                    if ($bodyMapMatched) {
                        return new $parserClass($parsedMail, $arfMail);
                    }
                }
            }
        }

        // No valid parsers found
        return false;
    }
}
