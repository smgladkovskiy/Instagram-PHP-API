<?php namespace MetzWeb\Instagram\Exceptions;

class PaginationException extends InstagramException
{

    /**
     * PaginationException constructor.
     */
    public function __construct()
    {
        parent::__construct("Error: pagination() | This method doesn't support pagination.");
    }
}