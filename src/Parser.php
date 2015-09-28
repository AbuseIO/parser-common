<?php

namespace AbuseIO\Parsers;

class Parser
{
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
        return [
            'errorStatus'   => false,
            'errorMessage'  => 'Data sucessfully parsed',
            'data'          => $data,
        ];
    }

    /**
     * Check if the feed specified is known in the parser config.
     * @param  string  $name Current feed name
     * @return boolean      Return true of false
     */
    protected function isKnownFeed($configBase, $feedName)
    {
        if (empty(config("{$configBase}.feeds.{$feedName}"))) {
            return $this->failed(
                "Detected feed '{$feedName}' is unknown."
            );
        }
        return true;
    }

    /**
     * Check and see if a feed is enabled.
     * @param  string $name Current feed name
     * @return boolean      Return true of false
     */
    protected function isEnabledFeed($configBase, $feedName)
    {
        return (config("{$configBase}.feeds.{$feedName}.enabled") === true) ? true : false;
    }

    /**
     * Check if all required fields are in the report.
     * @param  array  $report Report data
     * @param  string $name   Current feed name
     * @return boolean        Returns true or fails the parsing
     */
    protected function hasRequiredFields($configBase, $feedName, $report)
    {
        $columns = array_filter(config("{$configBase}.feeds.{$feedName}.fields"));
        if (count($columns) > 0) {
            foreach ($columns as $column) {
                if (!isset($report[$column])) {
                    return $this->failed(
                        "Required field ${column} is missing in the report or config is incorrect."
                    );
                }
            }
        }
        return true;
    }
}
