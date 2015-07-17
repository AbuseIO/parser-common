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
}
