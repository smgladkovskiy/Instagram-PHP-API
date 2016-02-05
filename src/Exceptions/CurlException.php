<?php namespace MetzWeb\Instagram\Exceptions;

class CurlException extends InstagramException
{

    /**
     * CurlException constructor.
     *
     * @param $curl_error
     */
    public function __construct($curl_error)
    {
        parent::__construct('cURL error: ' . $curl_error, 500);
    }
}