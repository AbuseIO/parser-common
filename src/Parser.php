<?php

namespace AbuseIO\Parsers;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use ReflectionClass;
use Uuid;
use Log;

/**
 * Class Parser
 * @package AbuseIO\Parsers
 */
class Parser
{
    /**
     * Configuration Basename (parser name)
     * @var string
     */
    public $configBase;

    /**
     * Filesystem object
     * @var object
     */
    public $fs;

    /**
     * Temporary working dir
     * @var string
     */
    public $tempPath;

    /**
     * Contains the name of the currently used feed within the parser
     * @var string
     */
    public $feedName = false;

    /**
     * Contains an array of found incidents that need to be handled
     * @var array
     */
    public $incidents = [ ];

    /**
     * Warning counter
     * @var integer
     */
    public $warningCount = 0;

    /**
     * Contains the Email
     * @var \PhpMimeMailParser\Parser
     */
    public $parsedEmail;

    /**
     * Contains the ARF mail
     * @var array
     */
    public $arfMail;

    /**
     * Create a new Parser instance
     *
     * @param \PhpMimeMailParser\Parser $parsedMail
     * @param array $arfMail
     * @param object $parser
     */
    public function __construct($parsedMail, $arfMail, $parser)
    {
        $this->parsedMail = $parsedMail;
        $this->arfMail = $arfMail;

        $this->startup($parser);
    }

    /**
     * Generalize the local config based on the parser class object.
     *
     * @param object $parser
     * @return void
     */
    protected function startup($parser)
    {
        $reflect = new ReflectionClass($parser);

        $this->configBase = 'parsers.' . $reflect->getShortName();

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

        if (empty(config("{$this->configBase}.feeds.{$this->feedName}"))) {
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
     * @return boolean Returns true or false
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
        } else {
            return true;
        }
    }

    /**
     * Check and see if a feed is enabled.
     * @return boolean Returns true or false
     */
    protected function isEnabledFeed()
    {
        if (config("{$this->configBase}.feeds.{$this->feedName}.enabled") !== true) {
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
     * Check if all required fields are in the report.
     * @param  array   $report Report data
     * @return boolean         Returns true or false
     */
    protected function hasRequiredFields($report)
    {
        if (is_array(config("{$this->configBase}.feeds.{$this->feedName}.fields"))) {
            $columns = array_filter(config("{$this->configBase}.feeds.{$this->feedName}.fields"));
            if (count($columns) > 0) {
                foreach ($columns as $column) {
                    if (!isset($report[$column])) {
                        Log::warning(
                            get_class($this) . ': ' .
                            config("{$this->configBase}.parser.name") . " feed '{$this->feedName}' " .
                            "says $column is required but is missing, therefore skipping processing of this incident"
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
     * @param  array   $report      The report that needs filtering base on config elements
     * @param  boolean $removeEmpty Option to remove empty fields from report, default is true
     * @return array   $report      The filtered version of the report
     */
    protected function applyFilters($report, $removeEmpty = true)
    {
        if ((!empty(config("{$this->configBase}.feeds.{$this->feedName}.filters"))) &&
            (is_array(config("{$this->configBase}.feeds.{$this->feedName}.filters")))
        ) {
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
                if ($value == "" && !is_bool($value)) {
                    unset($report[$field]);
                }
            }
        }

        return $report;
    }
}
