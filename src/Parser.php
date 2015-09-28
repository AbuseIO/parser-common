<?php

namespace AbuseIO\Parsers;

use Illuminate\Filesystem\Filesystem;
use Uuid;

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
     * Create a new Parser instance
     */
    public function __construct()
    {
        //
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
            'data'          => '',
        ];
    }

    /**
     * Return success
     * @param  String $message
     * @return Array
     */
    protected function success($data)
    {
        $this->cleanup();

        return [
            'errorStatus'   => false,
            'errorMessage'  => 'Data sucessfully parsed',
            'data'          => $data,
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
        $this->tempPath = "/tmp/{$uuid}/";
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
    protected function isKnownFeed($feedName)
    {
        return (empty(config("{$this->configBase}.feeds.{$feedName}"))) ? false : true;
    }

    /**
     * Check and see if a feed is enabled.
     * @param  String   $feedName   Current feed name
     * @return Boolean              Returns true or false
     */
    protected function isEnabledFeed($feedName)
    {
        return (config("{$this->configBase}.feeds.{$feedName}.enabled") === true) ? true : false;
    }

    /**
     * Check if all required fields are in the report.
     * @param  String   $configBase Configuration Base current parser
     * @param  String   $feedName   Current feed name
     * @param  Array    $report     Report data
     * @return Boolean              Returns true or false
     */
    protected function hasRequiredFields($feedName, $report)
    {
        $columns = array_filter(config("{$this->configBase}.feeds.{$feedName}.fields"));
        if (count($columns) > 0) {
            foreach ($columns as $column) {
                if (!isset($report[$column])) {
                    $this->requiredField = $column;
                    return false;
                }
            }
        }
        return true;
    }
}
