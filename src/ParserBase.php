<?php

namespace AbuseIO\Parsers;

use Illuminate\Filesystem\Filesystem;
use Uuid;
use Log;

abstract class ParserBase
{
    /**
     * @var string Configuration Basename (parser name)
     */
    public $configBase;

    /**
     * @var object Filesystem object
     */
    public $fs;

    /**
     * @var string Temporary working dir
     */
    public $tempPath;

    /**
     * @var string name of the feed that is currently used
     */
    public $feedName = false;

    /**
     * @var array found incidents that need to be handled
     */
    public $incidents = [ ];

    /**
     * @var integer warning counter
     */
    public $warningCount = 0;

    /**
     * @var \PhpMimeMailParser\Parser Contains the Email
     */
    public $parsedEmail;

    /**
     * @var array Contains the ARF mail
     */
    public $arfMail;

    /**
     * @param \PhpMimeMailParser\Parser $parsedMail
     * @param array $arfMail
     */
    public function __construct($parsedMail, $arfMail)
    {
        $this->parsedMail = $parsedMail;
        $this->arfMail = $arfMail;

        $this->startup();
    }

    /**
     * Generalize the local config based on the parser class object.
     *
     * @return void
     */
    protected function startup()
    {
        if (!isset($this->configBase)) {
            $this->configBase = 'parsers.' . $this->getShortName();
        }

        if (empty(config("{$this->configBase}.parser.name"))) {
            $this->failed("Required parser.name is missing in parser configuration");
        }

        Log::info(
            get_class($this) . ': ' .
            'Received message from: ' . $this->parsedMail->getHeader('from') . " with subject: '" .
            $this->parsedMail->getHeader('subject') . "' arrived at parser: " .
            config("{$this->configBase}.parser.name")
        );

    }

    /**
     * Return failed
     * @param  string $message
     * @return array
     */
    protected function failed($message)
    {
        $this->cleanup();

        Log::warning(
            get_class($this) . ': ' .
            'Parser run failed for module ' . config("{$this->configBase}.parser.name")
            . ' has ended with errors ' . $message
        );

        return [
            'errorStatus'   => true,
            'errorMessage'  => $message,
            'warningCount'  => $this->warningCount,
            'data'          => false,
        ];
    }

    /**
     * Return success
     * @return array
     */
    protected function success()
    {
        $this->cleanup();

        /*
         * Empty mail parsing results is useally a problem. So if the resultset is empty we set a single warning
         * to trigger an alert if the warning is set to error in the config.
         */
        if (empty($this->incidents)) {
            $this->warningCount++;
            Log::warning(
                get_class($this) . ': ' .
                'The parser ' . config("{$this->configBase}.parser.name") . ' did not return any incidents which ' .
                'should be investigated for parser and/or configuration errors'
            );
        }

        Log::info(
            get_class($this) . ': ' .
            'Parser run completed for module : ' . config("{$this->configBase}.parser.name")
        );

        return [
            'errorStatus'   => false,
            'errorMessage'  => 'Data sucessfully parsed',
            'warningCount'  => $this->warningCount,
            'data'          => $this->incidents,
        ];
    }

    /**
     * Cleanup anything a parser might have left (basically, remove the working dir)
     * @return void
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
     * @return boolean Returns true or call $this->failed()
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
     * @return boolean Returns true or false
     */
    protected function isKnownFeed()
    {
        if ($this->feedName === false) {
            return $this->failed("Parser did not set the required feedName value");
        }

        $currentFeed = config("{$this->configBase}.feeds.{$this->feedName}");
        if (empty($currentFeed)) {
            $this->warningCount++;
            Log::warning(
                get_class($this) . ': ' .
                "The feed referred as '{$this->feedName}' is not configured in the parser " .
                config("{$this->configBase}.parser.name") .
                ' therefore skipping processing of this e-mail'
            );
            return false;
        }

        return true;
    }

    /**
     * Check if a valid arfMail was passed along which is required when called.
     * @return boolean $this->arfMail !== false
     */
    protected function hasArfMail()
    {
        if ($this->arfMail === false) {
            $this->warningCount++;
            Log::warning(
                get_class($this) . ': ' .
                "The feed referred as '{$this->feedName}' has an ARF requirement that was not met"
            );
            return false;
        }
        return true;
    }

    /**
     * @return boolean true if the current feed is enabled in the config, false otherwise
     */
    protected function isEnabledFeed()
    {
        $currentFeedEnabled = config("{$this->configBase}.feeds.{$this->feedName}.enabled");
        if ($currentFeedEnabled !== true) {
            Log::warning(
                get_class($this) . ': ' .
                "The feed '{$this->feedName}' is disabled in the configuration of parser " .
                config("{$this->configBase}.parser.name") .
                ' therefore skipping processing of this e-mail'
            );
            return false;
        }
        return true;
    }

    /**
     * @param  array   $report Report data
     * @return boolean         Returns true if all required fields are in the report, false otherwise
     */
    protected function hasRequiredFields($report)
    {
        $feedFields = config("{$this->configBase}.feeds.{$this->feedName}.fields");
        $columns = array_filter($feedFields);
        foreach ($columns as $column) {
            if (isset($report[$column])) {
                continue;
            }
            Log::warning(
                get_class($this) . ': ' .
                config("{$this->configBase}.parser.name") . " feed '{$this->feedName}' " .
                "says $column is required but is missing, therefore skipping processing of this incident"
            );
            $this->warningCount++;
            return false;
        }

        return true;
    }

    /**
     * Filter the unwanted and empty fields from the report.
     * @param  array   $report      The report that needs filtering base on config elements
     * @param  boolean $removeEmpty Option to remove empty fields from report, default is true
     * @return array   $report      The filtered version of the report
     */
    protected function applyFilters($report, $removeEmpty = true)
    {
        $filter_columns = config("{$this->configBase}.feeds.{$this->feedName}.filters");
        if ((!empty($filter_columns)) && (is_array($filter_columns))) {
            $filter_columns = array_filter($filter_columns); // remove empty values
            $filter_columns = array_flip($filter_columns); // make values keys
            $report = array_diff_key($report, $filter_columns); // remove keys from $report
        }

        if ($removeEmpty) {
            // No sense in adding empty fields, so we remove them
            $report = array_filter($report, function($key, $value){
                if (is_bool($value)) {
                    return true;
                }
                if ($value != "") {
                    return true;
                }
                return false;
            });
        }

        return $report;
    }
}
