<?php

namespace AbuseIO\Parsers;

use Illuminate\Filesystem\Filesystem;
use Uuid;
use Log;

class Parser
{
    /**
     * Configuration Basename (parser name)
     * @var String
     */
    public $configBase;

    /**
     * Filesystem object
     * @var Object
     */
    public $fs;

    /**
     * Temporary working dir
     * @var String
     */
    public $tempPath;

    /**
     * Contains the name of the missing field that is required
     * @var String
     */
    public $requiredField;

    /**
     * Contains the name of the currently used feed within the parser
     * @var String
     */
    public $feedName;

    /**
     * Contains an array of found events that need to be handled
     * @var Array
     */
    public $events = [ ];

    /**
     * Warning counter
     * @var Integer
     */
    public $warningCount = 0;

    public $parsedEmail;
    public $arfMail;

    /**
     * Create a new Parser instance
     */
    public function __construct($parsedMail, $arfMail)
    {
        //
        $this->parsedMail = $parsedMail;
        $this->arfMail = $arfMail;
    }

    /**
     * Return failed
     * @param  String $message
     * @return Array
     */
    protected function failed($message)
    {
        $this->cleanup();

        return [
            'errorStatus'   => true,
            'errorMessage'  => $message,
            'warningCount'  => $this->warningCount,
            'data'          => false,
        ];
    }

    /**
     * Return success
     * @param  String $message
     * @return Array
     */
    protected function success()
    {
        $this->cleanup();

        if (empty($data)) {
            Log::warning(
                'The parser ' . config("{$this->configBase}.parser.name") . ' did not return any events which ' .
                'should be investigated for parser and/or configuration errors'
            );
        }

        return [
            'errorStatus'   => false,
            'errorMessage'  => 'Data sucessfully parsed',
            'warningCount'  => $this->warningCount,
            'data'          => $this->events,
        ];
    }

    /**
     * Cleanup anything a parser might have left (basically, remove the working dir)
     * @return Boolean              Returns true or false
     */
    protected function cleanup()
    {
        // if $this->fs is an object, the Filesystem has been used, clean it up.
        if (is_object($this->fs)) {
            if ($this->fs->isDirectory($this->tempPath)) {
                $this->fs->deleteDirectory($this->tempPath, false);
            }
        }
    }

    /**
     * Setup a working directory for the parser
     * @return Boolean              Returns true or call $this->failed()
     */
    protected function createWorkingDir()
    {
        $uuid = Uuid::generate(4);
        $this->tempPath = "/tmp/abuseio-{$uuid}/";
        $this->fs = new Filesystem;

        if (!$this->fs->makeDirectory($this->tempPath)) {
            return $this->failed("Unable to create directory {$this->tempPath}");
        }
        return true;
    }

    /**
     * Check if the feed specified is known in the parser config.
     * @param  String   $configBase Configuration Base current parser
     * @param  String   $feedName   Current feed name
     * @return Boolean              Returns true or false
     */
    protected function isKnownFeed()
    {
        if (empty(config("{$this->configBase}.feeds.{$this->feedName}"))) {
            $this->warningCount++;
            Log::warning(
                "The feed referred as '{$this->feedName}' is not configured in the parser " .
                config("{$this->configBase}.parser.name") .
                ' therefore skipping processing of this e-mail'
            );
            return false;
        } else {
            return true;
        }
    }

    /**
     * Check if a valid arfMail was passed along which is required when called.
     * @param  String   $configBase Configuration Base current parser
     * @param  String   $feedName   Current feed name
     * @return Boolean              Returns true or false
     */
    protected function hasArfMail()
    {
        if ($this->arfMail === false) {
            $this->warningCount++;
            Log::warning(
                "The feed referred as '{$this->feedName}' has an ARF requirement that was not met"
            );
            return false;
        } else {
            return true;
        }
    }

    /**
     * Check and see if a feed is enabled.
     * @param  String   $configBase Configuration Base current parser
     * @param  String   $feedName   Current feed name
     * @return Boolean              Returns true or false
     */
    protected function isEnabledFeed()
    {
        if (config("{$this->configBase}.feeds.{$this->feedName}.enabled") !== true) {
            Log::warning(
                "The feed '{$this->feedName}' is disabled in the configuration of parser " .
                config("{$this->configBase}.parser.name") .
                ' therefore skipping processing of this e-mail'
            );
        }
        return true;
    }

    /**
     * Check if all required fields are in the report.
     * @param  String   $configBase Configuration Base current parser
     * @param  String   $feedName   Current feed name
     * @param  Array    $report     Report data
     * @return Boolean              Returns true or false
     */
    protected function hasRequiredFields($report)
    {
        if (is_array(config("{$this->configBase}.feeds.{$this->feedName}.fields"))) {
            $columns = array_filter(config("{$this->configBase}.feeds.{$this->feedName}.fields"));
            if (count($columns) > 0) {
                foreach ($columns as $column) {
                    if (!isset($report[$column])) {
                        Log::warning(
                            config("{$this->configBase}.parser.name") . " feed '{$this->feedName}' " .
                            "says $column is required but is missing, therefore skipping processing of this e-event"
                        );
                        $this->warningCount++;
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Filter the unwanted and empty fields from the report.
     * @param   Array   $report      The report that needs filtering base on config elements
     * @param   Boolean $removeEmpty Option to remove empty fields from report, default is true
     * @return Array
     */
    protected function applyFilters($report, $removeEmpty = true)
    {
        if (is_array("{$this->configBase}.feeds.{$this->feedName}.filters")) {
            $filter_columns = array_filter(config("{$this->configBase}.feeds.{$this->feedName}.filters"));
            foreach ($filter_columns as $column) {
                if (!empty($report[$column])) {
                    unset($report[$column]);
                }
            }
        }

        // No sense in adding empty fields, so we remove them
        if ($removeEmpty) {
            foreach ($report as $field => $value) {
                if ($value == "") {
                    unset($report[$field]);
                }
            }
        }

        return $report;
    }
}
