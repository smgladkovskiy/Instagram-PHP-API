<?php namespace MetzWeb\Instagram\Exceptions;

use Exception;

class InstagramException extends Exception
{

    public function __construct($message)
    {
        parent::__construct('Instagram error: ' . $message);
    }
}
